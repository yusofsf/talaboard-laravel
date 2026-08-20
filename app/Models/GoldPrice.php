<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoldPrice extends Model
{
    protected $fillable = [
        'bahar_sell',
        'bahar_buy',
        'nim_sell',
        'nim_buy',
        'rob_sell',
        'rob_buy',
        'mithqal_sell',
        'mithqal_buy',
        'geram_sell',
        'geram_buy',
        'ounce',
    ];

    protected function casts(): array
    {
        return [
            'bahar_sell' => 'integer',
            'bahar_buy' => 'integer',
            'nim_sell' => 'integer',
            'nim_buy' => 'integer',
            'rob_sell' => 'integer',
            'rob_buy' => 'integer',
            'mithqal_sell' => 'integer',
            'mithqal_buy' => 'integer',
            'geram_sell' => 'integer',
            'geram_buy' => 'integer',
            'ounce' => 'float',
        ];
    }

    /** Build the normalized database row from PriceService::all(). */
    public static function fromPayload(array $payload): array
    {
        $sell = $payload['gold'] ?? [];
        $buy = $payload['gold_buy'] ?? [];

        return [
            'bahar_sell' => $sell['bahar'] ?? null,
            'bahar_buy' => $buy['bahar'] ?? null,
            'nim_sell' => $sell['nim'] ?? null,
            'nim_buy' => $buy['nim'] ?? null,
            'rob_sell' => $sell['rob'] ?? null,
            'rob_buy' => $buy['rob'] ?? null,
            'mithqal_sell' => $sell['mithqal'] ?? null,
            'mithqal_buy' => $buy['mithqal'] ?? null,
            'geram_sell' => $sell['geram'] ?? null,
            'geram_buy' => $buy['geram'] ?? null,
            'ounce' => $payload['ounce']['gold'] ?? null,
        ];
    }
}
