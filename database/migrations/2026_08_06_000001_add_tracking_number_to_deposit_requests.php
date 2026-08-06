<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->string('tracking_number', 100)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->dropColumn('tracking_number');
        });
    }
};
