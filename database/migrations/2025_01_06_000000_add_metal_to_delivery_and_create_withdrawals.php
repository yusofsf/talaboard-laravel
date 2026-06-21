<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // درخواست تحویل فیزیکی حالا هم طلا و هم نقره را پشتیبانی می‌کند
        Schema::table('silver_delivery_requests', function (Blueprint $table) {
            $table->string('metal')->default('silver')->after('user_id'); // gold | silver
        });

        // درخواست تسویه حساب (برداشت از کیف پول)
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->string('card_number');
            $table->string('shaba');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        Schema::table('silver_delivery_requests', function (Blueprint $table) {
            $table->dropColumn('metal');
        });
    }
};
