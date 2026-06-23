<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankCard extends Model
{
    protected $fillable = ['user_id', 'bank_name', 'card_number', 'account_number', 'shaba'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
