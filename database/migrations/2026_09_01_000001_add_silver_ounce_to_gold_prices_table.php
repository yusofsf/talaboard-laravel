<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gold_prices', function (Blueprint $table) {
            $table->decimal('silver_ounce', 14, 4)->nullable()->after('ounce');
        });
    }

    public function down(): void
    {
        Schema::table('gold_prices', function (Blueprint $table) {
            $table->dropColumn('silver_ounce');
        });
    }
};
