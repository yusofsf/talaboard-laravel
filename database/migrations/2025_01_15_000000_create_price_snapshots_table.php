<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // عکس فوری قیمت‌ها — هر ۱۰ ثانیه توسط فرمان prices:snapshot از API/منابع گرفته و اینجا ذخیره می‌شود.
        // صفحه‌ی اصلی آخرین رکورد را می‌خواند (به‌جای فراخوانی زنده‌ی منابع روی هر درخواست).
        Schema::create('price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->json('payload');   // کل خروجی PriceService::all()
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_snapshots');
    }
};
