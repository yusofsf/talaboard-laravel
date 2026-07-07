<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_can_upload_article_images_and_rich_body_is_sanitized(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'مقاله تصویری',
            'slug' => 'article-with-uploaded-images',
            'summary' => 'خلاصه مقاله تصویری',
            'thumbnail_upload' => UploadedFile::fake()->image('thumb.jpg', 1200, 675),
            'body_upload' => UploadedFile::fake()->image('body.png', 900, 600),
            'body' => '<h2>تیتر مقاله</h2><p onclick="alert(1)">متن <strong>بولد</strong><script>alert(1)</script></p>',
            'tags' => 'تصویر',
            'topics' => 'آموزش',
            'is_published' => true,
        ])->assertRedirect();

        $article = Article::where('slug', 'article-with-uploaded-images')->firstOrFail();

        $this->assertStringStartsWith('/storage/articles/', $article->thumbnail_image);
        $this->assertStringStartsWith('/storage/articles/', $article->body_image);
        $this->assertStringContainsString('<h2>تیتر مقاله</h2>', $article->body);
        $this->assertStringContainsString('<strong>بولد</strong>', $article->body);
        $this->assertStringNotContainsString('<script>', $article->body);
        $this->assertStringNotContainsString('onclick', $article->body);

        Storage::disk('public')->assertExists(str_replace('/storage/', '', $article->thumbnail_image));
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $article->body_image));
    }
}
