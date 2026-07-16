<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationListTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_notification_read_removes_it_from_the_users_list(): void
    {
        $user = User::factory()->create();
        $notif = Notification::create(['title' => 'تست', 'type' => 'info', 'user_id' => null]);

        $this->actingAs($user)->get('/notifications')
            ->assertInertia(fn ($page) => $page->has('notifications', 1));

        $this->actingAs($user)->post("/notifications/read/{$notif->id}")->assertRedirect();

        $this->actingAs($user)->get('/notifications')
            ->assertInertia(fn ($page) => $page->has('notifications', 0));
    }

    public function test_a_broadcast_notification_still_shows_for_other_users_after_one_reads_it(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $notif = Notification::create(['title' => 'همگانی', 'type' => 'info', 'user_id' => null]);

        $this->actingAs($a)->post("/notifications/read/{$notif->id}")->assertRedirect();

        $this->actingAs($b)->get('/notifications')
            ->assertInertia(fn ($page) => $page->has('notifications', 1));
    }

    public function test_regular_users_only_see_their_own_and_broadcast_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Notification::create(['title' => 'broadcast', 'type' => 'info', 'user_id' => null]);
        Notification::create(['title' => 'mine', 'type' => 'info', 'user_id' => $user->id]);
        $private = Notification::create(['title' => 'other private', 'type' => 'info', 'user_id' => $other->id]);

        $this->actingAs($user)->get('/notifications')
            ->assertInertia(fn ($page) => $page
                ->has('notifications', 2)
                ->where('notifications.0.title', 'mine')
                ->where('notifications.1.title', 'broadcast'));

        $this->actingAs($user)->post("/notifications/read/{$private->id}")->assertNotFound();
        $this->assertFalse(NotificationRead::where('notification_id', $private->id)->where('user_id', $user->id)->exists());
    }

    public function test_admins_can_see_all_notifications(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Notification::create(['title' => 'broadcast', 'type' => 'info', 'user_id' => null]);
        Notification::create(['title' => 'user private', 'type' => 'info', 'user_id' => $user->id]);

        $this->actingAs($admin)->get('/notifications')
            ->assertInertia(fn ($page) => $page->has('notifications', 2));

        $this->assertSame(2, $admin->unreadCount());
    }
}
