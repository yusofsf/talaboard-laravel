<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\Article;
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

        return Inertia::render('Admin/Articles', ['articles' => $articles]);
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

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:articles,slug' . ($ignoreId ? ',' . $ignoreId : '');

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
            $data['thumbnail_image'] = Storage::url($request->file('thumbnail_upload')->store('articles', 'public'));
        }

        if ($request->hasFile('body_upload')) {
            $data['body_image'] = Storage::url($request->file('body_upload')->store('articles', 'public'));
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

    private function cleanBody(string $body): string
    {
        $allowedTags = '<p><br><strong><b><em><i><u><h2><h3><ul><ol><li><blockquote><a>';
        $clean = strip_tags($body, $allowedTags);

        $clean = preg_replace('/\s+on\w+\s*=\s*(["\']).*?\1/iu', '', $clean);
        $clean = preg_replace('/\s+style\s*=\s*(["\']).*?\1/iu', '', $clean);
        $clean = preg_replace('/\s+href\s*=\s*(["\'])\s*(?!\/|https?:\/\/|mailto:|tel:)[^"\']*\1/iu', '', $clean);
        $clean = preg_replace('/<a\b(?![^>]*\brel=)/iu', '<a rel="noopener noreferrer"', $clean);

        return trim($clean ?? '');
    }
}
