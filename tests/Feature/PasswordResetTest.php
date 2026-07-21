<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\OtpToken;
use App\Support\UserPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_sms_otp_resets_a_password_without_master_otp_help(): void
    {
        config()->set('sms.otp_enabled', true);

        $user = User::factory()->create(['phone' => '09120000001']);
        OtpToken::create([
            'phone' => $user->phone,
            'otp' => '123456',
            'purpose' => 'reset',
            'expires_at' => now()->addMinutes(2),
        ]);

        $this->withSession(['reset_phone' => $user->phone])
            ->post('/reset-password', [
                'otp' => '123456',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect('/login');

        $this->assertTrue(UserPassword::check($user->fresh(), 'NewPassword123!'));
    }
}
