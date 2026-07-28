<?php

namespace Tests\Feature;

use App\Models\AssetCollateralRequest;
use App\Models\TradeRoomOffer;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeRoomExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function fund(User $user, int $amount): void
    {
        WalletTransaction::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'deposit',
            'description' => 'seed',
        ]);
    }

    public function test_open_buy_offers_expire_after_midnight_and_refund_wallet_escrow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00')); // Monday

        $buyer = User::factory()->vip()->admin()->create();
        $this->fund($buyer, 1_000_000);

        $this->actingAs($buyer)->post('/trade-room', [
            'metal' => 'gold',
            'side' => 'buy',
            'grams' => 100,
            'price_per_gram' => 1_000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $offer = TradeRoomOffer::firstOrFail();
        $this->assertSame(900_000, $buyer->refresh()->walletBalance());

        Carbon::setTestNow(Carbon::parse('2026-07-14 00:01:00'));

        $this->artisan('trade-room:expire-open-offers')->assertSuccessful();

        $this->assertSame('cancelled', $offer->refresh()->status);
        $this->assertSame(1_000_000, $buyer->refresh()->walletBalance());
    }

    public function test_thursday_offers_stay_open_until_the_end_of_saturday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00')); // Thursday

        $buyer = User::factory()->vip()->admin()->create();
        $this->fund($buyer, 1_000_000);

        $response = $this->actingAs($buyer)->post('/trade-room', [
            'metal' => 'gold',
            'side' => 'buy',
            'grams' => 100,
            'price_per_gram' => 1_000,
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertStringContainsString('شنبه', session('success'));

        $offer = TradeRoomOffer::firstOrFail();

        Carbon::setTestNow(Carbon::parse('2026-07-18 23:30:00')); // Saturday
        $this->artisan('trade-room:expire-open-offers')->assertSuccessful();
        $this->assertSame('open', $offer->refresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-07-19 00:01:00')); // Sunday
        $this->artisan('trade-room:expire-open-offers')->assertSuccessful();
        $this->assertSame('cancelled', $offer->refresh()->status);
        $this->assertSame(1_000_000, $buyer->refresh()->walletBalance());
    }

    public function test_telegram_bot_offer_expires_after_two_minutes_and_releases_all_reserves(): void
    {
        config()->set('services.telegram.link_api_token', 'test-bot-token');
        config()->set('logging.default', 'null');
        Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));

        $buyer = User::factory()->create([
            'telegram_chat_id' => '778899',
            'is_vip' => true,
            'membership_level' => 2,
        ]);
        $this->fund($buyer, 1_000);
        $collateral = AssetCollateralRequest::create([
            'user_id' => $buyer->id,
            'asset' => 'gold',
            'quantity' => 10,
            'trade_limit_amount' => 5_000,
            'status' => 'approved',
            'source' => 'test',
        ]);

        $this->withToken('test-bot-token')->postJson('/api/telegram/trade-room/offers/create', [
            'telegram_chat_id' => '778899',
            'asset' => 'gold',
            'side' => 'buy',
            'unit' => 'gram',
            'quantity' => 3,
            'unit_price' => 1_000,
        ])->assertCreated();

        $offer = TradeRoomOffer::firstOrFail();
        $this->assertSame('telegram_bot', $offer->source);
        $this->assertSame(0, $buyer->refresh()->walletBalance());
        $this->assertSame(2_000, (int) $collateral->refresh()->used_amount);

        Carbon::setTestNow(Carbon::parse('2026-07-13 12:01:59'));
        $this->artisan('trade-room:expire-open-offers')->assertSuccessful();
        $this->assertSame('open', $offer->refresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-07-13 12:02:00'));
        $this->withToken('test-bot-token')->postJson('/api/telegram/trade-room/offers', [
            'telegram_chat_id' => '778899',
        ])->assertOk()->assertJsonCount(0, 'offers');

        $this->assertSame('cancelled', $offer->refresh()->status);
        $this->assertSame(1_000, $buyer->refresh()->walletBalance());
        $this->assertSame(0, (int) $collateral->refresh()->used_amount);
    }
}
