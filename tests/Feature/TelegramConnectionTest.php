<?php

namespace Tests\Feature;

use App\Models\TelegramLinkCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_can_connect_a_site_user_with_a_one_time_code(): void
    {
        config()->set('services.telegram.link_api_token', 'test-bot-token');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/telegram-link')->assertRedirect();
        $code = $this->app['session.store']->get('telegram_link_code');

        $this->assertMatchesRegularExpression('/^\\d{6}$/', $code);

        $this->withToken('test-bot-token')
            ->postJson('/api/v1/telegram/connect', [
                'code' => $code,
                'telegram_user_id' => 123456789,
                'telegram_chat_id' => 123456789,
                'telegram_username' => 'username',
            ])
            ->assertOk()
            ->assertJsonPath('linked', true)
            ->assertJsonPath('user_id', $user->id);

        $this->assertDatabaseHas('telegram_connections', [
            'user_id' => $user->id,
            'telegram_user_id' => '123456789',
            'telegram_chat_id' => '123456789',
            'telegram_username' => 'username',
        ]);
        $this->assertNotNull(TelegramLinkCode::first()->used_at);
        $this->assertSame('123456789', $user->fresh()->telegram_chat_id);
    }

    public function test_connection_code_cannot_be_reused(): void
    {
        config()->set('services.telegram.link_api_token', 'test-bot-token');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/profile/telegram-link');
        $code = $this->app['session.store']->get('telegram_link_code');

        $payload = ['code' => $code, 'telegram_user_id' => 123456789, 'telegram_chat_id' => 123456789];
        $this->withToken('test-bot-token')->postJson('/api/v1/telegram/connect', $payload)->assertOk();
        $this->withToken('test-bot-token')->postJson('/api/v1/telegram/connect', $payload)->assertUnprocessable();
    }
}
