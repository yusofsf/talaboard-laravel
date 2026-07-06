<?php

namespace App\Http\Controllers;

use App\Models\PriceSnapshot;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Response;

class SeoPageController extends Controller
{
    public function show(string $page): Response
    {
        $pages = [
            ...config('seo.public_pages', []),
            ...config('seo.keyword_pages', []),
        ];
        abort_unless(isset($pages[$page]), 404);

        $meta = $pages[$page];
        $siteUrl = rtrim(config('seo.url'), '/');
        $canonical = $siteUrl . $meta['path'];
        $prices = Schema::hasTable('price_snapshots')
            ? (PriceSnapshot::latestPayload() ?? [])
            : [];

        return response()->view('seo.landing', [
            'pageKey' => $page,
            'meta' => [
                ...$meta,
                'canonical' => $canonical,
            ],
            'prices' => $prices,
            'schema' => $this->schema($meta, $canonical),
        ]);
    }

    private function schema(array $meta, string $canonical): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $meta['title'],
            'description' => $meta['description'],
            'url' => $canonical,
            'inLanguage' => 'fa-IR',
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => config('seo.site_name'),
                'url' => rtrim(config('seo.url'), '/') . '/',
            ],
            'about' => array_map(
                fn (string $name) => ['@type' => 'Thing', 'name' => $name],
                $meta['keywords'] ?? []
            ),
        ];

        if (! empty($meta['faq'])) {
            $schema = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    $schema,
                    [
                        '@type' => 'FAQPage',
                        'mainEntity' => array_map(fn (array $item) => [
                            '@type' => 'Question',
                            'name' => $item['question'],
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => $item['answer'],
                            ],
                        ], $meta['faq']),
                    ],
                ],
            ];
        }

        return $schema;
    }
}
