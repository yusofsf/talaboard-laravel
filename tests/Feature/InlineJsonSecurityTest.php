<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InlineJsonSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_page_json_cannot_be_broken_out_of_its_script_tag(): void
    {
        Article::create([
            'title' => '</script><script>alert(1)</script>',
            'slug' => 'inline-json-security',
            'summary' => 'security test',
            'body' => '<p>safe</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->get('/articles/inline-json-security');

        $response->assertOk();
        $response->assertDontSee('</script><script>alert(1)</script>', false);
        $response->assertSee('\\u003C/script\\u003E\\u003Cscript\\u003Ealert(1)\\u003C/script\\u003E', false);
    }
}
