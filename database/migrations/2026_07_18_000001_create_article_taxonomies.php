<?php

use App\Models\Article;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $defaultTopics = [
        'نقره',
        'سکه',
        'طلا و آبشده',
        'تحلیل بازار',
        'سرمایه‌گذاری',
        'راهنمای خرید و فروش',
    ];

    public function up(): void
    {
        Schema::create('article_topics', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('article_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        foreach ($this->defaultTopics as $topic) {
            $this->insertTaxonomy('article_topics', $topic);
        }

        DB::table('articles')->orderBy('id')->get()->each(function ($article) {
            $oldTopics = $this->decodeList($article->topics);
            $oldTags = $this->decodeList($article->tags);
            $newTags = $this->uniqueList([...$oldTags, ...$oldTopics]);

            foreach ($newTags as $tag) {
                $this->insertTaxonomy('article_tags', $tag);
            }

            $newTopic = $this->generalTopicFor($article, $newTags);
            $this->insertTaxonomy('article_topics', $newTopic);

            DB::table('articles')->where('id', $article->id)->update([
                'tags' => json_encode($newTags, JSON_UNESCAPED_UNICODE),
                'topics' => json_encode([$newTopic], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_tags');
        Schema::dropIfExists('article_topics');
    }

    private function insertTaxonomy(string $table, string $name): void
    {
        $name = $this->cleanValue($name);
        $slug = Article::taxonomySlug($name);

        if ($name === '' || $slug === '') {
            return;
        }

        DB::table($table)->updateOrInsert(
            ['slug' => $slug],
            ['name' => $name, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private function decodeList(?string $json): array
    {
        $items = json_decode((string) $json, true);

        if (! is_array($items)) {
            return [];
        }

        return $this->uniqueList($items);
    }

    private function uniqueList(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => $this->cleanValue((string) $item))
            ->filter(fn (string $item) => $item !== '' && Article::taxonomySlug($item) !== '')
            ->unique(fn (string $item) => Article::taxonomySlug($item))
            ->values()
            ->all();
    }

    private function cleanValue(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?: '';
    }

    private function generalTopicFor(object $article, array $tags): string
    {
        $haystack = Article::taxonomySlug(implode(' ', [
            $article->title,
            $article->summary,
            strip_tags((string) $article->body),
            ...$tags,
        ]));

        $rules = [
            'نقره' => ['نقره', 'عیار-999', 'عیار-995', '995', '999'],
            'سکه' => ['سکه', 'بهار', 'نیم-سکه', 'ربع-سکه'],
            'طلا و آبشده' => ['طلا', 'آبشده', 'مثقال'],
            'سرمایه‌گذاری' => ['سرمایه', 'سرمایه-گذاری', 'پس-انداز'],
            'تحلیل بازار' => ['تحلیل', 'بازار', 'قیمت', 'اونس', 'دلار'],
        ];

        foreach ($rules as $topic => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, Article::taxonomySlug($needle))) {
                    return $topic;
                }
            }
        }

        return 'راهنمای خرید و فروش';
    }
};
