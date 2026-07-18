<?php

namespace Tests\Feature;

use App\Models\GoldLedger;
use App\Models\InventoryIncreaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryIncreaseRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_a_gold_inventory_increase(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/inventory-increase-requests', [
            'metal' => 'gold',
            'grams' => 12.5,
            'note' => 'افزایش موجودی خریداری‌شده',
        ])->assertRedirect();

        $this->assertDatabaseHas('inventory_increase_requests', [
            'user_id' => $user->id,
            'metal' => 'gold',
            'purity' => '',
            'status' => 'pending',
        ]);
        $this->assertSame(0, GoldLedger::count());
    }

    public function test_admin_approval_credits_the_requested_gold_once(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $request = InventoryIncreaseRequest::create([
            'user_id' => $user->id,
            'metal' => 'gold',
            'purity' => '',
            'grams' => 7.25,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post("/admin/inventory-increase-requests/{$request->id}/approve", ['note' => 'تأیید شد'])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame(7.25, (float) $user->fresh()->goldBalance());
        $this->assertDatabaseHas('gold_ledger', [
            'user_id' => $user->id,
            'type' => 'inventory_increase',
            'reference_id' => $request->id,
        ]);
    }
}
