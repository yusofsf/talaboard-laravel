<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepositRequest extends Model
{
    protected $fillable = ['user_id', 'amount', 'note', 'status', 'admin_note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
