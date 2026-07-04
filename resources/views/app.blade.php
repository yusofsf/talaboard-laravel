@php
    $siteUrl = rtrim(config('seo.url'), '/');
    $siteName = config('seo.site_name');
    $logo = $siteUrl . config('seo.logo');
    $page = $page ?? [];
    $props = $page['props'] ?? [];
    $hasSeo = isset($props['seo']) && is_array($props['seo']);
    $seo = array_replace(config('seo.default'), $hasSeo ? $props['seo'] : [
        'robots' => 'noindex, nofollow',
    ]);
    $canonical = $seo['canonical'] ?? url()->current();
    $title = $seo['title'] ?? config('seo.default.title');
    $description = $seo['description'] ?? config('seo.default.description');
    $schema = $seo['schema'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title inertia>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="author" content="{{ $siteName }}">
<meta name="robots" content="{{ $seo['robots'] ?? 'index, follow, max-image-preview:large' }}">
<link rel="canonical" href="{{ $canonical }}">
<link rel="alternate" hreflang="fa-IR" href="{{ $canonical }}">

<meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $seo['image'] ?? $logo }}">
<meta property="og:locale" content="{{ config('seo.locale') }}">
<meta name="twitter:card" content="{{ config('seo.twitter_card') }}">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $seo['image'] ?? $logo }}">
<meta name="theme-color" content="#0b0e14">

<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'JewelryStore',
    '@id' => $siteUrl . '/#organization',
    'name' => $siteName,
    'alternateName' => 'صفرپور',
    'url' => $siteUrl . '/',
    'logo' => $logo,
    'image' => $logo,
    'description' => config('seo.default.description'),
    'priceRange' => '$$',
    'currenciesAccepted' => 'IRR',
    'areaServed' => 'IR',
    'knowsAbout' => ['طلا', 'نقره', 'نقره آبشده', 'سکه', 'نیم سکه', 'ربع سکه', 'سکه تمام', 'ساچمه نقره', 'نقره عیار ۹۹۹', 'نقره عیار ۹۹۵'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
@if (! empty($schema))
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
@endif

<link rel="icon" href="/logo.jpg" type="image/jpeg">
<link rel="apple-touch-icon" href="/logo.jpg">
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu&display=swap" rel="stylesheet">
@viteReactRefresh
@vite(['resources/js/app.jsx'])
@inertiaHead
</head>
<body>
@inertia

<noscript>
    <main>
        <h1>قیمت لحظه‌ای طلا، نقره و سکه | آبشده صفرپور</h1>
        <p>
            آبشده صفرپور مرجع قیمت لحظه‌ای طلا، قیمت نقره و قیمت سکه و پلتفرم خرید و فروش آنلاین طلا و نقره است.
            در این تابلو نرخ روز طلای آبشده، نقره آبشده، نقره عیار ۹۹۹ و ۹۹۵، سکه تمام، نیم سکه و ربع سکه نمایش داده می‌شود.
        </p>
        <p>
            برای مشاهده نرخ‌های به‌روز، خرید نقره، فروش نقره، خرید طلا، فروش طلا و محاسبه قیمت طلا و نقره از منوی سایت استفاده کنید.
        </p>
    </main>
</noscript>
</body>
</html>
