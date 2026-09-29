<?php

namespace App\Console\Commands;

use App\Models\GoldPrice;
use App\Models\PriceSnapshot;
use App\Services\PriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SnapshotPrices extends Command
{
    protected $signature = 'prices:snapshot';

    protected $description = 'گرفتن قیمت‌ها از منابع/API و ذخیره‌ی یک عکس فوری در دیتابیس (هر ۱۰ ثانیه توسط زمان‌بند اجرا می‌شود)';

    public function handle(PriceService $prices): int
    {
        $payload = $prices->all();

        // هنگام قطعی منبع قیمت طلا، کلیدهای null (طلا، سکه و انس) با آخرین مقدار ثبتشده
        // پر میشوند تا تابلو، کانال و خوانندههای مستقیم جدول تاریخچه بدون قیمت نمانند.
        $served = $this->withLastKnownGold($payload);

        DB::transaction(function () use ($served) {
            PriceSnapshot::create(['payload' => $served]);

            // تاریخچهی ستونی طلا هم همان مقادیر سرویشده را نگه میدارد تا هیچ خوانندهای null نبیند.
            GoldPrice::create(GoldPrice::fromPayload($served));

            // فقط چند اسنپ‌شات JSON آخر برای نمایش سریع صفحه نگه داشته می‌شود.
            $cutoff = PriceSnapshot::query()->latest('id')->skip(20)->value('id');
            if ($cutoff) {
                PriceSnapshot::query()->where('id', '<=', $cutoff)->delete();
            }
        });

        return self::SUCCESS;
    }

    /**
     * هر کلید null در بخشهای gold و gold_buy و همچنین انس طلا/نقره را با آخرین مقدار
     * ثبتشدهی همان کلید از تاریخچهی gold_prices پر میکند. اگر تاریخچهای نباشد یا کلیدی
     * سابقه نداشته باشد، همان null باقی میماند. سایر بخشهای payload دستنخورده برمیگردند.
     */
    private function withLastKnownGold(array $payload): array
    {
        $hasMissing = false;

        foreach (['gold', 'gold_buy'] as $section) {
            if (is_array($payload[$section] ?? null) && in_array(null, $payload[$section], true)) {
                $hasMissing = true;
            }
        }

        foreach (['gold', 'silver'] as $ounce) {
            if (is_array($payload['ounce'] ?? null) && ($payload['ounce'][$ounce] ?? null) === null) {
                $hasMissing = true;
            }
        }

        if (! $hasMissing) {
            return $payload;
        }

        $lastKnown = GoldPrice::lastKnownSellBuy();

        foreach (['gold', 'gold_buy'] as $section) {
            if (! is_array($payload[$section] ?? null)) {
                continue;
            }

            foreach ($payload[$section] as $key => $value) {
                if ($value === null && ($lastKnown[$section][$key] ?? null) !== null) {
                    $payload[$section][$key] = $lastKnown[$section][$key];
                }
            }
        }

        if (is_array($payload['ounce'] ?? null)) {
            $lastOunce = GoldPrice::lastKnownOunce();

            foreach (['gold', 'silver'] as $ounce) {
                if (($payload['ounce'][$ounce] ?? null) === null && $lastOunce[$ounce] !== null) {
                    $payload['ounce'][$ounce] = $lastOunce[$ounce];
                }
            }
        }

        return $payload;
    }
}
