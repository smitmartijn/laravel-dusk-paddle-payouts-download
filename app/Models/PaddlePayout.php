<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaddlePayout extends Model
{
    protected $fillable = [
        'reference',
        'region', // usually "us" or "row"
        'date',
        'amount',
        'notes',
        'invoice_link', // original download link
        'invoice_attachment', // path to the PDF

        'paddle_account_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function paddleAccount()
    {
        return $this->belongsTo(PaddleAccount::class);
    }
}
