<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'title', 'slug', 'summary', 'thumbnail_image', 'body_image', 'body',
        'tags', 'topics', 'is_published', 'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'topics' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Article $article) {
            if (blank($article->slug)) {
                $article->slug = Str::slug($article->title) ?: Str::random(8);
            }

            if ($article->is_published && blank($article->published_at)) {
                $article->published_at = now();
            }
        });
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public static function taxonomySlug(string $value): string
    {
        return Str::of($value)
            ->replace(['ي', 'ك'], ['ی', 'ک'])
            ->lower()
            ->trim()
            ->replaceMatches('/[^\pL\pN]+/u', '-')
            ->trim('-')
            ->toString();
    }
}
