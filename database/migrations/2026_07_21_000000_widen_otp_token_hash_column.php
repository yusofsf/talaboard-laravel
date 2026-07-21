<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OTPs are bcrypt hashes, not six-character plaintext values.
        Schema::table('otp_tokens', function (Blueprint $table) {
            $table->string('otp', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('otp_tokens', function (Blueprint $table) {
            $table->string('otp', 6)->change();
        });
    }
};
