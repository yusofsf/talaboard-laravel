<?php

namespace Tests\Feature;

use App\Models\GoldLedger;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramBotApiTest extends TestCase
{
    use RefreshDatabase;

    private function botUser(): User
    {
        config()->set('services.telegram.link_api_token', 'test-bot-token');
        config()->set('logging.default', 'null');

        return User::factory()->create([
            'telegram_chat_id' => '778899',
            'is_vip' => true,
            'membership_level' => 2,
        ]);
    }

    public function test_bot_can_read_balances_and_create_trade_room_offer(): void
    {
        $user = $this->botUser();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 1000000000, 'type' => 'deposit', 'description' => 'test']);

        $this->withToken('test-bot-token')->postJson('/api/telegram/overview', ['telegram_chat_id' => '778899'])
            ->assertOk()->assertJsonPath('wallet_balance', 1000000000);

        $this->withToken('test-bot-token')->postJson('/api/telegram/trade-room/offers/create', [
            'telegram_chat_id' => '778899', 'asset' => 'gold', 'side' => 'buy',
            'unit' => 'gram', 'quantity' => 1, 'unit_price' => 1000,
        ])->assertCreated()->assertJsonPath('status', 'open');

        $this->assertDatabaseHas('trade_room_offers', ['user_id' => $user->id, 'metal' => 'gold', 'side' => 'buy', 'status' => 'open']);
        $this->assertSame(999999000, $user->fresh()->walletBalance());
    }

    public function test_bot_can_create_delivery_request_and_read_its_status(): void
    {
        $user = $this->botUser();
        GoldLedger::create(['user_id' => $user->id, 'grams' => 12, 'type' => 'test', 'description' => 'test']);

        $response = $this->withToken('test-bot-token')->postJson('/api/telegram/deliveries', [
            'telegram_chat_id' => '778899', 'asset' => 'gold', 'quantity' => 10,
            'recipient_name' => 'گیرنده', 'phone' => '09120000000', 'delivery_method' => 'pickup',
        ])->assertCreated();

        $id = $response->json('id');
        $this->withToken('test-bot-token')->postJson("/api/telegram/deliveries/{$id}", ['telegram_chat_id' => '778899'])
            ->assertOk()->assertJsonPath('status', 'pending');
        $this->assertSame(2.0, $user->fresh()->goldBalance());
    }
}
