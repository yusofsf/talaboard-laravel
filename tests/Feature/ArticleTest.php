<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_articles_are_public_and_include_media_tags_and_topics(): void
    {
        Article::create([
            'title' => 'راهنمای خرید نقره',
            'slug' => 'silver-buying-guide',
            'summary' => 'خلاصه مقاله',
            'thumbnail_image' => '/images/thumb.jpg',
            'body_image' => '/images/body.jpg',
            'body' => "پاراگراف اول\n\nپاراگراف دوم",
            'tags' => ['نقره', 'عیار'],
            'topics' => ['آموزش خرید'],
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->get('/articles')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Articles/Index')
                ->where('articles.0.title', 'راهنمای خرید نقره')
                ->where('articles.0.thumbnail_image', '/images/thumb.jpg')
                ->where('articles.0.topics.0', 'آموزش خرید'));

        $this->get('/articles/silver-buying-guide')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Articles/Show')
                ->where('article.body', "پاراگراف اول\n\nپاراگراف دوم")
                ->where('article.body_image', '/images/body.jpg')
                ->where('article.tags.0', 'نقره'));
    }

    public function test_unpublished_articles_are_not_public(): void
    {
        Article::create([
            'title' => 'پیش نویس',
            'slug' => 'draft-article',
            'body' => 'متن',
            'is_published' => false,
        ]);

        $this->get('/articles')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('articles', 0));
        $this->get('/articles/draft-article')->assertNotFound();
    }

    public function test_admin_can_create_an_article(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'تحلیل بازار سکه',
            'slug' => 'coin-market-analysis',
            'summary' => 'خلاصه تحلیل',
            'thumbnail_image' => '/images/coin-thumb.jpg',
            'body_image' => '/images/coin-body.jpg',
            'body' => 'متن تحلیل بازار سکه',
            'tags' => 'سکه، تحلیل',
            'topics' => 'بازار، آموزش',
            'is_published' => true,
        ])->assertRedirect();

        $article = Article::where('slug', 'coin-market-analysis')->first();
        $this->assertNotNull($article);
        $this->assertSame(['سکه', 'تحلیل'], $article->tags);
        $this->assertSame(['بازار', 'آموزش'], $article->topics);
        $this->assertSame('/images/coin-thumb.jpg', $article->thumbnail_image);
        $this->assertSame('/images/coin-body.jpg', $article->body_image);
        $this->assertTrue($article->is_published);
    }
}
