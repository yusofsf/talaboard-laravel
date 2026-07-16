<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// هر ۱۰ ثانیه قیمت‌ها را گرفته و در دیتابیس می‌نویسد؛ صفحه‌ی اصلی آخرین رکورد را می‌خواند.
// نیازمند اجرای دائمی scheduler است: php artisan schedule:work (یا کرون دقیقه‌ای schedule:run در سرور).
Schedule::command('prices:snapshot')
    ->everyTenSeconds()
    ->withoutOverlapping();

Schedule::command('trade-room:expire-open-offers')
    ->everyMinute()
    ->withoutOverlapping();
