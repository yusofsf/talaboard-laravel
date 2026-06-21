<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    protected $fillable = ['user_id', 'amount', 'card_number', 'shaba', 'status', 'admin_note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
