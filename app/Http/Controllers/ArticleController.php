<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\Article;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $topic = trim((string) $request->query('topic', ''));
        $tag = trim((string) $request->query('tag', ''));
        $siteUrl = rtrim(config('seo.url'), '/');

        $articles = Article::published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->filter(function (Article $article) use ($topic, $tag) {
                if ($topic !== '' && ! in_array($topic, $article->topics ?: [], true)) {
                    return false;
                }

                if ($tag !== '' && ! in_array($tag, $article->tags ?: [], true)) {
                    return false;
                }

                return true;
            })
            ->values()
            ->map(fn (Article $a) => $this->present($a));

        $filterTitle = $topic !== '' ? "موضوع {$topic}" : ($tag !== '' ? "تگ {$tag}" : null);
        $canonical = $siteUrl . '/articles';

        if ($topic !== '') {
            $canonical .= '?topic=' . rawurlencode($topic);
        } elseif ($tag !== '') {
            $canonical .= '?tag=' . rawurlencode($tag);
        }

        return Inertia::render('Articles/Index', [
            'articles' => $articles,
            'filters' => ['topic' => $topic, 'tag' => $tag],
            'topics' => $this->listValues('topics'),
            'tags' => $this->listValues('tags'),
            'seo' => [
                'title' => $filterTitle
                    ? "مقالات {$filterTitle} | آبشده صفری‌پور"
                    : 'مقالات طلا، نقره و سکه | آبشده صفری‌پور',
                'description' => $filterTitle
                    ? "مقالات مرتبط با {$filterTitle} درباره خرید و فروش طلا، نقره، سکه و بازار فلزات گران‌بها."
                    : 'مقالات آموزشی و تحلیلی درباره خرید و فروش طلا، نقره، سکه، عیارها و بازار فلزات گران‌بها.',
                'canonical' => $canonical,
                'schema' => $this->articleListSchema($articles->all(), $canonical),
            ],
        ]);
    }

    public function show(string $slug)
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();
        $siteUrl = rtrim(config('seo.url'), '/');
        $canonical = $siteUrl . '/articles/' . $article->slug;
        $image = $this->absoluteUrl($article->thumbnail_image ?: $article->body_image);

        return Inertia::render('Articles/Show', [
            'article' => $this->present($article),
            'seo' => [
                'title' => $article->title . ' | آبشده صفری‌پور',
                'description' => $article->summary ?: mb_substr(strip_tags($article->body), 0, 150),
                'canonical' => $canonical,
                'type' => 'article',
                'image' => $image ?: $siteUrl . config('seo.logo'),
                'schema' => $this->articleSchema($article, $canonical, $image),
            ],
        ]);
    }

    private function present(Article $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'summary' => $article->summary,
            'thumbnail_image' => $article->thumbnail_image,
            'body_image' => $article->body_image,
            'body' => $article->body,
            'tags' => $article->tags ?: [],
            'topics' => $article->topics ?: [],
            'published_at' => $article->published_at ? Jalali::format($article->published_at, false) : null,
            'created_at' => Jalali::format($article->created_at, false),
        ];
    }

    private function listValues(string $field): array
    {
        return Article::query()
            ->pluck($field)
            ->flatMap(fn ($items) => $items ?: [])
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function absoluteUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim(config('seo.url'), '/') . '/' . ltrim($path, '/');
    }

    private function articleListSchema(array $articles, string $canonical): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => 'مقالات آبشده صفری‌پور',
            'url' => $canonical,
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => array_map(fn ($article, $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => rtrim(config('seo.url'), '/') . '/articles/' . $article['slug'],
                    'name' => $article['title'],
                ], $articles, array_keys($articles)),
            ],
        ];
    }

    private function articleSchema(Article $article, string $canonical, ?string $image): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->title,
            'description' => $article->summary ?: mb_substr(strip_tags($article->body), 0, 150),
            'url' => $canonical,
            'image' => $image ?: rtrim(config('seo.url'), '/') . config('seo.logo'),
            'datePublished' => optional($article->published_at ?: $article->created_at)->toAtomString(),
            'dateModified' => optional($article->updated_at)->toAtomString(),
            'author' => [
                '@type' => 'Organization',
                'name' => config('seo.site_name'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('seo.site_name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => rtrim(config('seo.url'), '/') . config('seo.logo'),
                ],
            ],
            'keywords' => array_values(array_unique(array_merge($article->topics ?: [], $article->tags ?: []))),
        ];
    }
}
