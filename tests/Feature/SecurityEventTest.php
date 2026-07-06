<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspicious_guest_input_is_logged_and_admins_are_notified(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withHeader('User-Agent', 'SecurityTest/1.0')
            ->post('/register', [
                'name' => '<script>alert(1)</script>',
                'phone' => '09120000000',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ])->assertSessionHasErrors('name');

        $event = SecurityEvent::first();
        $this->assertNotNull($event);
        $this->assertSame('xss_attempt', $event->event_type);
        $this->assertNull($event->user_id);
        $this->assertSame('/register', $event->path);
        $this->assertSame('POST', $event->method);
        $this->assertSame('SecurityTest/1.0', $event->user_agent);
        $this->assertSame('[masked]', $event->payload['password']);
        $this->assertSame('[masked]', $event->payload['password_confirmation']);
        $this->assertSame('name', $event->matched_fields[0]['field']);
        $this->assertStringContainsString('<script>', $event->matched_fields[0]['sample']);

        $this->assertTrue(Notification::where('user_id', $admin->id)
            ->where('title', 'هشدار امنیتی: ورودی مشکوک')
            ->exists());
    }

    public function test_suspicious_authenticated_input_is_linked_to_the_user(): void
    {
        $user = User::factory()->create(['phone' => '09120000001']);

        $this->actingAs($user)->post('/profile/info', [
            'name' => '<img src=x onerror=alert(1)>',
            'phone' => '09120000001',
        ])->assertSessionHasErrors('name');

        $event = SecurityEvent::first();
        $this->assertNotNull($event);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('xss_attempt', $event->event_type);
        $this->assertSame('/profile/info', $event->path);
    }
}
