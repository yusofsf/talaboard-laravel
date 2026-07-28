<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_room_offers', function (Blueprint $table) {
            // Existing offers keep their current behaviour after deployment.
            $table->boolean('allow_partial_fill')->default(true)->after('price_per_gram');
        });
    }

    public function down(): void
    {
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->dropColumn('allow_partial_fill');
        });
    }
};
