<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_a_users_new_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['must_reset_password' => true]);

        $this->actingAs($admin)
            ->post("/admin/users/{$user->id}/password", [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue(UserPassword::check($user, 'NewPassword123!'));
        $this->assertFalse($user->must_reset_password);
        $this->assertNull($user->legacy_password_hash);
    }
}
