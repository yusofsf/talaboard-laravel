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

    public function test_articles_can_be_filtered_by_topic_or_tag(): void
    {
        Article::create([
            'title' => 'راهنمای خرید طلا',
            'slug' => 'gold-buying-guide',
            'body' => 'متن مقاله طلا',
            'tags' => ['سرمایه‌گذاری'],
            'topics' => ['آموزش خرید'],
            'is_published' => true,
            'published_at' => now(),
        ]);
        Article::create([
            'title' => 'تحلیل بازار سکه',
            'slug' => 'coin-market',
            'body' => 'متن مقاله سکه',
            'tags' => ['تحلیل'],
            'topics' => ['بازار'],
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $this->get('/articles?topic=' . urlencode('آموزش خرید'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Articles/Index')
                ->where('filters.topic', 'آموزش خرید')
                ->where('articles.0.title', 'راهنمای خرید طلا')
                ->has('articles', 1)
                ->where('topics.0', 'آموزش خرید')
                ->where('seo.canonical', rtrim(config('seo.url'), '/') . '/articles?topic=' . rawurlencode('آموزش خرید')));

        $this->get('/articles?tag=' . urlencode('تحلیل'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.tag', 'تحلیل')
                ->where('articles.0.title', 'تحلیل بازار سکه')
                ->has('articles', 1));
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
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article = Article::where('slug', 'coin-market-analysis')->first();
        $this->assertNotNull($article);
        $this->assertSame(['سکه', 'تحلیل'], $article->tags);
        $this->assertSame(['بازار', 'آموزش'], $article->topics);
        $this->assertSame('/images/coin-thumb.jpg', $article->thumbnail_image);
        $this->assertSame('/images/coin-body.jpg', $article->body_image);
        $this->assertTrue($article->is_published);
    }

    public function test_admin_article_form_receives_existing_tags_and_topics(): void
    {
        $admin = User::factory()->admin()->create();
        Article::create([
            'title' => 'مقاله موضوع‌دار',
            'slug' => 'article-with-taxonomy',
            'body' => 'متن',
            'tags' => ['سکه', 'عیار'],
            'topics' => ['آموزش', 'بازار'],
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/articles')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Articles')
                ->where('tagOptions.0', 'سکه')
                ->where('topicOptions.0', 'آموزش'));
    }

    public function test_admin_can_upload_article_images_and_rich_body_is_sanitized(): void
    {
        config([
            'filesystems.disks.public.root' => sys_get_temp_dir() . '/talaboard-test-public',
            'filesystems.disks.public.url' => '/storage',
            'filesystems.disks.public.visibility' => 'public',
        ]);
        Storage::disk('public')->deleteDirectory('articles');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'مقاله تصویری',
            'slug' => 'article-with-uploaded-images',
            'summary' => 'خلاصه مقاله تصویری',
            'thumbnail_upload' => UploadedFile::fake()->image('thumb.jpg', 1200, 675),
            'body_upload' => UploadedFile::fake()->image('body.png', 900, 600),
            'body' => '<h2>تیتر مقاله</h2><p style="color:red">متن <strong>بولد</strong></p>',
            'tags' => 'تصویر',
            'topics' => 'آموزش',
            'is_published' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article = Article::where('slug', 'article-with-uploaded-images')->firstOrFail();

        $this->assertStringStartsWith('/storage/articles/', $article->thumbnail_image);
        $this->assertStringStartsWith('/storage/articles/', $article->body_image);
        $this->assertStringContainsString('<h2>تیتر مقاله</h2>', $article->body);
        $this->assertStringContainsString('<strong>بولد</strong>', $article->body);
        $this->assertStringNotContainsString('style=', $article->body);

        Storage::disk('public')->assertExists(str_replace('/storage/', '', $article->thumbnail_image));
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $article->body_image));
    }
}
