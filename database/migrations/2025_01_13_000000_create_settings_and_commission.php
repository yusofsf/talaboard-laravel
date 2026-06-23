<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تنظیمات کلید-مقدار قابل‌ویرایش توسط ادمین (مثل درصد کارمزد اتاق معاملاتی)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insert([
            'key' => 'trade_room_commission_percent',
            'value' => '0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // مبلغ کل کارمزد کسر‌شده هنگام تکمیل معامله (تومان) — برای نمایش/گزارش
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->unsignedBigInteger('commission')->default(0)->after('admin_note');
        });
    }

    public function down(): void
    {
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->dropColumn('commission');
        });
        Schema::dropIfExists('settings');
    }
};
