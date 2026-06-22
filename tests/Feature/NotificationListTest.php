<?php

namespace Tests\Feature;

use App\Models\Notification;
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
}
