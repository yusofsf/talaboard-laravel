<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_collateral_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset', 32);
            $table->decimal('quantity', 18, 4);
            $table->unsignedBigInteger('trade_limit_amount')->default(0);
            $table->unsignedBigInteger('used_amount')->default(0);
            $table->string('status', 24)->default('pending')->index();
            $table->text('note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('source', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_collateral_requests');
    }
};
