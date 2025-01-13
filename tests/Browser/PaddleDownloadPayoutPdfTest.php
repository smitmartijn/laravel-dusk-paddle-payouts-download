<?php

use Laravel\Dusk\Browser;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Facebook\WebDriver\WebDriverBy;
use App\Models\PaddleAccount;
use Spatie\PdfToText\Pdf;
use Carbon\Carbon;

/**
 * This is the Laravel Dusk function to download Paddle payouts as PDFs.
 *
 * To begin, you need to have the following:
 *  - A Paddle account with payouts permissions (the 'finance' role is sufficient)
 *  - Create a PaddleAccount record for each account in the database
 *
 * Process:
 *  - Logs in to the Paddle dashboard, navigates to the Payouts page, and parses the payouts.
 *  - Uses the `downloadPdf` function to download and store the PDFs for "US" and "RoW" invoices.
 *  - Extracts the total amount from the PDFs using the PdfToText package and `extractTotalAmountFromPdfText` function.
 *  - Creates or updates the PaddlePayout records in the database.
 *
 * After this function has run, you can query the database to get the payouts data and sync it somewhere else (like an accounting system).
 */
test('parses payouts from Paddle and downloads PDFs', function () {
  $this->browse(function (Browser $browser) {

    $accounts = PaddleAccount::where('login_email', '!=', null)
      ->get();

    foreach ($accounts as $account) {
      $browser->visit('https://vendors.paddle.com')
        ->type('email', $account->login_email)
        ->type('password', $account->login_password)
        ->press('Login')
        ->waitForText('Business Account', 30);

      $browser->clickLink('Business Account')
        ->waitForLink('Payouts', 30)
        ->clickLink('Payouts')
        ->waitFor('pui-table', 30);

      $rows = $browser->waitFor('pui-thead pui-th', 30)
        ->elements('pui-tbody pui-tr');

      foreach ($rows as $row) {
        $columns = $row->findElements(WebDriverBy::cssSelector('pui-td'));

        $reference = $columns[0]->getText();
        $date = $columns[1]->getText();
        $amount = $columns[2]->getText();
        $notes = $columns[3]->getText();

        $buttons = $columns[4]->findElements(WebDriverBy::cssSelector('pui-button'));
        $invoiceUsLink = '';
        $invoiceRoWLink = '';

        foreach ($buttons as $button) {
          $buttonText = $button->getText();
          if (strpos($buttonText, 'US') !== false) {
            $invoiceUsLink = $button->getAttribute('href');
          } elseif (strpos($buttonText, 'RoW') !== false) {
            $invoiceRoWLink = $button->getAttribute('href');
          }
        }

        Log::info("{$account->name} Found payout: Reference={$reference}, Date={$date}, Amount={$amount}, Notes={$notes}, Links: US={$invoiceUsLink}, RoW={$invoiceRoWLink}");

        $filenameAccount = Str::slug($account->name);
        $invoiceDate = Carbon::parse($date)->format('Y-m-d');
        $usPdf = downloadPdf($browser, $invoiceUsLink, "{$filenameAccount}_{$reference}_{$invoiceDate}_us_invoice.pdf");
        $rowPdf = downloadPdf($browser, $invoiceRoWLink, "{$filenameAccount}_{$reference}_{$invoiceDate}_row_invoice.pdf");

        // use ocr to get the total amount per invoice, as the Amount column is both invoices combined
        $usPdfText = Pdf::getText(Storage::path($usPdf));
        $rowPdfText = Pdf::getText(Storage::path($rowPdf));

        $usTotal = extractTotalAmountFromPdfText($usPdfText);
        $rowTotal = extractTotalAmountFromPdfText($rowPdfText);

        if (empty($usTotal) || empty($rowTotal)) {
          throw new Exception("Could not extract total amount from PDFs for Reference={$reference}.");
        }

        // TODO: Add your own currency here, if needed
        $usAmount = str_replace(['€', ','], ['', ''], $usTotal);
        $rowAmount = str_replace(['€', ','], ['', ''], $rowTotal);

        // create us payment
        $account->paddlePayouts()->updateOrCreate(
          ['reference' => $reference, 'region' => 'us'],
          [
            'date' => $date,
            'amount' => $usAmount,
            'notes' => $notes,
            'invoice_link' => $invoiceUsLink,
            'invoice_attachment' => $usPdf,
          ]
        );
        // create row payment
        $account->paddlePayouts()->updateOrCreate(
          ['reference' => $reference, 'region' => 'row'],
          [
            'date' => $date,
            'amount' => $rowAmount,
            'notes' => $notes,
            'invoice_link' => $invoiceRoWLink,
            'invoice_attachment' => $rowPdf,
          ]
        );
      } // end foreach row

      $browser->visit('https://vendors.paddle.com/logout')
        ->waitForText('Login', 30);
    } // end foreach account
  });
});


function extractTotalAmountFromPdfText(string $pdfText): ?string
{
  if (preg_match('/Amount Due\s+€(\d{1,3}(?:,\d{3})*\.\d{2})/', $pdfText, $matches)) {
    return $matches[1];
  }
  return null;
}

/**
 * Download a PDF from a given URL.
 */
function downloadPdf(Browser $browser, string $url, string $filename): string
{
  $storagePath = 'paddle_invoices';
  if (!Storage::exists($storagePath)) {
    Storage::makeDirectory($storagePath);
  }

  try {
    // Record files before download
    $beforeFiles = glob(storage_path('temp/*.pdf'));

    // Use existing browser session to download PDF
    $browser->visit($url);
    // Wait for download to complete
    $browser->pause(3000);

    // Find new file by comparing before/after
    $afterFiles = glob(storage_path('temp/*.pdf'));
    $newFiles = array_diff($afterFiles, $beforeFiles);

    if (empty($newFiles)) {
      throw new Exception("No new PDF file was downloaded");
    }

    // Get the most recently downloaded file
    $downloadedFile = array_pop($newFiles);
    $fullPath = $storagePath . '/' . $filename;

    // Move the downloaded file to storage
    Storage::put($fullPath, file_get_contents($downloadedFile));
    unlink($downloadedFile);
    return $fullPath;
  } catch (Exception $e) {
    Log::error("PDF download for {$url} failed: " . $e->getMessage());
    throw $e;
  }
}
