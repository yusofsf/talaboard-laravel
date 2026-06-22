<?php

namespace Tests\Unit;

use App\Services\SmsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    public function test_sms_is_not_sent_when_disabled_via_config_even_with_an_api_key(): void
    {
        config(['sms.enabled' => false, 'sms.kavenegar_api_key' => 'fake-key', 'sms.kavenegar_sender' => '1000']);
        Http::fake();

        $result = (new SmsService())->send('09120000000', 'test message');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_sms_is_sent_when_enabled_with_an_api_key(): void
    {
        config(['sms.enabled' => true, 'sms.kavenegar_api_key' => 'fake-key', 'sms.kavenegar_sender' => '1000']);
        Http::fake(['*' => Http::response(['return' => ['status' => 200]], 200)]);

        $result = (new SmsService())->send('09120000000', 'test message');

        $this->assertTrue($result);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sms/send.json'));
    }
}
