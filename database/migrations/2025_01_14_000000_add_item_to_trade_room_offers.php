<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // برای پشتیبانی از سکه‌ها در اتاق معاملاتی: metal='coin' و item نوع سکه (bahar|nim|rob) را مشخص می‌کند.
        // برای طلا/نقره item خالی می‌ماند و grams/price_per_gram مثل قبل برحسب گرم است؛ برای سکه grams=تعداد و price_per_gram=قیمت هر عدد.
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->string('item')->nullable()->after('metal');
        });
    }

    public function down(): void
    {
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->dropColumn('item');
        });
    }
};
