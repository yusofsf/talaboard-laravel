<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('salt', 64)->nullable()->after('password');
        });

        foreach (DB::table('users')->whereNull('salt')->orderBy('id')->cursor() as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['salt' => Str::random(32)]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('salt');
        });
    }
};
