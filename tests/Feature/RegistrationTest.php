<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_existing_user_gets_a_clear_registration_error_after_phone_normalization(): void
    {
        User::factory()->create(['phone' => '09123456789']);

        $this->from('/register')->post('/register', [
            'name' => 'کاربر تکراری',
            'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/register')
            ->assertSessionHasErrors([
                'phone' => 'کاربر قبلاً ثبت‌نام کرده است.',
            ]);

        $this->assertGuest();
        $this->assertSame(1, User::where('phone', '09123456789')->count());
    }
}
