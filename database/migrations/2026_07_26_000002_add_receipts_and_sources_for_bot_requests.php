<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_increase_requests', function (Blueprint $table) {
            $table->string('receipt_path')->nullable();
        });

        Schema::table('silver_delivery_requests', function (Blueprint $table) {
            $table->string('source', 30)->default('website')->index();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_increase_requests', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });

        Schema::table('silver_delivery_requests', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
