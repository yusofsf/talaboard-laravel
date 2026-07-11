<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoPagesTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_sitemap_lists_published_articles(): void
    {
        $siteUrl = rtrim(config('seo.url'), '/');

        Article::create([
            'title' => 'راهنمای بازار طلا',
            'slug' => 'gold-market-guide',
            'body' => 'متن مقاله',
            'is_published' => true,
            'published_at' => now(),
        ]);
        Article::create([
            'title' => 'پیش‌نویس',
            'slug' => 'draft-guide',
            'body' => 'متن',
            'is_published' => false,
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee($siteUrl . '/articles', false)
            ->assertSee($siteUrl . '/articles/gold-market-guide', false)
            ->assertDontSee($siteUrl . '/articles/draft-guide', false);
    }
}
