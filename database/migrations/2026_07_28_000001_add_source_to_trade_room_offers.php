<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->string('source', 30)->default('website')->index()->after('user_id');
        });

        // سفارش‌های اتاق معاملاتی ربات در لاگ فعالیت شناسه سفارش را دارند.
        if (Schema::hasTable('activity_logs')) {
            DB::table('activity_logs')
                ->where('action', 'telegram_room_offer')
                ->pluck('description')
                ->each(function (?string $description) {
                    if ($description && preg_match('/#(\d+)/u', $description, $matches)) {
                        DB::table('trade_room_offers')
                            ->where('id', (int) $matches[1])
                        ->update(['source' => 'telegram_bot']);
                    }
                });

            // پذیرش‌های جزئی، منشأ سفارش مادر را به ارث می‌برند.
            DB::table('trade_room_offers')
                ->whereNotNull('parent_offer_id')
                ->pluck('parent_offer_id', 'id')
                ->each(function ($parentId, $id) {
                    $parentSource = DB::table('trade_room_offers')->where('id', (int) $parentId)->value('source');
                    if ($parentSource === 'telegram_bot') {
                        DB::table('trade_room_offers')->where('id', (int) $id)->update(['source' => 'telegram_bot']);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('trade_room_offers', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
