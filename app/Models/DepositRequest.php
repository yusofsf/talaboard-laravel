<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepositRequest extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'amount', 'tracking_number', 'note', 'receipt_path', 'source', 'status', 'admin_note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
