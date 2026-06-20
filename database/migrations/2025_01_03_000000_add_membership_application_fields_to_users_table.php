<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // سطح عضویت: ۱ = کاربر عادی (ثبت‌نام‌شده)، ۲ = عضو ویژه
            $table->unsignedTinyInteger('membership_level')->default(1)->after('is_vip');
            // وضعیت درخواست عضویت ویژه: none | pending | approved | rejected
            $table->string('membership_status')->default('none')->after('membership_level');
            $table->string('national_id_doc')->nullable()->after('membership_status');
            $table->string('identity_doc')->nullable()->after('national_id_doc');
            $table->string('verification_video')->nullable()->after('identity_doc');
        });

        // هماهنگ‌سازی کاربران ویژه‌ی فعلی با سطح عددی جدید
        DB::table('users')->where('is_vip', true)->update([
            'membership_level'  => 2,
            'membership_status' => 'approved',
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'membership_level', 'membership_status',
                'national_id_doc', 'identity_doc', 'verification_video',
            ]);
        });
    }
};
