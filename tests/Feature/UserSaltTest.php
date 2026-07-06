<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserPassword;
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

    public function test_registration_uses_custom_salt_before_bcrypt(): void
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
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertTrue(UserPassword::check($user, 'password'));
    }

    public function test_legacy_bcrypt_password_is_upgraded_on_successful_login(): void
    {
        $user = User::factory()->create([
            'phone' => '09120000001',
            'salt' => UserPassword::newSalt(),
            'password' => Hash::make('password'),
        ]);
        $oldHash = $user->password;
        $oldSalt = $user->salt;

        $this->post('/login', [
            'phone' => '09120000001',
            'password' => 'password',
        ])->assertRedirect('/');

        $user->refresh();
        $this->assertNotSame($oldHash, $user->password);
        $this->assertNotSame($oldSalt, $user->salt);
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertTrue(UserPassword::check($user, 'password'));
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
        $this->assertFalse(Hash::check('new-password', $user->password));
        $this->assertTrue(UserPassword::check($user, 'new-password'));
    }
}
