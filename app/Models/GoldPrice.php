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
        'silver_ounce',
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
            'silver_ounce' => 'float',
        ];
    }

    /** Build the normalized database row from the served snapshot payload. */
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
            'silver_ounce' => $payload['ounce']['silver'] ?? null,
        ];
    }

    /**
     * آخرین مقدار غیر-null هر قلم طلا (فروش و خرید) از تاریخچه؛
     * هنگام قطعی منبع قیمت برای پرکردن کلیدهای null در payload اسنپشات استفاده میشود.
     *
     * @return array{gold: array<string, int|null>, gold_buy: array<string, int|null>}
     */
    public static function lastKnownSellBuy(): array
    {
        $columns = ['bahar', 'nim', 'rob', 'mithqal', 'geram'];

        $sell = array_fill_keys($columns, null);
        $buy = array_fill_keys($columns, null);

        $latest = static::query()->latest('id')->first();

        if ($latest === null) {
            return ['gold' => $sell, 'gold_buy' => $buy];
        }

        // مسیر سریع: ردیف آخر در حالت عادی همهی ستونهای طلا را دارد.
        foreach ($columns as $key) {
            $sell[$key] = $latest->{"{$key}_sell"};
            $buy[$key] = $latest->{"{$key}_buy"};
        }

        // ستونهای خالی (قطعی منبع): تا آخرین مقدار ثبتشده در تاریخچه به عقب برگرد.
        foreach ($columns as $key) {
            if ($sell[$key] === null) {
                $value = static::query()->whereNotNull("{$key}_sell")->latest('id')->value("{$key}_sell");
                $sell[$key] = $value !== null ? (int) $value : null;
            }

            if ($buy[$key] === null) {
                $value = static::query()->whereNotNull("{$key}_buy")->latest('id')->value("{$key}_buy");
                $buy[$key] = $value !== null ? (int) $value : null;
            }
        }

        return ['gold' => $sell, 'gold_buy' => $buy];
    }

    /** آخرین مقدار غیر-null انس طلا و نقره از تاریخچه؛ برای پرکردن هنگام قطعی همهی منابع انس. */
    public static function lastKnownOunce(): array
    {
        $gold = static::query()->whereNotNull('ounce')->latest('id')->value('ounce');
        $silver = static::query()->whereNotNull('silver_ounce')->latest('id')->value('silver_ounce');

        return [
            'gold' => $gold !== null ? (float) $gold : null,
            'silver' => $silver !== null ? (float) $silver : null,
        ];
    }
}
