<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleTopic extends Model
{
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (ArticleTopic $topic) {
            $topic->slug = Article::taxonomySlug($topic->slug ?: $topic->name);
        });
    }
}
