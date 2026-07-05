<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('silver_delivery_requests', function (Blueprint $table) {
            $table->enum('delivery_method', ['address', 'pickup'])->default('address')->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('silver_delivery_requests', function (Blueprint $table) {
            $table->dropColumn('delivery_method');
        });
    }
};
