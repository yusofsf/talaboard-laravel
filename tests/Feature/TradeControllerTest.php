<?php

namespace Tests\Feature;

use App\Models\SilverLedger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeControllerTest extends TestCase
{
    use RefreshDatabase;

    /** قیمت‌های ثابت برای تست — بدون نیاز به API طلالند یا دیتابیس نقره. */
    private function fakePrices(): array
    {
        $this->mock(PriceService::class, function ($mock) {
            $mock->shouldReceive('all')->andReturn([
                'gold'       => ['geram' => 50_000_000, 'mithqal' => 216_590_000],
                'gold_buy'   => ['geram' => 49_000_000, 'mithqal' => 212_257_000],
                'silver'     => ['gram_999' => 400_000, 'mithqal_999' => 1_732_720],
                'silver_buy' => ['gram_999' => 390_000, 'mithqal_999' => 1_689_402],
                'dollar'     => ['price' => 90_000, 'label' => 'دلار آمریکا'],
                'ounce'      => ['gold' => null, 'silver' => null],
                'open'       => [],
                'errors'     => [],
                'updated_at' => '12:00:00',
            ]);
        });

        return [];
    }

    public function test_buy_gold_fails_without_enough_wallet_balance(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/trade/geram', [
            'trade_type' => 'buy',
            'quantity'   => 1,
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, Transaction::count());
    }

    public function test_buy_gold_succeeds_and_debits_wallet(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 60_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $response = $this->actingAs($user)->post('/trade/geram', [
            'trade_type' => 'buy',
            'quantity'   => 1,
        ]);

        $response->assertRedirect(route('history'));
        $this->assertSame(1, Transaction::where('type', 'buy')->where('item', 'geram')->count());
        $this->assertSame(60_000_000 - 50_000_000, $user->refresh()->walletBalance());
    }

    public function test_sell_gold_fails_without_existing_holding(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/trade/geram', [
            'trade_type' => 'sell',
            'quantity'   => 1,
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, Transaction::count());
    }

    public function test_sell_gold_succeeds_after_prior_buy(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 60_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $this->actingAs($user)->post('/trade/geram', ['trade_type' => 'buy', 'quantity' => 1]);

        $response = $this->actingAs($user)->post('/trade/geram', [
            'trade_type' => 'sell',
            'quantity'   => 1,
        ]);

        $response->assertRedirect(route('history'));
        $this->assertSame(1, Transaction::where('type', 'sell')->where('item', 'geram')->count());
        // 60,000,000 - 50,000,000 (خرید) + 49,000,000 (فروش)
        $this->assertSame(60_000_000 - 50_000_000 + 49_000_000, $user->refresh()->walletBalance());
    }

    public function test_sell_gold_fails_when_requesting_more_than_holding(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 60_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $this->actingAs($user)->post('/trade/geram', ['trade_type' => 'buy', 'quantity' => 1]);

        $response = $this->actingAs($user)->post('/trade/geram', [
            'trade_type' => 'sell',
            'quantity'   => 2,
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, Transaction::where('type', 'sell')->count());
    }

    public function test_buy_silver_credits_silver_ledger(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 1_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $this->actingAs($user)->post('/trade/gram_999', ['trade_type' => 'buy', 'quantity' => 2]);

        $this->assertSame(2.0, $user->refresh()->silverBalance('999'));
        $this->assertSame(1, SilverLedger::where('type', 'purchase')->count());
    }

    public function test_sell_silver_fails_without_existing_holding(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/trade/gram_999', [
            'trade_type' => 'sell',
            'quantity'   => 1,
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, Transaction::count());
    }

    public function test_sell_silver_succeeds_and_debits_ledger_credits_wallet(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 3_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $this->actingAs($user)->post('/trade/gram_999', ['trade_type' => 'buy', 'quantity' => 5]);
        $this->actingAs($user)->post('/trade/gram_999', ['trade_type' => 'sell', 'quantity' => 3]);

        $this->assertSame(2.0, $user->refresh()->silverBalance('999'));
        // 3,000,000 - (5*400,000) + (3*390,000)
        $this->assertSame(3_000_000 - 2_000_000 + 1_170_000, $user->walletBalance());
    }

    public function test_unknown_item_redirects_home(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/trade/not-a-real-item');

        $response->assertRedirect('/');
    }

    public function test_buy_mithqal_silver_credits_ledger_in_grams(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 2_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $this->actingAs($user)->post('/trade/mithqal_999', ['trade_type' => 'buy', 'quantity' => 1]);

        // ۱ مثقال = ۴.۳۳۱۸ گرم
        $this->assertEqualsWithDelta(4.3318, $user->refresh()->silverBalance('999'), 0.0001);
    }

    public function test_sell_mithqal_silver_fails_without_enough_gram_holding(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/trade/mithqal_999', [
            'trade_type' => 'sell',
            'quantity'   => 1,
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, Transaction::count());
    }

    public function test_buy_mithqal_gold_credits_gold_ledger_in_grams(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 300_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $this->actingAs($user)->post('/trade/mithqal', ['trade_type' => 'buy', 'quantity' => 1]);

        $this->assertEqualsWithDelta(4.3318, $user->refresh()->goldBalance(), 0.0001);
    }

    public function test_sell_mithqal_gold_fails_without_enough_gram_holding(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/trade/mithqal', [
            'trade_type' => 'sell',
            'quantity'   => 1,
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, Transaction::count());
    }
}
