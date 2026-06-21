<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = ['user_id', 'type', 'item', 'item_label', 'quantity', 'price_per_unit', 'total', 'status', 'admin_note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
