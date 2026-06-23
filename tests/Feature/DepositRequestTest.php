<?php

namespace Tests\Feature;

use App\Models\DepositRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_a_deposit_and_admins_are_notified(): void
    {
        $user  = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)->post('/wallet/deposit', [
            'amount' => 500000,
            'note'   => 'پیگیری ۱۲۳۴',
        ])->assertRedirect();

        $deposit = DepositRequest::first();
        $this->assertSame(500000, $deposit->amount);
        $this->assertSame('pending', $deposit->status);
        $this->assertSame(0, $user->refresh()->walletBalance());

        $this->assertTrue(Notification::where('user_id', $admin->id)->exists());
    }

    public function test_admin_approving_a_deposit_credits_the_wallet_and_notifies_correctly(): void
    {
        $user        = User::factory()->create();
        $actingAdmin = User::factory()->admin()->create(['name' => 'مدیر اول']);
        $otherAdmin  = User::factory()->admin()->create(['name' => 'مدیر دوم']);
        $deposit = DepositRequest::create(['user_id' => $user->id, 'amount' => 300000, 'status' => 'pending']);

        $this->actingAs($actingAdmin)->post("/admin/deposits/{$deposit->id}/approve", [
            'note' => 'رسید بررسی شد',
        ])->assertRedirect();

        $this->assertSame('approved', $deposit->refresh()->status);
        $this->assertSame(300000, $user->refresh()->walletBalance());

        $userNotif = Notification::where('user_id', $user->id)->first();
        $this->assertNotNull($userNotif);
        $this->assertStringNotContainsString('مدیر اول', $userNotif->body);
        $this->assertStringContainsString('توسط ادمین', $userNotif->body);

        $adminNotif = Notification::where('user_id', $otherAdmin->id)->first();
        $this->assertNotNull($adminNotif);
        $this->assertStringContainsString('مدیر اول', $adminNotif->body);
    }

    public function test_admin_rejecting_a_deposit_requires_a_reason_and_does_not_credit_wallet(): void
    {
        $user  = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $deposit = DepositRequest::create(['user_id' => $user->id, 'amount' => 200000, 'status' => 'pending']);

        $this->actingAs($admin)->post("/admin/deposits/{$deposit->id}/reject", [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post("/admin/deposits/{$deposit->id}/reject", ['reason' => 'رسید نامعتبر'])
            ->assertRedirect();

        $deposit->refresh();
        $this->assertSame('rejected', $deposit->status);
        $this->assertSame('رسید نامعتبر', $deposit->admin_note);
        $this->assertSame(0, $user->refresh()->walletBalance());
    }
}
