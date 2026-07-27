<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeRoomAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_vip_member_cannot_view_or_submit_to_the_trade_room(): void
    {
        $vip = User::factory()->vip()->create();

        $this->actingAs($vip)->get('/trade-room')->assertForbidden();
        $this->actingAs($vip)->post('/trade-room', [
            'metal' => 'gold',
            'side' => 'buy',
            'grams' => 100,
            'price_per_gram' => 1_000,
        ])->assertForbidden();
    }

    public function test_admin_can_view_the_trade_room_without_vip_membership(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/trade-room')->assertOk();
    }
}
