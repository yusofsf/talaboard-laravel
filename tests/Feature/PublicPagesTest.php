<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_speed_test_page_does_not_require_login(): void
    {
        $this->get('/speed-test')->assertOk();
    }

    public function test_about_page_is_public(): void
    {
        $this->get('/about')->assertOk();
    }

    public function test_article_alias_redirects_to_articles_index(): void
    {
        $this->get('/article')->assertRedirect(route('articles.index'));
    }

    public function test_public_pages_include_security_headers(): void
    {
        config(['seo.force_https' => true]);

        $response = $this->get('https://metalsp.ir/')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(), payment=()')
            ->assertHeader('Content-Security-Policy');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[a-f0-9]{32}' https:\/\/s3\.tradingview\.com/", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }
}
