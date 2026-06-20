<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // موجودی نقره‌ی فیزیکی هر کاربر (دفترکل، مشابه wallet_transactions ولی برحسب گرم)
        Schema::create('silver_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purity'); // 999 | 995
            $table->decimal('grams', 12, 4); // علامت‌دار: + واریز، - برداشت
            $table->string('type'); // purchase, sale, p2p_buy, p2p_sell, offer_escrow, offer_refund, delivery
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // اتاق معاملاتی — پیشنهادهای خرید/فروش نقره بین اعضای ویژه
        Schema::create('trade_room_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('side', ['buy', 'sell']);
            $table->string('purity'); // 999 | 995
            $table->decimal('grams', 12, 4);
            $table->bigInteger('price_per_gram');
            $table->enum('status', ['open', 'completed', 'cancelled'])->default('open');
            $table->foreignId('counterparty_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // درخواست تحویل فیزیکی نقره
        Schema::create('silver_delivery_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purity');
            $table->decimal('grams', 12, 4);
            $table->string('recipient_name');
            $table->string('phone');
            $table->text('address');
            $table->enum('status', ['pending', 'approved', 'shipped', 'delivered', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('silver_delivery_requests');
        Schema::dropIfExists('trade_room_offers');
        Schema::dropIfExists('silver_ledger');
    }
};
