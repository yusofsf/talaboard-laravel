<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSaltTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_get_a_random_salt_that_is_hidden_from_arrays(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->salt);
        $this->assertSame(32, strlen($user->salt));
        $this->assertArrayNotHasKey('salt', $user->toArray());
    }

    public function test_registration_persists_salt_and_keeps_bcrypt_login_working(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'phone' => '09120000000',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/');

        $user = User::where('phone', '09120000000')->first();
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->salt);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_password_update_rotates_salt(): void
    {
        $user = User::factory()->create();
        $oldSalt = $user->salt;

        $this->actingAs($user)->post('/profile/password', [
            'old_password' => 'password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ])->assertRedirect();

        $this->assertNotSame($oldSalt, $user->refresh()->salt);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }
}
