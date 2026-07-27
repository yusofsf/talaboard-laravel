<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_reserved_amount')->default(0)->after('price_per_gram');
            $table->unsignedBigInteger('collateral_reserved_amount')->default(0)->after('wallet_reserved_amount');
        });
    }

    public function down(): void
    {
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->dropColumn(['wallet_reserved_amount', 'collateral_reserved_amount']);
        });
    }
};
