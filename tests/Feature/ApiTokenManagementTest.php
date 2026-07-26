<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
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
}
