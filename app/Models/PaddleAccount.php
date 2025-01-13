<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaddleAccount extends Model
{
    protected $fillable = [
        'name',
        'login_email',
        'login_password',
    ];

    protected $casts = [
        'login_password' => 'encrypted',
    ];

    public function paddlePayouts(): HasMany
    {
        return $this->hasMany(PaddlePayout::class);
    }
}
