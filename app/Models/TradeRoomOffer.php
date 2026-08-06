<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TradeRoomOffer extends Model
{
    use SoftDeletes;

    public const GOLD_MIN_GRAMS = 1.0;

    public const SILVER_MIN_GRAMS = 10.0;

    protected $fillable = ['parent_offer_id', 'user_id', 'source', 'metal', 'item', 'side', 'purity', 'grams', 'price_per_gram', 'allow_partial_fill', 'wallet_reserved_amount', 'collateral_reserved_amount', 'status', 'counterparty_id', 'completed_at', 'admin_note', 'commission'];

    protected $casts = ['grams' => 'decimal:4', 'allow_partial_fill' => 'boolean', 'completed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function counterparty()
    {
        return $this->belongsTo(User::class, 'counterparty_id');
    }

    public function parentOffer()
    {
        return $this->belongsTo(self::class, 'parent_offer_id');
    }

    public function total(): int
    {
        return (int) round((float) $this->grams * $this->price_per_gram);
    }

    public static function minimumGrams(string $metal): float
    {
        return $metal === 'silver' ? self::SILVER_MIN_GRAMS : self::GOLD_MIN_GRAMS;
    }
}
