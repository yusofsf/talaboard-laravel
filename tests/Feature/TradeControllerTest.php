<?php

namespace Tests\Feature;

use App\Models\CartItem;
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

    private function fakePrices(): void
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
    }

    private function chargeWallet(User $user, int $amount): void
    {
        WalletTransaction::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'deposit',
            'description' => 'charge',
        ]);
    }

    private function addToCart(User $user, string $item, string $type, float $quantity)
    {
        return $this->actingAs($user)->post("/trade/{$item}", [
            'trade_type' => $type,
            'quantity' => $quantity,
        ]);
    }

    private function checkout(User $user)
    {
        return $this->actingAs($user)->post('/cart/checkout');
    }

    public function test_trade_page_is_public_and_indexable(): void
    {
        $this->fakePrices();

        $response = $this->get('/trade/mithqal');

        $response->assertOk();
        $response->assertSee('index, follow, max-image-preview:large', false);
        $response->assertSee('https://metalsp.ir/trade/mithqal', false);
    }

    public function test_buy_gold_adds_to_cart_without_creating_transaction(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        $this->chargeWallet($user, 600_000_000);

        $response = $this->addToCart($user, 'geram', 'buy', 10);

        $response->assertRedirect(route('cart'));
        $this->assertSame(1, CartItem::where('user_id', $user->id)->where('item', 'geram')->count());
        $this->assertSame(0, Transaction::count());
        $this->assertSame(600_000_000, $user->refresh()->walletBalance());
    }

    public function test_checkout_gold_buy_debits_wallet_and_clears_cart(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        $this->chargeWallet($user, 600_000_000);

        $this->addToCart($user, 'geram', 'buy', 10);
        $response = $this->checkout($user);

        $response->assertRedirect(route('history'));
        $this->assertSame(1, Transaction::where('type', 'buy')->where('item', 'geram')->count());
        $this->assertSame(100_000_000, $user->refresh()->walletBalance());
        $this->assertSame(0, CartItem::where('user_id', $user->id)->count());
    }

    public function test_checkout_buy_redirects_to_wallet_without_enough_balance(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();

        $this->addToCart($user, 'geram', 'buy', 10);
        $response = $this->checkout($user);

        $response->assertRedirect(route('wallet'));
        $response->assertSessionHas('error');
        $this->assertSame(0, Transaction::count());
        $this->assertSame(1, CartItem::where('user_id', $user->id)->count());
    }

    public function test_sell_gold_fails_without_existing_holding(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();

        $response = $this->addToCart($user, 'geram', 'sell', 1);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, CartItem::count());
        $this->assertSame(0, Transaction::count());
    }

    public function test_sell_gold_succeeds_after_prior_buy_checkout(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        $this->chargeWallet($user, 600_000_000);

        $this->addToCart($user, 'geram', 'buy', 10);
        $this->checkout($user);
        $this->addToCart($user, 'geram', 'sell', 10);
        $response = $this->checkout($user);

        $response->assertRedirect(route('history'));
        $this->assertSame(1, Transaction::where('type', 'sell')->where('item', 'geram')->count());
        $this->assertSame(590_000_000, $user->refresh()->walletBalance());
    }

    public function test_buy_silver_checkout_credits_silver_ledger(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        $this->chargeWallet($user, 5_000_000);

        $this->addToCart($user, 'gram_999', 'buy', 10);
        $this->checkout($user);

        $this->assertSame(10.0, $user->refresh()->silverBalance('999'));
        $this->assertSame(1, SilverLedger::where('type', 'purchase')->count());
    }

    public function test_sell_silver_checkout_debits_ledger_and_credits_wallet(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        $this->chargeWallet($user, 7_000_000);

        $this->addToCart($user, 'gram_999', 'buy', 15);
        $this->checkout($user);
        $this->addToCart($user, 'gram_999', 'sell', 12);
        $this->checkout($user);

        $this->assertSame(3.0, $user->refresh()->silverBalance('999'));
        $this->assertSame(5_680_000, $user->walletBalance());
    }

    public function test_unknown_item_redirects_home(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/trade/not-a-real-item');

        $response->assertRedirect('/');
    }

    public function test_buy_mithqal_silver_checkout_credits_ledger_in_grams(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        $this->chargeWallet($user, 6_000_000);

        $this->addToCart($user, 'mithqal_999', 'buy', 3);
        $this->checkout($user);

        $this->assertEqualsWithDelta(3 * 4.3318, $user->refresh()->silverBalance('999'), 0.0001);
    }

    public function test_buy_mithqal_gold_checkout_credits_gold_ledger_in_grams(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        $this->chargeWallet($user, 700_000_000);

        $this->addToCart($user, 'mithqal', 'buy', 3);
        $this->checkout($user);

        $this->assertEqualsWithDelta(3 * 4.3318, $user->refresh()->goldBalance(), 0.0001);
    }

    public function test_buy_gold_fails_below_minimum_10_grams(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        $this->chargeWallet($user, 600_000_000);

        $response = $this->addToCart($user, 'geram', 'buy', 5);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, CartItem::count());
        $this->assertSame(0, Transaction::count());
    }

    public function test_buy_silver_fails_below_minimum_10_grams(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        $this->chargeWallet($user, 5_000_000);

        $response = $this->addToCart($user, 'gram_999', 'buy', 5);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, CartItem::count());
        $this->assertSame(0, Transaction::count());
    }

    public function test_buy_coin_below_10_grams_equivalent_still_goes_through_cart(): void
    {
        $this->fakePrices();
        $user = User::factory()->create();
        $this->chargeWallet($user, 600_000_000);

        $response = $this->addToCart($user, 'bahar', 'buy', 1);

        $response->assertRedirect(route('cart'));
        $this->assertSame(1, CartItem::where('item', 'bahar')->count());

        $this->checkout($user)->assertRedirect(route('history'));
        $this->assertSame(1, Transaction::where('item', 'bahar')->count());
    }

    public function test_empty_cart_checkout_returns_error(): void
    {
        $user = User::factory()->create();

        $response = $this->checkout($user);

        $response->assertSessionHas('error');
    }
}
