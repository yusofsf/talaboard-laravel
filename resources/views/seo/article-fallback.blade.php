@php
    $articles = $props['articles'] ?? [];
    $article = $props['article'] ?? null;
    $relatedArticles = $props['relatedArticles'] ?? [];
    $isArticle = $component === 'Articles/Show' && is_array($article);
    $pageTitle = $isArticle
        ? ($article['title'] ?? '')
        : (($props['archive']['name'] ?? null)
            ? 'مقالات '.($props['archive']['name'] ?? '')
            : 'مقالات');
@endphp

<main class="page-wide" data-server-rendered-article-page style="max-width: 1120px">
    <nav aria-label="مسیر صفحه" style="margin-bottom: 18px">
        <a href="/">خانه</a>
        <span aria-hidden="true"> / </span>
        @if ($isArticle)
            <a href="/articles">مقالات</a>
            <span aria-hidden="true"> / </span>
            <span>{{ $article['title'] }}</span>
        @else
            <span>مقالات</span>
        @endif
    </nav>

    @if ($isArticle)
        <article style="max-width: 860px; margin: 0 auto">
            <h1>{{ $article['title'] }}</h1>

            @if (! empty($article['published_at']) || ! empty($article['created_at']))
                <p>{{ $article['published_at'] ?? $article['created_at'] }}</p>
            @endif

            @if (! empty($article['summary']))
                <p>{{ $article['summary'] }}</p>
            @endif

            @if (! empty($article['thumbnail_image']))
                <img src="{{ $article['thumbnail_image'] }}" alt="{{ $article['title'] }}" width="860" height="484">
            @endif

            <div class="article-body">
                @if (preg_match('/<\/?[a-z][\s\S]*>/i', (string) ($article['body'] ?? '')))
                    {!! $article['body'] !!}
                @else
                    @foreach (preg_split('/\R{2,}/u', (string) ($article['body'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $paragraph)
                        <p>{{ trim($paragraph) }}</p>
                    @endforeach
                @endif
            </div>

            @if (! empty($article['body_image']))
                <img src="{{ $article['body_image'] }}" alt="{{ $article['title'] }} - تصویر داخل متن" loading="lazy">
            @endif

            @if (! empty($article['topics']) || ! empty($article['tags']))
                <p>
                    @foreach (array_merge($article['topics'] ?? [], $article['tags'] ?? []) as $taxonomy)
                        <span>{{ $taxonomy }}</span>@if (! $loop->last)، @endif
                    @endforeach
                </p>
            @endif
        </article>

        @if (count($relatedArticles) > 0)
            <section aria-labelledby="server-related-articles" style="max-width: 860px; margin: 36px auto 0">
                <h2 id="server-related-articles">مقالات مرتبط</h2>
                <ul>
                    @foreach ($relatedArticles as $related)
                        <li><a href="/articles/{{ rawurlencode($related['slug']) }}">{{ $related['title'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif
    @else
        <section aria-labelledby="server-articles-title">
            <h1 id="server-articles-title">{{ $pageTitle }}</h1>
            <p>آموزش‌ها و تحلیل‌های کاربردی درباره طلا، نقره، سکه و بازار فلزات گران‌بها.</p>

            @if (count($articles) > 0)
                <div>
                    @foreach ($articles as $item)
                        <article>
                            <h2><a href="/articles/{{ rawurlencode($item['slug']) }}">{{ $item['title'] }}</a></h2>
                            @if (! empty($item['summary']))
                                <p>{{ $item['summary'] }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <p>هنوز مقاله‌ای منتشر نشده است.</p>
            @endif
        </section>
    @endif
</main>
