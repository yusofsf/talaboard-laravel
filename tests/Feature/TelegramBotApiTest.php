<?php

namespace Tests\Feature;

use App\Models\AssetCollateralRequest;
use App\Models\GoldLedger;
use App\Models\TradeRoomOffer;
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

        $this->assertDatabaseHas('trade_room_offers', [
            'user_id' => $user->id,
            'source' => 'telegram_bot',
            'metal' => 'gold',
            'side' => 'buy',
            'status' => 'open',
        ]);
        $this->assertSame(999999000, $user->fresh()->walletBalance());

        $viewer = User::factory()->admin()->create();
        $this->actingAs($viewer)->get('/trade-room')
            ->assertInertia(fn ($page) => $page
                ->where('buyOffers.0.id', TradeRoomOffer::firstOrFail()->id)
                ->where('buyOffers.0.is_from_bot', true));
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

    public function test_bot_can_create_buy_offer_with_approved_asset_collateral(): void
    {
        $user = $this->botUser();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 1000, 'type' => 'deposit', 'description' => 'test']);
        AssetCollateralRequest::create([
            'user_id' => $user->id,
            'asset' => 'gold',
            'quantity' => 10,
            'trade_limit_amount' => 5000,
            'status' => 'approved',
            'source' => 'test',
        ]);

        $this->withToken('test-bot-token')->postJson('/api/telegram/trade-room/offers/create', [
            'telegram_chat_id' => '778899', 'asset' => 'gold', 'side' => 'buy',
            'unit' => 'gram', 'quantity' => 3, 'unit_price' => 1000,
        ])->assertCreated()->assertJsonPath('status', 'open');

        $offer = TradeRoomOffer::first();
        $this->assertSame(1000, (int) $offer->wallet_reserved_amount);
        $this->assertSame(2000, (int) $offer->collateral_reserved_amount);
        $this->assertSame(0, $user->fresh()->walletBalance());
        $this->assertSame(3000, AssetCollateralRequest::first()->availableAmount());
    }

    public function test_bot_can_read_own_completed_trade_room_history(): void
    {
        $user = $this->botUser();
        $other = User::factory()->vip()->create();
        TradeRoomOffer::create([
            'user_id' => $other->id,
            'counterparty_id' => $user->id,
            'metal' => 'gold',
            'purity' => '',
            'side' => 'sell',
            'grams' => 2,
            'price_per_gram' => 1000,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->withToken('test-bot-token')->postJson('/api/telegram/trade-room/offers', [
            'telegram_chat_id' => '778899',
            'mine' => true,
            'status' => 'accepted',
        ])->assertOk()
            ->assertJsonPath('offers.0.status', 'completed')
            ->assertJsonPath('offers.0.is_counterparty', true);
    }
}
