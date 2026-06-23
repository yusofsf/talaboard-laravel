<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\SilverDeliveryRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_logged(): void
    {
        $user = User::factory()->create(['phone' => '09120000000']);

        $this->post('/login', ['phone' => '09120000000', 'password' => 'password'])
            ->assertRedirect('/');

        $log = ActivityLog::where('action', 'login')->where('user_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('auth', $log->category);
    }

    public function test_failed_login_is_logged(): void
    {
        User::factory()->create(['phone' => '09120000000']);

        $this->post('/login', ['phone' => '09120000000', 'password' => 'wrong']);

        $this->assertSame(1, ActivityLog::where('action', 'login_failed')->count());
    }

    public function test_admin_action_is_logged_via_notify_helper(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();

        $this->actingAs($admin)->post('/admin/wallet-credit', [
            'user_id' => $user->id, 'amount' => 1_000_000, 'description' => 'تست',
        ])->assertRedirect();

        $this->assertTrue(ActivityLog::where('category', 'admin')->where('user_id', $admin->id)->exists());
    }

    public function test_admin_can_view_user_trades_page(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 5_000_000, 'type' => 'deposit', 'description' => 'x']);

        $this->actingAs($admin)->get("/admin/users/{$user->id}/trades")
            ->assertOk();
    }

    public function test_non_admin_cannot_view_user_trades_page(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get("/admin/users/{$other->id}/trades")
            ->assertForbidden();
    }

    public function test_delivery_reject_requires_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();
        $delivery = SilverDeliveryRequest::create([
            'user_id' => $user->id, 'metal' => 'gold', 'purity' => '', 'grams' => 5,
            'recipient_name' => 'x', 'phone' => '0912', 'address' => 'y', 'status' => 'pending',
        ]);

        // رد بدون دلیل → خطای اعتبارسنجی
        $this->actingAs($admin)->post("/admin/delivery/{$delivery->id}/update", ['status' => 'rejected'])
            ->assertSessionHasErrors('note');
        $this->assertSame('pending', $delivery->refresh()->status);

        // رد با دلیل → موفق و دلیل ذخیره می‌شود
        $this->actingAs($admin)->post("/admin/delivery/{$delivery->id}/update", ['status' => 'rejected', 'note' => 'مدارک ناقص'])
            ->assertRedirect();
        $delivery->refresh();
        $this->assertSame('rejected', $delivery->status);
        $this->assertSame('مدارک ناقص', $delivery->admin_note);
    }

    public function test_rejected_delivery_request_is_removed_from_the_admin_dashboard_list(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();
        $pending = SilverDeliveryRequest::create([
            'user_id' => $user->id, 'metal' => 'gold', 'purity' => '', 'grams' => 5,
            'recipient_name' => 'x', 'phone' => '0912', 'address' => 'y', 'status' => 'pending',
        ]);
        $rejected = SilverDeliveryRequest::create([
            'user_id' => $user->id, 'metal' => 'gold', 'purity' => '', 'grams' => 3,
            'recipient_name' => 'x', 'phone' => '0912', 'address' => 'y', 'status' => 'rejected', 'admin_note' => 'x',
        ]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->has('deliveryRequests', 1)
            ->where('deliveryRequests.0.id', $pending->id));
    }
}
