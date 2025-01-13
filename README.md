
# Using Laravel Dusk to download Paddle payout PDFs

This project uses Laravel Dusk to log into [Paddle](https://paddle.com)'s web interface, navigate to the payouts page, and download the available PDF files for payouts.

I use these payout PDFs for accounting purposes by syncing them as invoices to my accounting software. Since this process only happens once a month, it might not seem like a big task. However, if you manage multiple Paddle accounts for different products, it can quickly take several hours each month to complete. So, let's automate it!


## Commands used after a clean laravel project setup
```
composer require laravel/dusk
```
When prompted, don't rerun with `--dev` if you want to run this in production and not just in your local/testing environments.
```
php artisan dusk:install
```
This command will download the Chromium browser driver.

> Note: Laravel Dusk requires a browser to be installed on the system where it runs. So make sure to install the `google-chrome-stable` package on the machine that will execute this process.

## Laravel Dusk code to download Paddle payouts

The core functionality of this project resides in: `tests/Browser/PaddleDownloadPayoutPdfTest.php`

Here's what it does:
- Fetches the available `PaddleAccount` records (create at least one before running the script).
- Logs into the Paddle dashboard, navigates to the Payouts page, and retrieves the available payouts.
- Uses the `downloadPdf` function to download and store the PDFs for "US" and "RoW" invoices in `storage/app/private/paddle_invoices/`.
- Extracts the total amounts from the PDFs using the `PdfToText` package and the `extractTotalAmountFromPdfText` function.
- Creates or updates the PaddlePayout records in the database.

## Running the test

```
php artisan dusk
```

After running the test, you’ll have `PaddlePayout` records and the corresponding PDFs at your disposal!

