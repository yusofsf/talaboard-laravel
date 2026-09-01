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

    public function test_gold_ounce_is_parsed_from_alanchand_current_price_column(): void
    {
        Http::fake(fn () => Http::response(<<<'HTML'
            <table>
                <tr>
                    <td>انس طلا</td>
                    <td><span>۴,۱۸۸.۹۶$</span><span>۲.۶۸%</span></td>
                    <td>-</td>
                    <td>-</td>
                </tr>
            </table>
            HTML));

        $errors = [];
        $result = $this->invokePriceFetcher('fetchGoldOunce', $errors);

        $this->assertSame(4188.96, $result);
        $this->assertSame([], $errors);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://alanchand.com/gold-price');
    }

    public function test_silver_ounce_uses_latest_price_from_tgju_today_table(): void
    {
        Http::fake(fn () => Http::response(<<<'HTML'
            <table class="data-table">
                <thead><tr><th>قیمت</th><th>زمان</th><th>تغییر</th></tr></thead>
                <tbody>
                    <tr><td>۶۴.۶۲۶</td><td>۱۳:۰۳:۰۰</td><td>۰.۰۴۷</td></tr>
                    <tr><td>۶۴.۷۳۶</td><td>۱۳:۰۴:۵۱</td><td>۰.۱۱</td></tr>
                    <tr><td>۶۴.۶۷۳</td><td>۱۲:۵۹:۵۸</td><td>۰.۰۶۲</td></tr>
                </tbody>
            </table>
            HTML));

        $result = $this->invokeMethod('fetchSilverOunce', [61.25]);

        $this->assertSame(64.736, $result);
        Http::assertSent(fn ($request) => $request->url() === 'https://www.tgju.org/profile/silver/today');
    }

    public function test_silver_ounce_is_parsed_from_alanchand_before_tgju(): void
    {
        Http::fake(fn ($request) => Http::response(<<<'HTML'
            <table>
                <tr>
                    <td>انس نقره</td>
                    <td><span>۶۵.۰۷۵$</span><span>۲.۳۳%</span></td>
                </tr>
            </table>
            HTML));

        $result = $this->invokeMethod('fetchSilverOunce', [61.25]);

        $this->assertSame(65.075, $result);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://alanchand.com/gold-price');
    }

    public function test_silver_ounce_falls_back_to_yahoo_after_alanchand_and_tgju(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'finance.yahoo.com')) {
                return Http::response(['chart' => ['result' => [['meta' => ['regularMarketPrice' => 31.42]]]]]);
            }

            return Http::response('', 503);
        });

        $result = $this->invokeMethod('fetchSilverOunce', [61.25]);

        $this->assertSame(31.42, $result);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'finance.yahoo.com/v8/finance/chart/SI=F'));
    }

    public function test_silver_ounce_falls_back_to_database_value_when_tgju_is_unavailable(): void
    {
        Http::fake(fn () => Http::response('', 503));

        $result = $this->invokeMethod('fetchSilverOunce', [61.25]);

        $this->assertSame(61.25, $result);
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
            ->with('PriceService gold ounce (alanchand) returned HTTP 503')
            ->once();
        Log::shouldHaveReceived('warning')
            ->with('PriceService gold ounce unavailable after all sources failed')
            ->once();
    }

    public function test_public_payload_hides_price_source_and_curl_errors(): void
    {
        $payload = PriceService::publicPayload([
            'errors' => [
                'انس طلا: دریافت نشد',
                'انس نقره: دریافت نشد',
                'طلا: cURL error 28: Connection timed out',
                'دلار: قیمت دریافت نشد',
            ],
        ]);

        $this->assertSame([], $payload['errors']);
    }

    private function invokePriceFetcher(string $methodName, array &$errors): mixed
    {
        $method = new ReflectionMethod(PriceService::class, $methodName);

        return $method->invokeArgs(new PriceService, [&$errors]);
    }

    private function invokeMethod(string $methodName, array $arguments = []): mixed
    {
        $method = new ReflectionMethod(PriceService::class, $methodName);

        return $method->invokeArgs(new PriceService, $arguments);
    }
}
