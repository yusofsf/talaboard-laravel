<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // معاملات فروشگاه: وضعیت + یادداشت ادمین برای رد معامله با دلیل.
        // status: active | rejected — معاملات رد شده از موجودی و حسابداری کنار گذاشته می‌شوند.
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('status')->default('active')->after('total');
            $table->text('admin_note')->nullable()->after('status');
        });

        // اتاق معاملاتی: یادداشت ادمین برای رد/برگشت معامله با دلیل.
        // (وضعیت رد از همان مقدار موجود cancelled استفاده می‌کند تا قید enum اسکیوال‌لایت دست‌نخورده بماند.)
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->text('admin_note')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['status', 'admin_note']);
        });
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->dropColumn('admin_note');
        });
    }
};
