<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\Article;
use App\Models\ArticleTag;
use App\Models\ArticleTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminArticleController extends Controller
{
    public function index()
    {
        $articles = Article::orderByDesc('created_at')->get()
            ->map(fn (Article $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'slug' => $a->slug,
                'summary' => $a->summary,
                'thumbnail_image' => $a->thumbnail_image,
                'body_image' => $a->body_image,
                'body' => $a->body,
                'tags' => implode(', ', $a->tags ?: []),
                'topics' => implode(', ', $a->topics ?: []),
                'is_published' => $a->is_published,
                'published_at' => $a->published_at ? Jalali::format($a->published_at, false) : null,
                'created_at' => Jalali::format($a->created_at, false),
            ]);

        return Inertia::render('Admin/Articles', [
            'articles' => $articles,
            'tagOptions' => $this->listValues('tags'),
            'topicOptions' => $this->listValues('topics'),
            'tags' => $this->listTaxonomies('tags'),
            'topics' => $this->listTaxonomies('topics'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Article::create($data);
        $this->syncTaxonomyOptions($data);

        return back()->with('success', 'مقاله ذخیره شد.');
    }

    public function update(Request $request, int $id)
    {
        $article = Article::findOrFail($id);
        $data = $this->validated($request, $article->id);
        $article->update($data);
        $this->syncTaxonomyOptions($data);

        return back()->with('success', 'مقاله به‌روزرسانی شد.');
    }

    public function destroy(int $id)
    {
        Article::findOrFail($id)->delete();

        return back()->with('success', 'مقاله حذف شد.');
    }

    public function uploadEmbeddedImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        return response()->json([
            'url' => $this->storeUploadedImage($data['image']),
            'alt' => pathinfo($data['image']->getClientOriginalName(), PATHINFO_FILENAME),
        ]);
    }

    public function storeTopic(Request $request)
    {
        ArticleTopic::create($this->validatedTaxonomy($request, ArticleTopic::class));

        return back()->with('success', 'موضوع ذخیره شد.');
    }

    public function updateTopic(Request $request, int $id)
    {
        $topic = ArticleTopic::findOrFail($id);
        $oldName = $topic->name;
        $topic->update($this->validatedTaxonomy($request, ArticleTopic::class, $topic->id));
        $this->renameTaxonomyValue('topics', $oldName, $topic->name);

        return back()->with('success', 'موضوع به‌روزرسانی شد.');
    }

    public function destroyTopic(int $id)
    {
        $topic = ArticleTopic::findOrFail($id);
        $this->removeTaxonomyValue('topics', $topic->name);
        $topic->delete();

        return back()->with('success', 'موضوع حذف شد.');
    }

    public function storeTag(Request $request)
    {
        ArticleTag::create($this->validatedTaxonomy($request, ArticleTag::class));

        return back()->with('success', 'تگ ذخیره شد.');
    }

    public function updateTag(Request $request, int $id)
    {
        $tag = ArticleTag::findOrFail($id);
        $oldName = $tag->name;
        $tag->update($this->validatedTaxonomy($request, ArticleTag::class, $tag->id));
        $this->renameTaxonomyValue('tags', $oldName, $tag->name);

        return back()->with('success', 'تگ به‌روزرسانی شد.');
    }

    public function destroyTag(int $id)
    {
        $tag = ArticleTag::findOrFail($id);
        $this->removeTaxonomyValue('tags', $tag->name);
        $tag->delete();

        return back()->with('success', 'تگ حذف شد.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:articles,slug'.($ignoreId ? ','.$ignoreId : '');

        $data = $request->validate([
            'title' => 'required|string|max:180',
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[A-Za-z0-9\\-_]+$/', $unique],
            'summary' => 'nullable|string|max:500',
            'thumbnail_image' => 'nullable|string|max:500',
            'thumbnail_upload' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'body_image' => 'nullable|string|max:500',
            'body_upload' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'body' => 'required|string|max:20000',
            'tags' => 'nullable|string|max:500',
            'topics' => 'nullable|string|max:500',
            'is_published' => 'nullable|boolean',
        ]);

        if ($request->hasFile('thumbnail_upload')) {
            $data['thumbnail_image'] = $this->storeUploadedImage($request->file('thumbnail_upload'));
        }

        if ($request->hasFile('body_upload')) {
            $data['body_image'] = $this->storeUploadedImage($request->file('body_upload'));
        }

        unset($data['thumbnail_upload'], $data['body_upload']);

        $data['slug'] = $data['slug'] ?: (Str::slug($data['title']) ?: Str::random(8));
        $data['body'] = $this->cleanBody($data['body']);
        $data['tags'] = $this->splitList($data['tags'] ?? '');
        $data['topics'] = $this->splitList($data['topics'] ?? '');
        $data['is_published'] = $request->boolean('is_published');
        $data['published_at'] = $data['is_published'] ? now() : null;

        return $data;
    }

    private function splitList(string $value): array
    {
        return collect(preg_split('/[,،\\n]+/u', $value))
            ->map(fn ($item) => trim($item))
            ->filter(fn ($item) => $item !== '' && Article::taxonomySlug($item) !== '')
            ->unique(fn ($item) => Article::taxonomySlug($item))
            ->values()
            ->all();
    }

    private function listValues(string $field): array
    {
        $model = $field === 'topics' ? ArticleTopic::class : ArticleTag::class;

        return collect($model::query()->orderBy('name')->pluck('name')->all())
            ->merge(Article::query()
                ->pluck($field)
                ->flatMap(fn ($items) => $items ?: [])
                ->all())
            ->map(fn ($item) => preg_replace('/\s+/u', ' ', trim((string) $item)))
            ->filter(fn (?string $item) => $item && Article::taxonomySlug($item) !== '')
            ->unique(fn (string $item) => Article::taxonomySlug($item))
            ->sort()
            ->values()
            ->all();
    }

    private function listTaxonomies(string $field): array
    {
        $model = $field === 'topics' ? ArticleTopic::class : ArticleTag::class;
        $counts = Article::query()
            ->pluck($field)
            ->flatMap(fn ($items) => $items ?: [])
            ->map(fn ($item) => Article::taxonomySlug((string) $item))
            ->countBy();

        return $model::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'article_count' => (int) ($counts[$item->slug] ?? 0),
            ])
            ->values()
            ->all();
    }

    private function validatedTaxonomy(Request $request, string $model, ?int $ignoreId = null): array
    {
        $table = $model === ArticleTopic::class ? 'article_topics' : 'article_tags';

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $data['name'] = preg_replace('/\s+/u', ' ', trim($data['name']));
        $data['slug'] = Article::taxonomySlug($data['name']);

        $request->validate([
            'name' => [
                function (string $attribute, mixed $value, \Closure $fail) use ($table, $data, $ignoreId) {
                    if ($data['slug'] === '') {
                        $fail('نام معتبر نیست.');
                        return;
                    }

                    $exists = \Illuminate\Support\Facades\DB::table($table)
                        ->where('slug', $data['slug'])
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('این مورد قبلاً ثبت شده است.');
                    }
                },
            ],
        ]);

        return $data;
    }

    private function syncTaxonomyOptions(array $data): void
    {
        foreach ($data['topics'] ?? [] as $topic) {
            ArticleTopic::firstOrCreate(
                ['slug' => Article::taxonomySlug($topic)],
                ['name' => $topic],
            );
        }

        foreach ($data['tags'] ?? [] as $tag) {
            ArticleTag::firstOrCreate(
                ['slug' => Article::taxonomySlug($tag)],
                ['name' => $tag],
            );
        }
    }

    private function renameTaxonomyValue(string $field, string $oldName, string $newName): void
    {
        $oldSlug = Article::taxonomySlug($oldName);

        Article::query()->get()->each(function (Article $article) use ($field, $oldSlug, $newName) {
            $values = collect($article->{$field} ?: [])
                ->map(fn ($item) => Article::taxonomySlug((string) $item) === $oldSlug ? $newName : $item)
                ->map(fn ($item) => preg_replace('/\s+/u', ' ', trim((string) $item)))
                ->filter(fn (?string $item) => $item && Article::taxonomySlug($item) !== '')
                ->unique(fn (string $item) => Article::taxonomySlug($item))
                ->values()
                ->all();

            if ($values !== ($article->{$field} ?: [])) {
                $article->forceFill([$field => $values])->save();
            }
        });
    }

    private function removeTaxonomyValue(string $field, string $name): void
    {
        $slug = Article::taxonomySlug($name);

        Article::query()->get()->each(function (Article $article) use ($field, $slug) {
            $values = collect($article->{$field} ?: [])
                ->reject(fn ($item) => Article::taxonomySlug((string) $item) === $slug)
                ->values()
                ->all();

            if ($values !== ($article->{$field} ?: [])) {
                $article->forceFill([$field => $values])->save();
            }
        });
    }

    private function cleanBody(string $body): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="article-root">'.$body.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('article-root');
        if (! $root) {
            return '';
        }

        $this->sanitizeArticleNode($root);

        $clean = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $clean .= $dom->saveHTML($child);
        }

        return trim($clean);
    }

    private function storeUploadedImage($file): string
    {
        return Storage::url($file->store('articles', 'public'));
    }

    private function sanitizeArticleNode(\DOMNode $node): void
    {
        $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'a', 'img'];
        $dropWithContent = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'meta', 'link'];

        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag = strtolower($child->nodeName);
            if (in_array($tag, $dropWithContent, true)) {
                $node->removeChild($child);
                continue;
            }

            $this->sanitizeArticleNode($child);

            if (! in_array($tag, $allowedTags, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            if (! $this->sanitizeArticleElement($child)) {
                $node->removeChild($child);
            }
        }
    }

    private function sanitizeArticleElement(\DOMElement $element): bool
    {
        $tag = strtolower($element->nodeName);
        $allowedAttrs = [
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt'],
        ];

        foreach (iterator_to_array($element->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            if (str_starts_with($name, 'on') || $name === 'style' || ! in_array($name, $allowedAttrs[$tag] ?? [], true)) {
                $element->removeAttributeNode($attr);
            }
        }

        if ($tag === 'a') {
            $href = trim($element->getAttribute('href'));
            if ($href !== '' && ! $this->isAllowedArticleHref($href)) {
                $element->removeAttribute('href');
            }
            $element->setAttribute('rel', 'noopener noreferrer');
        }

        if ($tag === 'img' && ! $this->isAllowedArticleImageSource(trim($element->getAttribute('src')))) {
            return false;
        }

        return true;
    }

    private function isAllowedArticleHref(string $href): bool
    {
        return Str::startsWith($href, ['/', 'http://', 'https://', 'mailto:', 'tel:']);
    }

    private function isAllowedArticleImageSource(string $src): bool
    {
        if (Str::startsWith($src, '/storage/articles/')) {
            return true;
        }

        $allowedPrefixes = collect([
            rtrim((string) config('seo.url'), '/'),
            rtrim((string) config('filesystems.disks.public.url'), '/'),
        ])->filter()->unique();

        return $allowedPrefixes->contains(
            fn (string $prefix) => Str::startsWith($src, $prefix.'/storage/articles/')
                || Str::startsWith($src, $prefix.'/articles/')
        );
    }
}
