<?php

namespace Tests\Feature;

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

        $buyer = User::factory()->vip()->create();
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

        $buyer = User::factory()->vip()->create();
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
}
