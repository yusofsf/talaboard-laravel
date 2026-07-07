<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('trade_type', ['buy', 'sell']);
            $table->string('item');
            $table->string('item_label');
            $table->string('item_group');
            $table->decimal('quantity', 12, 4);
            $table->bigInteger('price_per_unit');
            $table->bigInteger('total');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
