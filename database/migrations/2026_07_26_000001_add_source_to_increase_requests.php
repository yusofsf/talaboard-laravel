<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->string('source', 30)->default('website')->index();
        });

        Schema::table('inventory_increase_requests', function (Blueprint $table) {
            $table->string('source', 30)->default('website')->index();
        });
    }

    public function down(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });

        Schema::table('inventory_increase_requests', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
