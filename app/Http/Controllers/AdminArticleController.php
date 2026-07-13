<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\Article;
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
        ]);
    }

    public function store(Request $request)
    {
        Article::create($this->validated($request));

        return back()->with('success', 'مقاله ذخیره شد.');
    }

    public function update(Request $request, int $id)
    {
        $article = Article::findOrFail($id);
        $article->update($this->validated($request, $article->id));

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
            ->filter()
            ->values()
            ->all();
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

    private function cleanBody(string $body): string
    {
        $allowedTags = '<p><br><strong><b><em><i><u><h2><h3><ul><ol><li><blockquote><a><img>';
        $clean = strip_tags($body, $allowedTags);

        $clean = preg_replace('/\s+on\w+\s*=\s*(["\']).*?\1/iu', '', $clean);
        $clean = preg_replace('/\s+style\s*=\s*(["\']).*?\1/iu', '', $clean);
        $clean = preg_replace('/\s+href\s*=\s*(["\'])\s*(?!\/|https?:\/\/|mailto:|tel:)[^"\']*\1/iu', '', $clean);
        $clean = preg_replace_callback('/<img\b[^>]*>/iu', fn (array $match) => $this->sanitizeImageTag($match[0]), $clean);
        $clean = preg_replace('/<a\b(?![^>]*\brel=)/iu', '<a rel="noopener noreferrer"', $clean);

        return trim($clean ?? '');
    }

    private function storeUploadedImage($file): string
    {
        return Storage::url($file->store('articles', 'public'));
    }

    private function sanitizeImageTag(string $tag): string
    {
        if (! preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/iu', $tag, $srcMatch)) {
            return '';
        }

        $src = trim($srcMatch[2]);

        if (! $this->isAllowedArticleImageSource($src)) {
            return '';
        }

        $cleanTag = '<img src="'.e($src).'"';

        if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/iu', $tag, $altMatch)) {
            $cleanAlt = trim(strip_tags($altMatch[2]));

            if ($cleanAlt !== '') {
                $cleanTag .= ' alt="'.e($cleanAlt).'"';
            }
        }

        return $cleanTag.'>';
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
