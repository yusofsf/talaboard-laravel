<?php

namespace Tests\Unit;

use App\Services\PriceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use Tests\TestCase;

class PriceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_dollar_falls_back_to_tgju_and_converts_rial_to_toman(): void
    {
        Http::fake(function ($request) {
            if ($request->url() === 'https://alanchand.com/') {
                return Http::response('', 504);
            }

            return Http::response('<span data-col="info.last_trade.PDrCotVal">۱٬۵۲۴٬۸۰۰</span>');
        });

        $errors = [];
        $result = $this->invokePriceFetcher('fetchDollar', $errors);

        $this->assertSame(152480, $result['price']);
        $this->assertSame([], $errors);
    }

    public function test_gold_ounce_uses_tgju_before_yahoo(): void
    {
        Http::fake(function ($request) {
            if ($request->url() === 'https://alanchand.com/gold-price') {
                return Http::response('', 504);
            }

            return Http::response(
                '<span class="value" data-col="info.last_trade.PDrCotVal">4,797.33</span>'
            );
        });

        $errors = [];
        $result = $this->invokePriceFetcher('fetchGoldOunce', $errors);

        $this->assertSame(4797.33, $result);
        $this->assertSame([], $errors);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'finance.yahoo.com'));
    }

    public function test_gold_ounce_failure_is_logged_but_not_returned_as_a_user_error(): void
    {
        Http::fake(fn () => Http::response('', 503));
        Log::spy();

        $errors = [];
        $result = $this->invokePriceFetcher('fetchGoldOunce', $errors);

        $this->assertNull($result);
        $this->assertSame([], $errors);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('PriceService gold ounce unavailable after all sources failed');
    }

    public function test_public_payload_hides_ounce_and_curl_errors(): void
    {
        $payload = PriceService::publicPayload([
            'errors' => [
                'انس طلا: دریافت نشد',
                'انس نقره: دریافت نشد',
                'طلا: cURL error 28: Connection timed out',
                'دلار: قیمت دریافت نشد',
            ],
        ]);

        $this->assertSame(['دلار: قیمت دریافت نشد'], $payload['errors']);
    }

    private function invokePriceFetcher(string $methodName, array &$errors): mixed
    {
        $method = new ReflectionMethod(PriceService::class, $methodName);

        return $method->invokeArgs(new PriceService, [&$errors]);
    }
}
