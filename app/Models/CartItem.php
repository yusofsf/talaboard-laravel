<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CartItem extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_id', 'trade_type', 'item', 'item_label', 'item_group',
        'quantity', 'price_per_unit', 'total',
    ];

    protected $casts = [
        'quantity' => 'float',
        'price_per_unit' => 'integer',
        'total' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
