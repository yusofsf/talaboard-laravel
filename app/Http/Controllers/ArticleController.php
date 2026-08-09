<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\Article;
use App\Models\ArticleTag;
use App\Models\ArticleTopic;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class ArticleController extends Controller
{
    private const PER_PAGE = 6;

    public function index(Request $request)
    {
        $topic = trim((string) $request->query('topic', ''));
        $tag = trim((string) $request->query('tag', ''));
        $siteUrl = rtrim(config('seo.url'), '/');

        if ($topic !== '') {
            return redirect('/articles/topic/'.rawurlencode(Article::taxonomySlug($topic)), 301);
        }

        if ($tag !== '') {
            return redirect('/articles/tag/'.rawurlencode(Article::taxonomySlug($tag)), 301);
        }

        $paginator = Article::published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
        $articles = $paginator->getCollection()
            ->map(fn (Article $a) => $this->presentCard($a))
            ->values();

        $canonical = $siteUrl.'/articles'.$this->canonicalPageSuffix($paginator);
        $siteName = config('seo.site_name');

        return Inertia::render('Articles/Index', [
            'articles' => $articles,
            'pagination' => $this->presentPagination($paginator),
            'filters' => ['topic' => $topic, 'tag' => $tag],
            'archive' => null,
            'topics' => $this->listValues('topics'),
            'seo' => [
                'title' => "مقالات طلا، نقره و سکه | {$siteName}",
                'description' => 'مقالات آموزشی و تحلیلی درباره خرید و فروش طلا، نقره، سکه، عیارها و بازار فلزات گران‌بها.',
                'canonical' => $canonical,
                'schema' => $this->articleListSchema($articles->all(), $canonical),
            ],
        ]);
    }

    public function topic(string $slug)
    {
        return $this->taxonomyArchive('topic', $slug);
    }

    public function tag(string $slug)
    {
        return $this->taxonomyArchive('tag', $slug);
    }

    private function taxonomyArchive(string $type, string $slug)
    {
        $requestedSlug = $slug;
        $slug = Article::taxonomySlug($requestedSlug);

        abort_if($slug === '', 404);

        if ($requestedSlug !== $slug) {
            return redirect('/articles/'.$type.'/'.rawurlencode($slug), 301);
        }

        $field = $type === 'topic' ? 'topics' : 'tags';
        $value = collect($this->listValues($field))
            ->first(fn (string $item) => Article::taxonomySlug($item) === $slug);

        abort_unless($value, 404);

        $siteUrl = rtrim(config('seo.url'), '/');
        $canonicalBase = $siteUrl.'/articles/'.$type.'/'.rawurlencode($slug);
        $matchingArticles = Article::published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Article $article) => collect($article->{$field} ?: [])
                ->contains(fn (string $item) => Article::taxonomySlug($item) === $slug))
            ->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $matchingArticles->forPage($page, self::PER_PAGE)->values(),
            $matchingArticles->count(),
            self::PER_PAGE,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
        $articles = $paginator->getCollection()
            ->map(fn (Article $article) => $this->presentCard($article))
            ->values();
        $canonical = $canonicalBase.$this->canonicalPageSuffix($paginator);
        $label = $type === 'topic' ? 'موضوع' : 'برچسب';
        $siteName = config('seo.site_name');
        $isIndexable = $matchingArticles->count() >= max(2, (int) config('seo.taxonomy_min_indexable_articles', 2));

        return Inertia::render('Articles/Index', [
            'articles' => $articles,
            'pagination' => $this->presentPagination($paginator),
            'filters' => [
                'topic' => $type === 'topic' ? $value : '',
                'tag' => $type === 'tag' ? $value : '',
            ],
            'archive' => ['type' => $type, 'slug' => $slug, 'name' => $value],
            'topics' => $this->listValues('topics'),
            'seo' => [
                'title' => "مقالات {$label} {$value} | {$siteName}",
                'description' => "مقالات و راهنماهای {$label} {$value} درباره بازار طلا، نقره و سکه در {$siteName}.",
                'canonical' => $canonical,
                'robots' => $isIndexable
                    ? 'index, follow, max-image-preview:large'
                    : 'noindex, follow',
                'schema' => $this->articleListSchema($articles->all(), $canonical, "مقالات {$label} {$value}"),
            ],
        ]);
    }

    public function show(string $slug)
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();
        $siteUrl = rtrim(config('seo.url'), '/');
        $canonical = $siteUrl.'/articles/'.$article->slug;
        $image = $this->absoluteUrl($article->thumbnail_image ?: $article->body_image);
        $siteName = config('seo.site_name');

        return Inertia::render('Articles/Show', [
            'article' => $this->present($article),
            'relatedArticles' => $this->relatedArticles($article),
            'seo' => [
                'title' => $article->title.' | '.$siteName,
                'description' => $article->summary ?: mb_substr(strip_tags($article->body), 0, 150),
                'canonical' => $canonical,
                'type' => 'article',
                'image' => $image ?: $siteUrl.config('seo.logo'),
                'schema' => $this->articleSchema($article, $canonical, $image),
            ],
        ]);
    }

    private function present(Article $article): array
    {
        return [
            ...$this->presentCard($article),
            'body_image' => $article->body_image,
            'body' => $article->body,
        ];
    }

    private function presentCard(Article $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'summary' => $article->summary,
            'thumbnail_image' => $article->thumbnail_image,
            'tags' => $this->taxonomyValues($article->tags ?: []),
            'topics' => $this->taxonomyValues($article->topics ?: []),
            'published_at' => $article->published_at ? Jalali::format($article->published_at, false) : null,
            'created_at' => Jalali::format($article->created_at, false),
        ];
    }

    private function presentPagination(LengthAwarePaginator $paginator): array
    {
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $visiblePages = collect([1, $lastPage])
            ->merge(range(max(1, $currentPage - 2), min($lastPage, $currentPage + 2)))
            ->unique()
            ->sort()
            ->values();

        return [
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'prev_page_url' => $paginator->previousPageUrl(),
            'next_page_url' => $paginator->nextPageUrl(),
            'pages' => $visiblePages
                ->map(fn (int $page) => [
                    'page' => $page,
                    'url' => $paginator->url($page),
                    'active' => $page === $currentPage,
                ])
                ->all(),
        ];
    }

    private function canonicalPageSuffix(LengthAwarePaginator $paginator): string
    {
        return $paginator->currentPage() > 1 ? '?page='.$paginator->currentPage() : '';
    }

    private function relatedArticles(Article $article): array
    {
        $topics = collect($this->taxonomyValues($article->topics ?: []))->map(fn (string $item) => Article::taxonomySlug($item));
        $tags = collect($this->taxonomyValues($article->tags ?: []))->map(fn (string $item) => Article::taxonomySlug($item));

        return Article::published()
            ->whereKeyNot($article->getKey())
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Article $candidate) => [
                'article' => $candidate,
                'score' => ($topics->intersect(collect($candidate->topics ?: [])->map(fn (string $item) => Article::taxonomySlug($item)))->count() * 2)
                    + $tags->intersect(collect($candidate->tags ?: [])->map(fn (string $item) => Article::taxonomySlug($item)))->count(),
            ])
            ->filter(fn (array $candidate) => $candidate['score'] > 0)
            ->sortByDesc('score')
            ->take(3)
            ->map(fn (array $candidate) => $this->presentCard($candidate['article']))
            ->values()
            ->all();
    }

    private function listValues(string $field): array
    {
        $model = $field === 'topics' ? ArticleTopic::class : ArticleTag::class;

        return collect($model::query()->orderBy('name')->pluck('name')->all())
            ->merge($this->taxonomyValues(Article::published()
                ->pluck($field)
                ->flatMap(fn ($items) => $items ?: [])
                ->all()))
            ->unique(fn (string $item) => Article::taxonomySlug($item))
            ->sort()
            ->values()
            ->all();
    }

    private function taxonomyValues(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => preg_replace('/\s+/u', ' ', trim((string) $item)))
            ->filter(fn (?string $item) => $item && Article::taxonomySlug($item) !== '')
            ->unique(fn (string $item) => Article::taxonomySlug($item))
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

        return rtrim(config('seo.url'), '/').'/'.ltrim($path, '/');
    }

    private function articleListSchema(array $articles, string $canonical, ?string $name = null): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name ?: 'مقالات '.config('seo.site_name'),
            'url' => $canonical,
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => array_map(fn ($article, $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => rtrim(config('seo.url'), '/').'/articles/'.$article['slug'],
                    'name' => $article['title'],
                ], $articles, array_keys($articles)),
            ],
        ];
    }

    private function articleSchema(Article $article, string $canonical, ?string $image): array
    {
        $siteUrl = rtrim(config('seo.url'), '/');
        $topics = $this->taxonomyValues($article->topics ?: []);
        $keywords = $this->taxonomyValues(array_merge($topics, $article->tags ?: []));

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Article',
                    '@id' => $canonical.'#article',
                    'mainEntityOfPage' => $canonical,
                    'headline' => $article->title,
                    'description' => $article->summary ?: mb_substr(strip_tags($article->body), 0, 150),
                    'url' => $canonical,
                    'image' => $image ?: $siteUrl.config('seo.logo'),
                    'inLanguage' => 'fa-IR',
                    'articleSection' => $topics,
                    'datePublished' => optional($article->published_at ?: $article->created_at)->toAtomString(),
                    'dateModified' => optional($article->updated_at)->toAtomString(),
                    'author' => ['@id' => $siteUrl.'/#organization'],
                    'publisher' => ['@id' => $siteUrl.'/#organization'],
                    'keywords' => $keywords,
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'خانه',
                            'item' => $siteUrl.'/',
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'مقالات',
                            'item' => $siteUrl.'/articles',
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $article->title,
                            'item' => $canonical,
                        ],
                    ],
                ],
            ],
        ];
    }
}
