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

        DB::transaction(function () use ($payload) {
            PriceSnapshot::create(['payload' => $payload]);

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
}
