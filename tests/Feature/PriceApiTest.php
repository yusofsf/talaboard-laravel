<?php

namespace Tests\Feature;

use App\Models\PriceSnapshot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceApiTest extends TestCase
{
    use RefreshDatabase;

    private function configureCredentials(): void
    {
        config()->set('services.price_api.username', 'price-client');
        config()->set('services.price_api.secret', 'test-secret');
    }

    public function test_price_api_requires_basic_authentication(): void
    {
        $this->configureCredentials();

        $this->getJson('/api/v1/prices')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Basic realm="Price API", charset="UTF-8"');
    }

    public function test_price_api_rejects_invalid_basic_authentication(): void
    {
        $this->configureCredentials();

        $this->withBasicAuth('price-client', 'wrong-secret')
            ->getJson('/api/v1/prices')
            ->assertUnauthorized();
    }

    public function test_price_api_returns_the_latest_price_snapshot_for_valid_credentials(): void
    {
        $this->configureCredentials();
        PriceSnapshot::create(['payload' => [
            'gold' => ['geram' => 123456],
            'updated_at' => '12:34:56',
        ]]);

        $this->withBasicAuth('price-client', 'test-secret')
            ->getJson('/api/v1/prices')
            ->assertOk()
            ->assertJsonPath('gold.geram', 123456)
            ->assertJsonPath('updated_at', '12:34:56');
    }

    public function test_price_api_hides_source_and_curl_errors_from_stored_snapshots(): void
    {
        $this->configureCredentials();
        PriceSnapshot::create(['payload' => [
            'errors' => [
                'انس طلا: دریافت نشد',
                'نقره: cURL error 28: Connection timed out',
                'دلار: قیمت دریافت نشد',
            ],
        ]]);

        $this->withBasicAuth('price-client', 'test-secret')
            ->getJson('/api/v1/prices')
            ->assertOk()
            ->assertJsonPath('errors', ['دلار: قیمت دریافت نشد']);
    }

    public function test_admin_can_create_database_backed_price_api_credentials(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/price-api-credentials', [
            'username' => 'partner-service',
        ])->assertRedirect()
            ->assertSessionHas('price_api_secret');

        $secret = session('price_api_secret');
        $this->assertSame('partner-service', Setting::get('price_api_username'));
        $this->assertNotSame($secret, Setting::get('price_api_secret_hash'));

        PriceSnapshot::create(['payload' => ['gold' => ['geram' => 123456]]]);
        $this->withBasicAuth('partner-service', $secret)
            ->getJson('/api/v1/prices')
            ->assertOk()
            ->assertJsonPath('gold.geram', 123456);
    }
}
