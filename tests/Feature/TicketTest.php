<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_ticket_and_admins_are_notified(): void
    {
        $user  = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)->post('/tickets', [
            'subject' => 'مشکل در واریز',
            'message' => 'سلام، کیف پولم شارژ نشد.',
        ])->assertRedirect();

        $ticket = Ticket::first();
        $this->assertSame('مشکل در واریز', $ticket->subject);
        $this->assertSame('open', $ticket->status);
        $this->assertSame(1, $ticket->messages()->count());

        $this->assertTrue(Notification::where('user_id', $admin->id)->exists());
    }

    public function test_admin_reply_notifies_user_without_admin_name_and_other_admins_with_admin_name(): void
    {
        $user        = User::factory()->create();
        $actingAdmin = User::factory()->admin()->create(['name' => 'مدیر اول']);
        $otherAdmin  = User::factory()->admin()->create(['name' => 'مدیر دوم']);

        $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'سوال درباره کارمزد', 'status' => 'open']);
        $ticket->messages()->create(['user_id' => $user->id, 'is_admin' => false, 'message' => 'کارمزد چقدر است؟', 'created_at' => now()]);

        $this->actingAs($actingAdmin)->post("/admin/tickets/{$ticket->id}/reply", [
            'message' => 'کارمزد صفر است.',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('answered', $ticket->status);

        $userNotif = Notification::where('user_id', $user->id)->first();
        $this->assertNotNull($userNotif);
        $this->assertStringNotContainsString('مدیر اول', $userNotif->body);
        $this->assertStringContainsString('کارمزد صفر است', $userNotif->body);

        $adminNotif = Notification::where('user_id', $otherAdmin->id)->first();
        $this->assertNotNull($adminNotif);
        $this->assertStringContainsString('مدیر اول', $adminNotif->body);
    }

    public function test_user_reply_reopens_ticket_and_notifies_admins(): void
    {
        $user  = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'پیگیری', 'status' => 'answered']);
        $ticket->messages()->create(['user_id' => $admin->id, 'is_admin' => true, 'message' => 'بررسی شد.', 'created_at' => now()]);

        $this->actingAs($user)->post("/tickets/{$ticket->id}/reply", [
            'message' => 'هنوز حل نشده.',
        ])->assertRedirect();

        $this->assertSame('open', $ticket->refresh()->status);
        $this->assertSame(2, $ticket->messages()->count());
        $this->assertTrue(Notification::where('user_id', $admin->id)->exists());
    }

    public function test_user_can_mark_ticket_resolved_and_then_no_one_can_reply(): void
    {
        $user  = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'حل‌شدنی', 'status' => 'answered']);

        $this->actingAs($user)->post("/tickets/{$ticket->id}/resolve")->assertRedirect();

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertTrue(Notification::where('user_id', $admin->id)->exists());

        // کاربر دیگر نمی‌تواند پاسخ بدهد
        $this->actingAs($user)->post("/tickets/{$ticket->id}/reply", ['message' => 'یه چیز دیگه'])
            ->assertSessionHas('error');

        // ادمین هم دیگر نمی‌تواند پاسخ بدهد
        $this->actingAs($admin)->post("/admin/tickets/{$ticket->id}/reply", ['message' => 'باشه'])
            ->assertSessionHas('error');

        $this->assertSame(1, $ticket->messages()->count());
    }

    public function test_closed_ticket_rejects_user_reply(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'بسته', 'status' => 'closed']);

        $this->actingAs($user)->post("/tickets/{$ticket->id}/reply", ['message' => 'سلام'])
            ->assertSessionHas('error');

        $this->assertSame(0, $ticket->messages()->count());
    }

    public function test_user_cannot_view_another_users_ticket(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ticket = Ticket::create(['user_id' => $owner->id, 'subject' => 'خصوصی', 'status' => 'open']);

        $this->actingAs($other)->get("/tickets/{$ticket->id}")->assertNotFound();
    }

    public function test_non_admin_cannot_access_admin_ticket_routes(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'x', 'status' => 'open']);

        $this->actingAs($user)->get("/admin/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($user)->post("/admin/tickets/{$ticket->id}/reply", ['message' => 'x'])->assertForbidden();
    }

    public function test_admin_can_close_a_ticket(): void
    {
        $user  = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'حل‌شده', 'status' => 'answered']);

        $this->actingAs($admin)->post("/admin/tickets/{$ticket->id}/close")->assertRedirect();

        $this->assertSame('closed', $ticket->refresh()->status);
        $this->assertTrue(Notification::where('user_id', $user->id)->exists());
    }
}
