<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNameValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_html_in_name(): void
    {
        $this->post('/register', [
            'name' => '<script>alert(1)</script>',
            'phone' => '09120000000',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('users', ['phone' => '09120000000']);
    }

    public function test_profile_update_rejects_html_in_name(): void
    {
        $user = User::factory()->create(['phone' => '09120000001']);

        $this->actingAs($user)->post('/profile/info', [
            'name' => '<b>bad</b>',
            'phone' => '09120000001',
        ])->assertSessionHasErrors('name');
    }

    public function test_admin_user_update_rejects_html_in_name(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['phone' => '09120000002']);

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => '<img src=x onerror=alert(1)>',
            'phone' => '09120000002',
        ])->assertSessionHasErrors('name');
    }
}
