<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradeRoomOffer extends Model
{
    protected $fillable = ['parent_offer_id', 'user_id', 'metal', 'item', 'side', 'purity', 'grams', 'price_per_gram', 'status', 'counterparty_id', 'completed_at', 'admin_note', 'commission'];

    protected $casts = ['grams' => 'decimal:4', 'completed_at' => 'datetime'];

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
}
