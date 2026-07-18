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

    public function test_enabled_master_otp_help_resets_a_password_independently_of_sms_otp(): void
    {
        config()->set('sms.enabled', true);
        config()->set('sms.master_otp', '000000');
        config()->set('sms.master_otp_enabled', false);
        config()->set('sms.master_otp_help_enabled', true);

        $user = User::factory()->create([
            'phone' => '09120000000',
            'must_reset_password' => true,
        ]);

        $this->withSession(['reset_phone' => $user->phone])
            ->post('/reset-password', [
                'otp' => '000000',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect('/login');

        $user->refresh();
        $this->assertFalse($user->must_reset_password);
        $this->assertTrue(UserPassword::check($user, 'new-password'));
    }

    public function test_enabled_sms_otp_resets_a_password_without_master_otp_help(): void
    {
        config()->set('sms.master_otp_enabled', true);
        config()->set('sms.master_otp_help_enabled', false);

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
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect('/login');

        $this->assertTrue(UserPassword::check($user->fresh(), 'new-password'));
    }
}
