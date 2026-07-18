<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleTag extends Model
{
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (ArticleTag $tag) {
            $tag->slug = Article::taxonomySlug($tag->slug ?: $tag->name);
        });
    }
}
