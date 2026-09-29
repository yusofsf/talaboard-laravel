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

        // هنگام قطعی منبع قیمت طلا، کلیدهای null طلا با آخرین مقدار ثبتشده پر میشود تا
        // تابلوی قیمت و مصرفکنندههای API (کانال) بدون قیمت نمانند؛ تاریخچه خام میماند.
        $served = $this->withLastKnownGold($payload);

        DB::transaction(function () use ($payload, $served) {
            PriceSnapshot::create(['payload' => $served]);

            // تاریخچه‌ی ستونی طلا مستقل از اسنپ‌شات‌های موقت نگهداری می‌شود.
            GoldPrice::create(GoldPrice::fromPayload($payload));

            // فقط چند اسنپ‌شات JSON آخر برای نمایش سریع صفحه نگه داشته می‌شود.
            $cutoff = PriceSnapshot::query()->latest('id')->skip(20)->value('id');
            if ($cutoff) {
                PriceSnapshot::query()->where('id', '<=', $cutoff)->delete();
            }
        });

        return self::SUCCESS;
    }

    /**
     * هر کلید null در بخشهای gold و gold_buy را با آخرین مقدار ثبتشدهی همان کلید از
     * تاریخچهی gold_prices پر میکند. اگر تاریخچهای نباشد یا کلیدی سابقه نداشته باشد،
     * همان null باقی میماند. سایر بخشهای payload دستنخورده برمیگردند.
     */
    private function withLastKnownGold(array $payload): array
    {
        if (! is_array($payload['gold'] ?? null) || ! is_array($payload['gold_buy'] ?? null)) {
            return $payload;
        }

        $hasMissing = false;

        foreach (['gold', 'gold_buy'] as $section) {
            if (in_array(null, $payload[$section], true)) {
                $hasMissing = true;

                break;
            }
        }

        if (! $hasMissing) {
            return $payload;
        }

        $lastKnown = GoldPrice::lastKnownSellBuy();

        foreach (['gold', 'gold_buy'] as $section) {
            foreach ($payload[$section] as $key => $value) {
                if ($value === null && ($lastKnown[$section][$key] ?? null) !== null) {
                    $payload[$section][$key] = $lastKnown[$section][$key];
                }
            }
        }

        return $payload;
    }
}
