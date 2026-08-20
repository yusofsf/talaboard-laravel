<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gold_prices', function (Blueprint $table) {
            $table->id();

            // قیمت‌های داخلی به تومان؛ برای هر قلم هر دو سمت خرید و فروش نگهداری می‌شود.
            $table->unsignedBigInteger('bahar_sell')->nullable();
            $table->unsignedBigInteger('bahar_buy')->nullable();
            $table->unsignedBigInteger('nim_sell')->nullable();
            $table->unsignedBigInteger('nim_buy')->nullable();
            $table->unsignedBigInteger('rob_sell')->nullable();
            $table->unsignedBigInteger('rob_buy')->nullable();
            $table->unsignedBigInteger('mithqal_sell')->nullable();
            $table->unsignedBigInteger('mithqal_buy')->nullable();
            $table->unsignedBigInteger('geram_sell')->nullable();
            $table->unsignedBigInteger('geram_buy')->nullable();

            // انس جهانی با اعشار و بر حسب دلار است.
            $table->decimal('ounce', 14, 4)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_prices');
    }
};
