<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankCard extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id', 'bank_name', 'card_number', 'account_number', 'shaba'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
