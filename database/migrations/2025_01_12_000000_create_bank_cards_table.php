<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // کارت‌های بانکی ذخیره‌شده‌ی کاربر — برای انتخاب سریع هنگام درخواست تسویه حساب.
        Schema::create('bank_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('card_number');
            $table->string('account_number')->nullable();
            $table->string('shaba');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_cards');
    }
};
