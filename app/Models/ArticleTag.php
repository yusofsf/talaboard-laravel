<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArticleTag extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (ArticleTag $tag) {
            $tag->slug = Article::taxonomySlug($tag->slug ?: $tag->name);
        });
    }
}
