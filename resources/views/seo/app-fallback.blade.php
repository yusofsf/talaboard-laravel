@php
    $component = $component ?? ($page['component'] ?? '');
    $prices = is_array($props['prices'] ?? null) ? $props['prices'] : [];
    $isHome = $component === 'Home';
    $isTrade = $component === 'Trade';
    $heading = $seo['heading'] ?? $title;
    $priceCards = $isHome ? [
        ['label' => 'قیمت گرم طلا', 'value' => data_get($prices, 'gold.geram'), 'href' => '/gold-gram-price'],
        ['label' => 'قیمت مثقال طلا', 'value' => data_get($prices, 'gold.mithqal'), 'href' => '/gold-mithqal-price'],
        ['label' => 'قیمت سکه تمام', 'value' => data_get($prices, 'gold.bahar'), 'href' => '/full-coin-price'],
        ['label' => 'قیمت گرم نقره ۹۹۹', 'value' => data_get($prices, 'silver.gram_999'), 'href' => '/silver-999-price'],
        ['label' => 'قیمت گرم نقره ۹۹۵', 'value' => data_get($prices, 'silver.gram_995'), 'href' => '/silver-995-price'],
    ] : [];
@endphp

<main data-server-rendered-app-fallback style="max-width: 1120px; margin: 0 auto; padding: 24px 18px">
    <nav aria-label="منوی اصلی" style="display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 28px">
        <a href="/">تابلوی قیمت</a>
        <a href="/gold-prices">قیمت طلا</a>
        <a href="/silver-prices">قیمت نقره</a>
        <a href="/coin-prices">قیمت سکه</a>
        <a href="/calculator">ماشین حساب</a>
        <a href="/articles">مقالات</a>
        <a href="/contact">تماس با ما</a>
    </nav>

    <header>
        <h1>{{ $heading }}</h1>
        <p>{{ $description }}</p>
    </header>

    @if ($isHome)
        <section aria-labelledby="server-price-board-title">
            <h2 id="server-price-board-title">آخرین قیمت طلا، نقره و سکه</h2>
            <ul>
                @foreach ($priceCards as $card)
                    <li>
                        <a href="{{ $card['href'] }}">{{ $card['label'] }}</a>:
                        <strong>{{ is_numeric($card['value']) ? number_format($card['value']) . ' تومان' : 'در حال به‌روزرسانی' }}</strong>
                    </li>
                @endforeach
            </ul>
        </section>
        <section aria-labelledby="server-home-guides-title">
            <h2 id="server-home-guides-title">راهنمای قیمت و معامله</h2>
            <p>برای مشاهده جزئیات هر بازار، نرخ خرید و فروش، واحد اندازه‌گیری و راهنمای معامله وارد صفحه تخصصی همان کالا شوید.</p>
            <p>
                <a href="/buy-gold">راهنمای خرید طلا</a>،
                <a href="/sell-gold">راهنمای فروش طلا</a>،
                <a href="/buy-silver">راهنمای خرید نقره</a> و
                <a href="/sell-silver">راهنمای فروش نقره</a>
            </p>
        </section>
    @elseif ($isTrade)
        @php
            $tradeLabel = data_get($props, 'meta.label', 'کالا');
            $sellPrice = $props['sellPrice'] ?? null;
            $buyPrice = $props['buyPrice'] ?? null;
        @endphp
        <section aria-labelledby="server-trade-price-title">
            <h2 id="server-trade-price-title">نرخ فعلی {{ $tradeLabel }}</h2>
            <p>قیمت فروش مجموعه: <strong>{{ is_numeric($sellPrice) ? number_format($sellPrice) . ' تومان' : 'در حال به‌روزرسانی' }}</strong></p>
            <p>قیمت خرید مجموعه: <strong>{{ is_numeric($buyPrice) ? number_format($buyPrice) . ' تومان' : 'در حال به‌روزرسانی' }}</strong></p>
            <p>قیمت قطعی سفارش هنگام ثبت درخواست دوباره از منبع زنده بررسی می‌شود.</p>
        </section>
    @elseif ($component === 'About' && is_array($props['content'] ?? null))
        <section>
            <h2>{{ data_get($props, 'content.title') }}</h2>
            <p>{!! nl2br(e((string) data_get($props, 'content.body'))) !!}</p>
        </section>
    @endif

    @unless ($isHome)
        <section aria-labelledby="server-related-pages-title">
            <h2 id="server-related-pages-title">صفحات مرتبط</h2>
            <p>
                <a href="/gold-prices">قیمت روز طلا</a>،
                <a href="/silver-prices">قیمت روز نقره</a>،
                <a href="/coin-prices">قیمت انواع سکه</a> و
                <a href="/articles">راهنماهای بازار</a>
            </p>
        </section>
    @endunless
</main>
