<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoPagesTest extends TestCase
{
    public function test_public_seo_landing_pages_render_indexable_metadata(): void
    {
        foreach (['/silver-prices', '/gold-prices', '/coin-prices', '/silver-999-price', '/gold-gram-price', '/full-coin-price', '/buy-gold', '/sell-silver'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
                ->assertSee('<link rel="canonical" href="' . rtrim(config('seo.url'), '/') . $path . '">', false)
                ->assertSee('application/ld+json', false);
        }
    }

    public function test_sitemap_lists_public_commercial_pages(): void
    {
        $siteUrl = rtrim(config('seo.url'), '/');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee($siteUrl . '/silver-prices', false)
            ->assertSee($siteUrl . '/gold-prices', false)
            ->assertSee($siteUrl . '/coin-prices', false)
            ->assertSee($siteUrl . '/silver-999-price', false)
            ->assertSee($siteUrl . '/gold-gram-price', false)
            ->assertSee($siteUrl . '/full-coin-price', false)
            ->assertSee($siteUrl . '/buy-gold', false)
            ->assertSee($siteUrl . '/sell-silver', false)
            ->assertDontSee($siteUrl . '/login', false)
            ->assertDontSee($siteUrl . '/register', false);
    }
}
