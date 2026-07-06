<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\Article;
use Inertia\Inertia;

class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Article $a) => $this->present($a));

        return Inertia::render('Articles/Index', [
            'articles' => $articles,
            'seo' => [
                'title' => 'مقالات طلا، نقره و سکه | آبشده صفری‌پور',
                'description' => 'مقالات آموزشی و تحلیلی درباره خرید و فروش طلا، نقره، سکه، عیارها و بازار فلزات گران‌بها.',
                'canonical' => rtrim(config('seo.url'), '/') . '/articles',
            ],
        ]);
    }

    public function show(string $slug)
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();

        return Inertia::render('Articles/Show', [
            'article' => $this->present($article),
            'seo' => [
                'title' => $article->title . ' | آبشده صفری‌پور',
                'description' => $article->summary ?: mb_substr(strip_tags($article->body), 0, 150),
                'canonical' => rtrim(config('seo.url'), '/') . '/articles/' . $article->slug,
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
}
