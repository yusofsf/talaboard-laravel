<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetCollateralRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'asset',
        'quantity',
        'trade_limit_amount',
        'used_amount',
        'status',
        'note',
        'admin_note',
        'source',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'trade_limit_amount' => 'integer',
        'used_amount' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function availableAmount(): int
    {
        return max(0, (int) $this->trade_limit_amount - (int) $this->used_amount);
    }
}
