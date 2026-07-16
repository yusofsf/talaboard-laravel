<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_a_sent_notification(): void
    {
        $admin = User::factory()->admin()->create();
        $notif = Notification::create(['title' => 'قدیمی', 'body' => 'متن قدیمی', 'type' => 'info', 'user_id' => null]);

        $this->actingAs($admin)->post("/admin/notify/{$notif->id}/update", [
            'title' => 'جدید', 'body' => 'متن جدید', 'type' => 'promo',
        ])->assertRedirect();

        $notif->refresh();
        $this->assertSame('جدید', $notif->title);
        $this->assertSame('متن جدید', $notif->body);
        $this->assertSame('promo', $notif->type);
    }

    public function test_admin_can_send_a_notification_to_one_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->post('/admin/notify', [
            'title' => 'targeted notification',
            'body' => 'body',
            'type' => 'info',
            'target' => (string) $target->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notifications', [
            'title' => 'targeted notification',
            'user_id' => $target->id,
        ]);
    }

    public function test_non_admin_cannot_edit_a_notification(): void
    {
        $user = User::factory()->create();
        $notif = Notification::create(['title' => 'x', 'type' => 'info', 'user_id' => null]);

        $this->actingAs($user)->post("/admin/notify/{$notif->id}/update", [
            'title' => 'y', 'type' => 'info',
        ])->assertForbidden();

        $this->assertSame('x', $notif->refresh()->title);
    }

    public function test_dashboard_reports_read_count_for_a_notification(): void
    {
        $admin = User::factory()->admin()->create();
        $reader = User::factory()->create();
        $notif = Notification::create(['title' => 'x', 'type' => 'info', 'user_id' => $reader->id]);
        NotificationRead::create(['notification_id' => $notif->id, 'user_id' => $reader->id, 'read_at' => now()]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->where('notifs.0.read_count', 1)
            ->where('notifs.0.target_count', 1));
    }
}
