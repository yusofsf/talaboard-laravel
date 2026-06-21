<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // گزارش فعالیت (سیستم لاگ) — ثبت رویدادهای مهم برای مشاهده‌ی ادمین.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // عامل رویداد
            $table->string('action');        // کلید رویداد: login، trade_buy، admin_action، ...
            $table->string('category')->default('other'); // auth | trade | wallet | admin | membership | other
            $table->text('description');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
            $table->index('category');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
