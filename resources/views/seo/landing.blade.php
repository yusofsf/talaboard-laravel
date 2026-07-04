@php
    $siteUrl = rtrim(config('seo.url'), '/');
    $logo = $siteUrl . config('seo.logo');
    $cards = [
        'silver-prices' => [
            ['label' => 'گرم نقره ۹۹۹', 'value' => data_get($prices, 'silver.gram_999'), 'href' => '/trade/gram_999'],
            ['label' => 'مثقال نقره ۹۹۹', 'value' => data_get($prices, 'silver.mithqal_999'), 'href' => '/trade/mithqal_999'],
            ['label' => 'گرم نقره ۹۹۵', 'value' => data_get($prices, 'silver.gram_995'), 'href' => '/trade/gram_995'],
            ['label' => 'مثقال نقره ۹۹۵', 'value' => data_get($prices, 'silver.mithqal_995'), 'href' => '/trade/mithqal_995'],
        ],
        'gold-prices' => [
            ['label' => 'گرم طلا', 'value' => data_get($prices, 'gold.geram'), 'href' => '/trade/geram'],
            ['label' => 'مثقال طلا', 'value' => data_get($prices, 'gold.mithqal'), 'href' => '/trade/mithqal'],
        ],
        'coin-prices' => [
            ['label' => 'سکه تمام', 'value' => data_get($prices, 'gold.bahar'), 'href' => '/trade/bahar'],
            ['label' => 'نیم سکه', 'value' => data_get($prices, 'gold.nim'), 'href' => '/trade/nim'],
            ['label' => 'ربع سکه', 'value' => data_get($prices, 'gold.rob'), 'href' => '/trade/rob'],
        ],
    ][$pageKey] ?? [];
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $meta['title'] }}</title>
<meta name="description" content="{{ $meta['description'] }}">
<meta name="robots" content="index, follow, max-image-preview:large">
<link rel="canonical" href="{{ $meta['canonical'] }}">
<link rel="alternate" hreflang="fa-IR" href="{{ $meta['canonical'] }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('seo.site_name') }}">
<meta property="og:title" content="{{ $meta['title'] }}">
<meta property="og:description" content="{{ $meta['description'] }}">
<meta property="og:url" content="{{ $meta['canonical'] }}">
<meta property="og:image" content="{{ $logo }}">
<meta property="og:locale" content="fa_IR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $meta['title'] }}">
<meta name="twitter:description" content="{{ $meta['description'] }}">
<meta name="twitter:image" content="{{ $logo }}">
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
<link rel="icon" href="/logo.jpg" type="image/jpeg">
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<style>
    :root{--bg:#0a0e1a;--panel:#151d3b;--panel2:#1c2550;--line:rgba(255,255,255,.09);--txt:#f4f7ff;--muted:#a2acc7;--gold:#f6cf63;--silver:#d7e0ee}
    *{box-sizing:border-box}body{margin:0;font-family:"Vazirmatn",Tahoma,sans-serif;background:linear-gradient(160deg,#0a0e1a,#111733);color:var(--txt);line-height:2}
    a{color:inherit;text-decoration:none}nav{display:flex;justify-content:space-between;align-items:center;padding:14px 24px;border-bottom:1px solid var(--line);background:rgba(10,14,26,.78);position:sticky;top:0;backdrop-filter:blur(12px)}
    .brand{display:flex;align-items:center;gap:10px;font-weight:800;color:var(--gold)}.brand img{width:36px;height:36px;border-radius:10px;object-fit:cover}.navlinks{display:flex;gap:10px;flex-wrap:wrap;color:var(--muted);font-size:14px}.navlinks a:hover{color:var(--gold)}
    main{max-width:1120px;margin:0 auto;padding:44px 18px 72px}.hero{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:28px;align-items:center}.eyebrow{color:var(--gold);font-weight:800;margin-bottom:8px}
    h1{font-size:clamp(30px,4.5vw,58px);line-height:1.45;margin:0 0 16px}p{margin:0 0 16px;color:var(--muted)}.cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}
    .btn{padding:11px 18px;border-radius:12px;background:linear-gradient(135deg,#f6cf63,#c79a2e);color:#1a1200;font-weight:800}.btn.secondary{background:rgba(255,255,255,.05);color:var(--txt);border:1px solid var(--line)}
    .prices{display:grid;gap:12px}.price{background:linear-gradient(160deg,var(--panel),var(--panel2));border:1px solid var(--line);border-radius:16px;padding:18px}.label{color:var(--muted);font-size:14px}.value{font-size:28px;font-weight:900;color:var(--silver);margin-top:4px}.unit{font-size:12px;color:var(--muted)}
    section{margin-top:42px}.content{background:rgba(255,255,255,.035);border:1px solid var(--line);border-radius:18px;padding:24px}.content h2{margin:0 0 14px;font-size:24px}.chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}.chip{border:1px solid var(--line);border-radius:999px;padding:5px 12px;color:var(--muted);font-size:13px}
    .links{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.linkcard{background:rgba(255,255,255,.04);border:1px solid var(--line);border-radius:14px;padding:16px}.linkcard strong{display:block;color:var(--gold);margin-bottom:6px}
    @media(max-width:760px){nav{align-items:flex-start;gap:12px;flex-direction:column}.hero{grid-template-columns:1fr}.links{grid-template-columns:1fr}main{padding-top:28px}.value{font-size:24px}}
</style>
</head>
<body>
<nav>
    <a class="brand" href="/"><img src="/logo.jpg" alt="آبشده صفرپور"><span>آبشده صفرپور</span></a>
    <div class="navlinks">
        <a href="/">تابلوی قیمت</a>
        <a href="/silver-prices">قیمت نقره</a>
        <a href="/gold-prices">قیمت طلا</a>
        <a href="/coin-prices">قیمت سکه</a>
        <a href="/calculator">ماشین حساب</a>
    </div>
</nav>
<main>
    <div class="hero">
        <div>
            <div class="eyebrow">قیمت لحظه‌ای و خرید و فروش آنلاین</div>
            <h1>{{ $meta['heading'] }}</h1>
            <p>{{ $meta['description'] }}</p>
            <p>
                در آبشده صفرپور قیمت‌ها به‌صورت دوره‌ای به‌روزرسانی می‌شوند تا برای تصمیم‌گیری در خرید و فروش طلا، نقره و سکه
                یک مرجع سریع، شفاف و قابل اتکا داشته باشید.
            </p>
            <div class="cta">
                <a class="btn" href="/">مشاهده تابلوی قیمت</a>
                <a class="btn secondary" href="/calculator">محاسبه قیمت</a>
            </div>
        </div>
        <div class="prices">
            @foreach ($cards as $card)
                <a class="price" href="{{ $card['href'] }}">
                    <div class="label">{{ $card['label'] }}</div>
                    <div class="value">{{ is_numeric($card['value']) ? number_format($card['value']) : 'به‌روزرسانی می‌شود' }}</div>
                    <div class="unit">تومان</div>
                </a>
            @endforeach
        </div>
    </div>

    <section class="content">
        <h2>راهنمای سریع {{ $meta['heading'] }}</h2>
        <p>
            برای جست‌وجوهایی مثل {{ implode('، ', array_slice($meta['keywords'] ?? [], 0, 4)) }}، صفحه‌های تخصصی به موتور جست‌وجو کمک می‌کنند
            موضوع هر بخش را دقیق‌تر بفهمد. این صفحه به‌صورت اختصاصی روی همین عبارت‌ها و نیاز کاربران بازار فلزات گران‌بها تمرکز دارد.
        </p>
        <p>
            قیمت نهایی معامله ممکن است بر اساس موجودی، کارمزد، شرایط بازار و زمان ثبت سفارش تغییر کند؛ برای اقدام نهایی از صفحه معامله یا تماس با مجموعه استفاده کنید.
        </p>
        <div class="chips">
            @foreach (($meta['keywords'] ?? []) as $keyword)
                <span class="chip">{{ $keyword }}</span>
            @endforeach
        </div>
    </section>

    <section class="links">
        <a class="linkcard" href="/silver-prices"><strong>قیمت نقره</strong><span>نقره ۹۹۹، نقره ۹۹۵، مثقال و گرم نقره</span></a>
        <a class="linkcard" href="/gold-prices"><strong>قیمت طلا</strong><span>گرم طلا، مثقال طلا و طلای آبشده</span></a>
        <a class="linkcard" href="/coin-prices"><strong>قیمت سکه</strong><span>سکه تمام، نیم سکه و ربع سکه</span></a>
    </section>
</main>
</body>
</html>
