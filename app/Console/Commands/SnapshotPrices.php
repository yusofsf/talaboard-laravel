<?php

namespace App\Console\Commands;

use App\Models\PriceSnapshot;
use App\Services\PriceService;
use Illuminate\Console\Command;

class SnapshotPrices extends Command
{
    protected $signature = 'prices:snapshot';

    protected $description = 'گرفتن قیمت‌ها از منابع/API و ذخیره‌ی یک عکس فوری در دیتابیس (هر ۱۰ ثانیه توسط زمان‌بند اجرا می‌شود)';

    public function handle(PriceService $prices): int
    {
        PriceSnapshot::create(['payload' => $prices->all()]);

        // فقط چند رکورد آخر را نگه می‌داریم تا جدول بی‌نهایت رشد نکند.
        $cutoff = PriceSnapshot::query()->latest('id')->skip(20)->value('id');
        if ($cutoff) {
            PriceSnapshot::query()->where('id', '<=', $cutoff)->delete();
        }

        return self::SUCCESS;
    }
}
