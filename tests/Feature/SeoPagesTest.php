<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_requests_redirect_to_https_when_canonical_https_is_enforced(): void
    {
        config(['seo.force_https' => true]);

        $this->get('http://metalsp.ir/articles?topic='.urlencode('آموزش خرید'))
            ->assertStatus(301)
            ->assertRedirect('https://metalsp.ir/articles?topic='.urlencode('آموزش خرید'));

        $this->get('https://www.metalsp.ir/articles')
            ->assertStatus(301)
            ->assertRedirect('https://metalsp.ir/articles');
    }

    public function test_public_seo_landing_pages_render_indexable_metadata(): void
    {
        foreach (['/silver-prices', '/gold-prices', '/coin-prices', '/silver-999-price', '/gold-gram-price', '/full-coin-price', '/buy-gold', '/sell-silver'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
                ->assertSee('<link rel="canonical" href="'.rtrim(config('seo.url'), '/').$path.'">', false)
                ->assertSee('application/ld+json', false);
        }
    }

    public function test_sitemap_lists_public_commercial_pages(): void
    {
        $siteUrl = rtrim(config('seo.url'), '/');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee($siteUrl.'/silver-prices', false)
            ->assertSee($siteUrl.'/gold-prices', false)
            ->assertSee($siteUrl.'/coin-prices', false)
            ->assertSee($siteUrl.'/silver-999-price', false)
            ->assertSee($siteUrl.'/gold-gram-price', false)
            ->assertSee($siteUrl.'/full-coin-price', false)
            ->assertSee($siteUrl.'/buy-gold', false)
            ->assertSee($siteUrl.'/sell-silver', false)
            ->assertDontSee($siteUrl.'/login', false)
            ->assertDontSee($siteUrl.'/register', false);
    }

    public function test_sitemap_lists_published_articles(): void
    {
        $siteUrl = rtrim(config('seo.url'), '/');

        Article::create([
            'title' => 'راهنمای بازار طلا',
            'slug' => 'gold-market-guide',
            'topics' => ['آموزش خرید', 'آموزش  خرید', '!!!'],
            'tags' => ['طلای آبشده', '---'],
            'body' => 'متن مقاله',
            'is_published' => true,
            'published_at' => now(),
        ]);
        Article::create([
            'title' => 'پیش‌نویس',
            'slug' => 'draft-guide',
            'topics' => ['موضوع پیش نویس'],
            'tags' => ['تگ پیش نویس'],
            'body' => 'متن',
            'is_published' => false,
        ]);

        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee($siteUrl.'/articles', false)
            ->assertSee($siteUrl.'/articles/gold-market-guide', false)
            ->assertSee($siteUrl.'/articles/topic/'.rawurlencode('آموزش-خرید'), false)
            ->assertSee($siteUrl.'/articles/tag/'.rawurlencode('طلای-آبشده'), false)
            ->assertDontSee($siteUrl.'/articles/draft-guide', false)
            ->assertDontSee(rawurlencode('موضوع-پیش-نویس'), false)
            ->assertDontSee(rawurlencode('تگ-پیش-نویس'), false);

        $this->assertSame(1, substr_count($response->getContent(), $siteUrl.'/articles/topic/'.rawurlencode('آموزش-خرید')));
        $this->assertStringNotContainsString('/articles/topic/</loc>', $response->getContent());
        $this->assertStringNotContainsString('/articles/tag/</loc>', $response->getContent());
    }
}
