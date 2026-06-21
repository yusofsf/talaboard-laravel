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
                'gold'       => ['geram' => 50_000_000, 'mithqal' => 216_590_000, 'bahar' => 500_000_000],
                'gold_buy'   => ['geram' => 49_000_000, 'mithqal' => 212_257_000, 'bahar' => 490_000_000],
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
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 600_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $response = $this->actingAs($user)->post('/trade/geram', [
            'trade_type' => 'buy',
            'quantity'   => 10,
        ]);

        $response->assertRedirect(route('history'));
        $this->assertSame(1, Transaction::where('type', 'buy')->where('item', 'geram')->count());
        $this->assertSame(600_000_000 - 500_000_000, $user->refresh()->walletBalance());
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
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 600_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $this->actingAs($user)->post('/trade/geram', ['trade_type' => 'buy', 'quantity' => 10]);

        $response = $this->actingAs($user)->post('/trade/geram', [
            'trade_type' => 'sell',
            'quantity'   => 10,
        ]);

        $response->assertRedirect(route('history'));
        $this->assertSame(1, Transaction::where('type', 'sell')->where('item', 'geram')->count());
        // 600,000,000 - 500,000,000 (خرید) + 490,000,000 (فروش)
        $this->assertSame(600_000_000 - 500_000_000 + 490_000_000, $user->refresh()->walletBalance());
    }

    public function test_sell_gold_fails_when_requesting_more_than_holding(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 600_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $this->actingAs($user)->post('/trade/geram', ['trade_type' => 'buy', 'quantity' => 10]);

        $response = $this->actingAs($user)->post('/trade/geram', [
            'trade_type' => 'sell',
            'quantity'   => 15,
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, Transaction::where('type', 'sell')->count());
    }

    public function test_buy_silver_credits_silver_ledger(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 5_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $this->actingAs($user)->post('/trade/gram_999', ['trade_type' => 'buy', 'quantity' => 10]);

        $this->assertSame(10.0, $user->refresh()->silverBalance('999'));
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
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 7_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $this->actingAs($user)->post('/trade/gram_999', ['trade_type' => 'buy', 'quantity' => 15]);
        $this->actingAs($user)->post('/trade/gram_999', ['trade_type' => 'sell', 'quantity' => 12]);

        $this->assertSame(3.0, $user->refresh()->silverBalance('999'));
        // 7,000,000 - (15*400,000) + (12*390,000)
        $this->assertSame(7_000_000 - 6_000_000 + 4_680_000, $user->walletBalance());
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
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 6_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $this->actingAs($user)->post('/trade/mithqal_999', ['trade_type' => 'buy', 'quantity' => 3]);

        // ۳ مثقال = ۱۲.۹۹۵۴ گرم (بالاتر از حداقل ۱۰ گرم)
        $this->assertEqualsWithDelta(3 * 4.3318, $user->refresh()->silverBalance('999'), 0.0001);
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
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 700_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $this->actingAs($user)->post('/trade/mithqal', ['trade_type' => 'buy', 'quantity' => 3]);

        $this->assertEqualsWithDelta(3 * 4.3318, $user->refresh()->goldBalance(), 0.0001);
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

    public function test_buy_gold_fails_below_minimum_10_grams(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 600_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $response = $this->actingAs($user)->post('/trade/geram', ['trade_type' => 'buy', 'quantity' => 5]);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, Transaction::count());
    }

    public function test_buy_silver_fails_below_minimum_10_grams(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 5_000_000, 'type' => 'deposit', 'description' => 'charge']);

        $response = $this->actingAs($user)->post('/trade/gram_999', ['trade_type' => 'buy', 'quantity' => 5]);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, Transaction::count());
    }

    public function test_buy_coin_below_10_grams_equivalent_still_succeeds(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 600_000_000, 'type' => 'deposit', 'description' => 'charge']);

        // سکه‌ها مشمول حداقل ۱۰ گرم نیستند
        $response = $this->actingAs($user)->post('/trade/bahar', ['trade_type' => 'buy', 'quantity' => 1]);

        $response->assertRedirect(route('history'));
        $this->assertSame(1, Transaction::where('item', 'bahar')->count());
    }
}
