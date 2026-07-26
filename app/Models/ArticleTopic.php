<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArticleTopic extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (ArticleTopic $topic) {
            $topic->slug = Article::taxonomySlug($topic->slug ?: $topic->name);
        });
    }
}
