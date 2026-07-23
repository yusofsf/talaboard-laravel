<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_room_offers', function (Blueprint $table) {
            // پذیرش‌های جزئی به‌صورت معامله‌های مستقل نگهداری می‌شوند تا هم
            // تاریخچه و هم امکان برگشت هر معامله محفوظ بماند.
            $table->foreignId('parent_offer_id')->nullable()->after('id')
                ->constrained('trade_room_offers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_offer_id');
        });
    }
};
