<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_any_page_updates_last_seen_at(): void
    {
        $user = User::factory()->create(['last_seen_at' => null]);

        $this->actingAs($user)->get('/');

        $this->assertNotNull($user->refresh()->last_seen_at);
    }

    public function test_admin_sees_recently_active_users_in_online_list(): void
    {
        $admin  = User::factory()->admin()->create(['last_seen_at' => now()]);
        $stale  = User::factory()->create(['last_seen_at' => now()->subMinutes(30)]);

        $response = $this->actingAs($admin)->get('/admin/online-users');

        $response->assertInertia(fn ($page) => $page
            ->has('users', 1)
            ->where('users.0.id', $admin->id));
    }

    public function test_non_admin_cannot_view_online_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/online-users')->assertForbidden();
    }
}
