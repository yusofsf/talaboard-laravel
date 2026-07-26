<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('api_tokens', function (Blueprint $table) { $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('token_hash', 64)->unique(); $table->json('abilities'); $table->timestamp('last_used_at')->nullable(); $table->timestamp('expires_at')->nullable(); $table->timestamps(); $table->softDeletes(); });
        foreach (['users','articles','article_tags','article_topics','cart_items','transactions','trade_room_offers','tickets','ticket_messages','notifications','bank_cards','deposit_requests','withdrawal_requests','silver_delivery_requests','inventory_increase_requests','invite_codes'] as $table) { if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) Schema::table($table, fn (Blueprint $t) => $t->softDeletes()); }
    }
    public function down(): void { foreach (['users','articles','article_tags','article_topics','cart_items','transactions','trade_room_offers','tickets','ticket_messages','notifications','bank_cards','deposit_requests','withdrawal_requests','silver_delivery_requests','inventory_increase_requests','invite_codes'] as $table) { if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes()); } Schema::dropIfExists('api_tokens'); }
};
