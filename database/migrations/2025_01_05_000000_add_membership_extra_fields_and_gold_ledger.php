<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('national_id');
            $table->string('residence_address')->nullable()->after('birth_date');
        });

        // موجودی طلا (گرم) — مشابه silver_ledger، فقط برای آیتم «گرم طلا»
        Schema::create('gold_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('grams', 12, 4);
            $table->string('type'); // purchase, sale, p2p_buy, p2p_sell, offer_escrow, offer_refund
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // برای پیشنهادهای طلا، purity کاربردی ندارد — مقدار رشته‌ی خالی '' ثبت می‌شود
        // (به‌جای null، تا نیازی به doctrine/dbal برای تغییر nullable نباشد)
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->string('metal')->default('silver')->after('user_id'); // gold | silver
        });
    }

    public function down(): void
    {
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->dropColumn('metal');
        });
        Schema::dropIfExists('gold_ledger');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'residence_address']);
        });
    }
};
