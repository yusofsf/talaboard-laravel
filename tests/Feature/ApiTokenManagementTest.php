<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\CartItem;
use App\Models\GoldLedger;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_issue_a_read_only_token_without_a_registered_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/api-tokens', [
            'owner_type' => 'guest',
            'client_name' => 'سرویس حسابداری',
            'name' => 'قیمت‌خوان',
            'abilities' => ['prices:read', 'trades:read'],
        ])->assertRedirect()
            ->assertSessionHas('issued_token');

        $this->assertDatabaseHas('api_tokens', [
            'user_id' => null,
            'client_name' => 'سرویس حسابداری',
            'name' => 'قیمت‌خوان',
        ]);
    }

    public function test_guest_token_cannot_receive_user_dependent_abilities(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/api-tokens', [
            'owner_type' => 'guest',
            'client_name' => 'سرویس مهمان',
            'name' => 'توکن نامعتبر',
            'abilities' => ['trades:create'],
        ])->assertSessionHasErrors('abilities');

        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_guest_token_can_read_trade_room_offers(): void
    {
        [, $plainToken] = ApiToken::issue(
            null,
            'سرویس قیمت',
            'توکن خواندن',
            ['trades:read'],
        );

        $this->withToken($plainToken)
            ->getJson('/api/v1/trade-room/offers')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_token_scope_only_allows_its_matching_endpoint(): void
    {
        $user = User::factory()->create();
        [, $plainToken] = ApiToken::issue($user, '', 'کیف پول', ['wallet:read']);

        $this->withToken($plainToken)->getJson('/api/v1/wallet')->assertOk()
            ->assertJsonStructure(['data' => ['balance', 'transactions']]);
        $this->withToken($plainToken)->getJson('/api/v1/profile')->assertForbidden();
    }

    public function test_api_buy_offer_reserves_wallet_funds_before_it_is_opened(): void
    {
        $user = User::factory()->vip()->create();
        WalletTransaction::create([
            'user_id' => $user->id,
            'amount' => 500_000,
            'type' => 'deposit',
            'description' => 'seed',
        ]);
        [, $plainToken] = ApiToken::issue($user, '', 'trader', ['trades:create']);

        $this->withToken($plainToken)->postJson('/api/v1/trade-room/offers', [
            'metal' => 'gold',
            'side' => 'buy',
            'grams' => 100,
            'price_per_gram' => 1_000,
        ])->assertCreated()
            ->assertJsonPath('data.wallet_reserved_amount', 100_000);

        $this->assertSame(400_000, $user->refresh()->walletBalance());
        $this->assertDatabaseHas('trade_room_offers', [
            'user_id' => $user->id,
            'source' => 'api_token',
            'status' => 'open',
            'wallet_reserved_amount' => 100_000,
        ]);
    }

    public function test_api_sell_offer_reserves_metal_before_it_is_opened(): void
    {
        $user = User::factory()->vip()->create();
        GoldLedger::create([
            'user_id' => $user->id,
            'grams' => 10,
            'type' => 'admin_adjust',
            'description' => 'seed',
        ]);
        [, $plainToken] = ApiToken::issue($user, '', 'trader', ['trades:create']);

        $this->withToken($plainToken)->postJson('/api/v1/trade-room/offers', [
            'metal' => 'gold',
            'side' => 'sell',
            'grams' => 4,
            'price_per_gram' => 1_000,
        ])->assertCreated();

        $this->assertSame(6.0, $user->refresh()->goldBalance());
        $this->assertDatabaseHas('gold_ledger', [
            'user_id' => $user->id,
            'grams' => -4,
            'type' => 'offer_escrow',
        ]);
    }

    public function test_shop_order_api_ignores_a_forged_client_price(): void
    {
        $user = User::factory()->create();
        [, $plainToken] = ApiToken::issue($user, '', 'trader', ['trades:create']);
        $this->mock(PriceService::class, function ($mock) {
            $mock->shouldReceive('all')->once()->andReturn([
                'gold' => ['geram' => 75_000_000],
                'gold_buy' => ['geram' => 74_000_000],
                'silver' => [],
                'silver_buy' => [],
            ]);
        });

        $this->withToken($plainToken)->postJson('/api/v1/shop/orders', [
            'trade_type' => 'buy',
            'item' => 'geram',
            'quantity' => 10,
            'price_per_unit' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.total', 750_000_000);

        $cart = CartItem::firstOrFail();
        $this->assertSame(75_000_000, $cart->price_per_unit);
        $this->assertSame(750_000_000, $cart->total);
    }
}
