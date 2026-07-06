<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('severity', 20)->default('medium');
            $table->string('route_name')->nullable();
            $table->string('path');
            $table->string('method', 10);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('payload')->nullable();
            $table->json('matched_fields')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
