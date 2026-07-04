<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoPagesTest extends TestCase
{
    public function test_public_seo_landing_pages_render_indexable_metadata(): void
    {
        foreach (['/silver-prices', '/gold-prices', '/coin-prices'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
                ->assertSee('<link rel="canonical" href="' . rtrim(config('seo.url'), '/') . $path . '">', false)
                ->assertSee('application/ld+json', false);
        }
    }

    public function test_sitemap_lists_public_commercial_pages(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('https://metalsp.ir/silver-prices', false)
            ->assertSee('https://metalsp.ir/gold-prices', false)
            ->assertSee('https://metalsp.ir/coin-prices', false)
            ->assertDontSee('https://metalsp.ir/login', false)
            ->assertDontSee('https://metalsp.ir/register', false);
    }
}
