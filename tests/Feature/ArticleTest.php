<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleTag;
use App\Models\ArticleTopic;
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

        $this->get('/articles/topic/'.rawurlencode('آموزش-خرید'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Articles/Index')
                ->where('filters.topic', 'آموزش خرید')
                ->where('articles.0.title', 'راهنمای خرید طلا')
                ->has('articles', 1)
                ->where('topics.0', 'آموزش خرید')
                ->where('seo.canonical', rtrim(config('seo.url'), '/').'/articles/topic/'.rawurlencode('آموزش-خرید')));

        $this->get('/articles/tag/'.rawurlencode('تحلیل'))
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

    public function test_admin_cannot_create_an_article_over_a_non_canonical_http_origin(): void
    {
        config(['seo.force_https' => true]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('http://www.metalsp.ir/admin/articles', [
            'title' => 'Article from admin form',
            'slug' => 'article-from-admin-form',
            'body' => 'Article body',
            'is_published' => true,
        ])->assertStatus(426);

        $this->assertDatabaseMissing('articles', [
            'slug' => 'article-from-admin-form',
        ]);
    }

    public function test_admin_article_form_receives_existing_tags_and_topics(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertNotNull(ArticleTopic::where('name', 'نقره')->first());
        ArticleTag::create(['name' => 'سرمایه‌گذاری', 'slug' => 'سرمایه-گذاری']);
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
                ->where('tagOptions', fn ($options) => collect($options)->contains('سرمایه‌گذاری') && collect($options)->contains('سکه'))
                ->where('topicOptions', fn ($options) => collect($options)->contains('نقره') && collect($options)->contains('آموزش'))
                ->where('topics', fn ($topics) => collect($topics)->contains(fn ($topic) => $topic['name'] === 'نقره'))
                ->where('tags.0.name', 'سرمایه‌گذاری'));
    }

    public function test_admin_can_edit_and_delete_article_topics_and_tags(): void
    {
        $admin = User::factory()->admin()->create();
        $topic = ArticleTopic::where('name', 'سکه')->firstOrFail();
        $tag = ArticleTag::create(['name' => 'عیار', 'slug' => 'عیار']);
        $article = Article::create([
            'title' => 'راهنمای سکه',
            'slug' => 'coin-topic-edit',
            'body' => 'متن مقاله',
            'topics' => ['سکه'],
            'tags' => ['عیار'],
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->put("/admin/article-topics/{$topic->id}", [
            'name' => 'بازار سکه',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->actingAs($admin)->put("/admin/article-tags/{$tag->id}", [
            'name' => 'عیار نقره',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article->refresh();
        $this->assertSame(['بازار سکه'], $article->topics);
        $this->assertSame(['عیار نقره'], $article->tags);

        $this->actingAs($admin)->delete("/admin/article-topics/{$topic->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/admin/article-tags/{$tag->id}")->assertRedirect();

        $article->refresh();
        $this->assertSame([], $article->topics);
        $this->assertSame([], $article->tags);
    }

    public function test_admin_can_upload_article_images_and_rich_body_is_sanitized(): void
    {
        config([
            'filesystems.disks.public.root' => sys_get_temp_dir().'/talaboard-test-public',
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

    public function test_admin_can_upload_an_embedded_article_image_for_the_editor(): void
    {
        config([
            'filesystems.disks.public.root' => sys_get_temp_dir().'/talaboard-test-public-inline',
            'filesystems.disks.public.url' => '/storage',
            'filesystems.disks.public.visibility' => 'public',
        ]);
        Storage::disk('public')->deleteDirectory('articles');
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/articles/embedded-image', [
            'image' => UploadedFile::fake()->image('inline-photo.webp', 1200, 675),
        ]);

        $response->assertOk()
            ->assertJsonPath('alt', 'inline-photo');

        $this->assertStringStartsWith('/storage/articles/', $response->json('url'));

        $storedPath = str_replace('/storage/', '', $response->json('url'));
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_article_body_keeps_only_uploaded_embedded_images(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'مقاله با تصویر داخل متن',
            'slug' => 'article-with-inline-image',
            'body' => '<p>متن آغازین</p><img src="/storage/articles/inline-image.jpg" alt="نمونه" style="width:100%"><img src="https://example.com/bad.jpg" alt="bad"><p>متن پایانی</p>',
            'is_published' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article = Article::where('slug', 'article-with-inline-image')->firstOrFail();

        $this->assertStringContainsString('<img src="/storage/articles/inline-image.jpg" alt="نمونه">', $article->body);
        $this->assertStringNotContainsString('style=', $article->body);
        $this->assertStringNotContainsString('https://example.com/bad.jpg', $article->body);
    }

    public function test_article_body_removes_unquoted_event_handlers_and_unsafe_links(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'Ù…Ù‚Ø§Ù„Ù‡ Ø¨Ø§ HTML Ù…Ø´Ú©ÙˆÚ©',
            'slug' => 'article-with-suspicious-html',
            'body' => '<p onclick=alert(1)>Ù…ØªÙ†</p><a href=javascript:alert(1) target="_blank">Ù„ÛŒÙ†Ú©</a><script>alert(1)</script>',
            'is_published' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article = Article::where('slug', 'article-with-suspicious-html')->firstOrFail();

        $this->assertStringContainsString('<p>Ù…ØªÙ†</p>', $article->body);
        $this->assertStringContainsString('<a target="_blank" rel="noopener noreferrer">Ù„ÛŒÙ†Ú©</a>', $article->body);
        $this->assertStringNotContainsString('onclick', $article->body);
        $this->assertStringNotContainsString('javascript:', $article->body);
        $this->assertStringNotContainsString('<script', $article->body);
    }

    public function test_article_body_keeps_uploaded_images_when_public_disk_url_differs_from_seo_url(): void
    {
        config([
            'seo.url' => 'https://metalsp.ir',
            'filesystems.disks.public.url' => 'https://cdn.metalsp.ir/storage',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'مقاله با تصویر cdn',
            'slug' => 'article-with-cdn-image',
            'body' => '<p>متن</p><img src="https://cdn.metalsp.ir/storage/articles/cdn-image.jpg" alt="cdn image">',
            'is_published' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article = Article::where('slug', 'article-with-cdn-image')->firstOrFail();

        $this->assertStringContainsString('https://cdn.metalsp.ir/storage/articles/cdn-image.jpg', $article->body);
    }

    public function test_topic_archive_has_a_clean_canonical_url_and_only_matching_published_articles(): void
    {
        Article::create([
            'title' => 'راهنمای خرید طلا',
            'slug' => 'gold-guide',
            'body' => 'متن مقاله',
            'topics' => ['آموزش خرید'],
            'is_published' => true,
            'published_at' => now(),
        ]);
        Article::create([
            'title' => 'پیش نویس طلا',
            'slug' => 'draft-gold-guide',
            'body' => 'متن پیش نویس',
            'topics' => ['آموزش خرید'],
            'is_published' => false,
        ]);
        Article::create([
            'title' => 'راهنمای دوم طلا',
            'slug' => 'second-gold-guide',
            'body' => 'متن مقاله دوم',
            'topics' => ['آموزش  خرید'],
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $this->get('/articles/topic/'.rawurlencode('آموزش-خرید'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Articles/Index')
                ->where('filters.topic', 'آموزش خرید')
                ->where('archive.type', 'topic')
                ->where('archive.slug', 'آموزش-خرید')
                ->where('articles.0.title', 'راهنمای خرید طلا')
                ->has('articles', 2)
                ->where('seo.canonical', rtrim(config('seo.url'), '/').'/articles/topic/'.rawurlencode('آموزش-خرید')));

        $this->get('/articles/topic/'.rawurlencode('آموزش---خرید'))
            ->assertStatus(301)
            ->assertRedirect('/articles/topic/'.rawurlencode('آموزش-خرید'));
    }

    public function test_tag_archive_has_a_clean_url_and_unknown_taxonomies_are_not_indexable(): void
    {
        Article::create([
            'title' => 'تحلیل بازار سکه',
            'slug' => 'coin-analysis',
            'body' => 'متن مقاله',
            'tags' => ['تحلیل بازار'],
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->get('/articles/tag/'.rawurlencode('تحلیل-بازار'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.tag', 'تحلیل بازار')
                ->where('archive.type', 'tag')
                ->where('articles.0.title', 'تحلیل بازار سکه')
                ->where('seo.canonical', rtrim(config('seo.url'), '/').'/articles/tag/'.rawurlencode('تحلیل-بازار')));

        $this->get('/articles/tag/not-found')->assertNotFound();
        $this->get('/articles/topic/not-found')->assertNotFound();
    }

    public function test_legacy_article_filters_redirect_to_the_clean_taxonomy_urls(): void
    {
        Article::create([
            'title' => 'راهنمای سرمایه گذاری',
            'slug' => 'investment-guide',
            'body' => 'متن مقاله',
            'topics' => ['سرمایه گذاری'],
            'tags' => ['طلای آبشده'],
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->get('/articles?topic='.urlencode('سرمایه گذاری'))
            ->assertRedirect('/articles/topic/'.rawurlencode('سرمایه-گذاری'))
            ->assertStatus(301);

        $this->get('/articles?tag='.urlencode('طلای آبشده'))
            ->assertRedirect('/articles/tag/'.rawurlencode('طلای-آبشده'))
            ->assertStatus(301);
    }

    public function test_article_page_includes_only_relevant_published_articles_for_internal_linking(): void
    {
        $article = Article::create([
            'title' => 'راهنمای اصلی طلا',
            'slug' => 'main-gold-guide',
            'body' => 'متن مقاله',
            'topics' => ['آموزش خرید'],
            'tags' => ['طلای آبشده'],
            'is_published' => true,
            'published_at' => now(),
        ]);
        Article::create([
            'title' => 'مقاله مرتبط',
            'slug' => 'related-gold-guide',
            'body' => 'متن مرتبط',
            'topics' => ['آموزش خرید'],
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
        Article::create([
            'title' => 'مقاله نامرتبط',
            'slug' => 'unrelated-coin-guide',
            'body' => 'متن نامرتبط',
            'topics' => ['بازار سکه'],
            'is_published' => true,
            'published_at' => now()->subDays(2),
        ]);
        Article::create([
            'title' => 'مقاله مرتبط منتشر نشده',
            'slug' => 'draft-related-guide',
            'body' => 'متن پیش نویس',
            'tags' => ['طلای آبشده'],
            'is_published' => false,
        ]);

        $this->get('/articles/'.$article->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('relatedArticles.0.title', 'مقاله مرتبط')
                ->has('relatedArticles', 1));
    }
}
