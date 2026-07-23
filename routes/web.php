<?php

use App\Http\Controllers\AdminArticleController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryIncreaseRequestController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeoPageController;
use App\Http\Controllers\SilverDeliveryController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\TradeRoomController;
use App\Http\Controllers\WalletController;
use App\Models\Article;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// تابلوی قیمت (عمومی)
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/api/prices', [HomeController::class, 'prices'])->name('prices.api');
Route::get('/calculator', fn () => Inertia::render('Calculator', [
    'seo' => [
        ...config('seo.public_pages.calculator'),
        'canonical' => rtrim(config('seo.url'), '/').'/calculator',
    ],
]))->name('calculator');
Route::get('/chart', fn () => Inertia::render('Chart'))->name('chart');
Route::get('/speed-test', fn () => Inertia::render('SpeedTest'))->name('speed-test');
Route::get('/about', fn () => Inertia::render('About', [
    'content' => [
        'title' => Setting::get('about_title', config('page_content.about.title')),
        'body' => Setting::get('about_body', config('page_content.about.body')),
    ],
]))->name('about');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:5,1')->name('contact.send');
Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/article', fn () => redirect()->route('articles.index'))->name('articles.alias');
Route::get('/articles/topic/{slug}', [ArticleController::class, 'topic'])->name('articles.topic');
Route::get('/articles/tag/{slug}', [ArticleController::class, 'tag'])->name('articles.tag');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::get('/trade/{item}', [TradeController::class, 'show'])->name('trade');
Route::get('/silver-prices', [SeoPageController::class, 'show'])->defaults('page', 'silver-prices')->name('seo.silver');
Route::get('/gold-prices', [SeoPageController::class, 'show'])->defaults('page', 'gold-prices')->name('seo.gold');
Route::get('/coin-prices', [SeoPageController::class, 'show'])->defaults('page', 'coin-prices')->name('seo.coin');

foreach (config('seo.keyword_pages', []) as $page => $meta) {
    Route::get($meta['path'], [SeoPageController::class, 'show'])
        ->defaults('page', $page)
        ->name("seo.keyword.{$page}");
}

// سئو: sitemap و robots از طریق روت سرو می‌شوند تا مستقل از سیملینکِ public_html کار کنند
// (در پروداکشن public_html جداست؛ فایل استاتیک بدون سیملینک ۴۰۴ می‌شد و گوگل «could not be read» می‌داد)
Route::get('/sitemap.xml', function () {
    $siteUrl = rtrim(config('seo.url'), '/');
    $articles = Article::published()->orderByDesc('published_at')->get();
    $taxonomyPages = collect(['topic' => 'topics', 'tag' => 'tags'])
        ->flatMap(fn (string $field, string $type) => $articles
            ->pluck($field)
            ->flatten()
            ->filter()
            ->map(fn (string $value) => Article::taxonomySlug($value))
            ->filter()
            ->unique()
            ->map(fn (string $slug) => [
                'path' => '/articles/'.$type.'/'.rawurlencode($slug),
                'changefreq' => 'weekly',
                'priority' => '0.58',
            ]))
        ->values()
        ->all();
    $tradePages = collect(['mithqal', 'geram', 'bahar', 'nim', 'rob', 'mithqal_999', 'gram_999', 'mithqal_995', 'gram_995'])
        ->map(fn (string $item) => [
            'path' => '/trade/'.$item,
            'changefreq' => 'hourly',
            'priority' => '0.74',
        ])
        ->all();
    $pages = [
        ...config('seo.public_pages', []),
        ...config('seo.keyword_pages', []),
        [
            'path' => '/articles',
            'changefreq' => 'daily',
            'priority' => '0.72',
        ],
        ...$tradePages,
        ...$taxonomyPages,
        ...$articles
            ->map(fn (Article $article) => [
                'path' => '/articles/'.$article->slug,
                'lastmod' => optional($article->updated_at)->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.64',
            ])
            ->all(),
    ];
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

    foreach ($pages as $page) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>'.e($siteUrl.$page['path'])."</loc>\n";

        if (! empty($page['lastmod'])) {
            $xml .= '    <lastmod>'.e($page['lastmod'])."</lastmod>\n";
        }

        $xml .= '    <changefreq>'.e($page['changefreq'] ?? 'weekly')."</changefreq>\n";
        $xml .= '    <priority>'.e($page['priority'] ?? '0.6')."</priority>\n";
        $xml .= "  </url>\n";
    }

    $xml .= '</urlset>'."\n";

    return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('sitemap');
Route::get('/robots.txt', fn () => response(file_get_contents(public_path('robots.txt')), 200, [
    'Content-Type' => 'text/plain; charset=UTF-8',
]))->name('robots');

Route::get('/pwa.webmanifest', fn () => response(json_encode([
    'id' => '/',
    'name' => 'آبشده صفرپور | قیمت طلا و نقره',
    'short_name' => 'آبشده صفرپور',
    'description' => 'تابلوی قیمت لحظه‌ای و خرید و فروش آنلاین طلا و نقره',
    'lang' => 'fa-IR',
    'dir' => 'rtl',
    'start_url' => '/',
    'scope' => '/',
    'display' => 'standalone',
    'background_color' => '#0b0e14',
    'theme_color' => '#0b0e14',
    'icons' => [
        [
            'src' => '/pwa-icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => '/pwa-icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => '/pwa-icon-maskable-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 200, [
    'Content-Type' => 'application/manifest+json; charset=UTF-8',
]))->name('pwa.manifest');

Route::get('/pwa-icon-{size}.png', function (string $size) {
    if (! in_array($size, ['192', '512', 'maskable-512'], true)) {
        abort(404);
    }

    $pixels = str_starts_with($size, 'maskable') ? 512 : (int) $size;
    $logo = imagecreatefromjpeg(public_path('logo.jpg'));
    $width = imagesx($logo);
    $height = imagesy($logo);
    $side = min($width, $height);
    $sourceX = (int) (($width - $side) / 2);
    $sourceY = (int) (($height - $side) / 2);
    $canvas = imagecreatetruecolor($pixels, $pixels);

    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

    if ($size === 'maskable-512') {
        $inner = 410;
        $offset = (int) (($pixels - $inner) / 2);
        imagecopyresampled($canvas, $logo, $offset, $offset, $sourceX, $sourceY, $inner, $inner, $side, $side);
    } else {
        imagecopyresampled($canvas, $logo, 0, 0, $sourceX, $sourceY, $pixels, $pixels, $side, $side);
    }

    ob_start();
    imagepng($canvas, null, 9);
    $png = ob_get_clean();
    imagedestroy($canvas);
    imagedestroy($logo);

    return response($png, 200, [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('size', '192|512|maskable-512')->name('pwa.icon');

// احراز هویت
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->name('forgot-password');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:5,1');
    Route::get('/reset-password', [AuthController::class, 'resetForm'])->name('reset-password');
    // محدودسازی شدید: جلوگیری از حدس‌زدن کد ۶ رقمی بازنشانی رمز (account takeover)
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:8,1');
});

Route::get('/verify-otp', [AuthController::class, 'otpForm'])->name('verify-otp');
// محدودسازی شدید: جلوگیری از brute-force کد ۶ رقمی ورود دو مرحله‌ای
Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:8,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// صفحات احراز هویت‌شده
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile/info', [ProfileController::class, 'updateInfo'])->name('profile.info');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/bank-cards', [ProfileController::class, 'storeBankCard'])->name('profile.bank-cards.store');
    Route::delete('/profile/bank-cards/{id}', [ProfileController::class, 'destroyBankCard'])->name('profile.bank-cards.destroy');

    Route::get('/history', [HistoryController::class, 'index'])->name('history');
    Route::get('/cart', [CartController::class, 'index'])->name('cart');
    Route::post('/cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');
    Route::delete('/cart/{id}', [CartController::class, 'destroy'])->name('cart.destroy');

    Route::post('/trade/{item}', [TradeController::class, 'store']);

    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet');
    Route::get('/accounting', [WalletController::class, 'accounting'])->name('accounting');
    Route::post('/wallet/withdraw', [WalletController::class, 'requestWithdrawal'])->name('wallet.withdraw');
    Route::post('/wallet/deposit', [WalletController::class, 'requestDeposit'])->name('wallet.deposit');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/read/all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/read/{id}', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/membership', [MembershipController::class, 'show'])->name('membership');
    Route::post('/membership/apply', [MembershipController::class, 'apply'])->name('membership.apply');

    Route::get('/trade-room', [TradeRoomController::class, 'index'])->name('trade-room');
    Route::post('/trade-room', [TradeRoomController::class, 'store'])->name('trade-room.store');
    Route::post('/trade-room/{id}/accept', [TradeRoomController::class, 'accept'])->name('trade-room.accept');
    Route::post('/trade-room/{id}/cancel', [TradeRoomController::class, 'cancel'])->name('trade-room.cancel');

    Route::post('/silver-delivery', [SilverDeliveryController::class, 'store'])->name('silver-delivery.store');
    Route::post('/inventory-increase-requests', [InventoryIncreaseRequestController::class, 'store'])->name('inventory-increase-requests.store');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{id}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{id}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
    Route::post('/tickets/{id}/resolve', [TicketController::class, 'resolve'])->name('tickets.resolve');
});

// پنل ادمین
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/articles', [AdminArticleController::class, 'index'])->name('articles.index');
    Route::post('/articles/embedded-image', [AdminArticleController::class, 'uploadEmbeddedImage'])->name('articles.embedded-image');
    Route::post('/article-topics', [AdminArticleController::class, 'storeTopic'])->name('article-topics.store');
    Route::put('/article-topics/{id}', [AdminArticleController::class, 'updateTopic'])->name('article-topics.update');
    Route::delete('/article-topics/{id}', [AdminArticleController::class, 'destroyTopic'])->name('article-topics.destroy');
    Route::post('/article-tags', [AdminArticleController::class, 'storeTag'])->name('article-tags.store');
    Route::put('/article-tags/{id}', [AdminArticleController::class, 'updateTag'])->name('article-tags.update');
    Route::delete('/article-tags/{id}', [AdminArticleController::class, 'destroyTag'])->name('article-tags.destroy');
    Route::post('/articles', [AdminArticleController::class, 'store'])->name('articles.store');
    Route::put('/articles/{id}', [AdminArticleController::class, 'update'])->name('articles.update');
    Route::delete('/articles/{id}', [AdminArticleController::class, 'destroy'])->name('articles.destroy');
    Route::get('/online-users', [AdminController::class, 'onlineUsers'])->name('online-users');
    Route::get('/accounting', [AdminController::class, 'accounting'])->name('accounting');
    Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
    Route::get('/tickets/{id}', [AdminController::class, 'ticketShow'])->name('tickets.show');
    Route::post('/tickets/{id}/reply', [AdminController::class, 'ticketReply'])->name('tickets.reply');
    Route::post('/tickets/{id}/close', [AdminController::class, 'ticketClose'])->name('tickets.close');
    Route::post('/set-level/{uid}', [AdminController::class, 'setLevel'])->name('set-level');
    Route::post('/wallet-credit', [AdminController::class, 'walletCredit'])->name('wallet-credit');
    Route::post('/inventory-adjust/{uid}', [AdminController::class, 'inventoryAdjust'])->name('inventory-adjust');
    Route::post('/notify', [AdminController::class, 'notify'])->name('notify');
    Route::post('/notify/{id}/update', [AdminController::class, 'updateNotification'])->name('notify.update');
    Route::delete('/notify/{id}', [AdminController::class, 'deleteNotification'])->name('notify.delete');
    Route::post('/membership/approve/{uid}', [AdminController::class, 'membershipApprove'])->name('membership.approve');
    Route::post('/membership/reject/{uid}', [AdminController::class, 'membershipReject'])->name('membership.reject');
    Route::post('/membership', [AdminController::class, 'membershipStore'])->name('membership.store');
    Route::post('/membership/{uid}', [AdminController::class, 'membershipUpdate'])->name('membership.update');
    Route::get('/membership/files/{uid}/{type}', [AdminController::class, 'membershipFile'])->name('membership.file');
    Route::post('/delivery/{id}/update', [AdminController::class, 'deliveryUpdate'])->name('delivery.update');
    Route::post('/withdrawals/{id}/approve', [AdminController::class, 'withdrawalApprove'])->name('withdrawals.approve');
    Route::post('/withdrawals/{id}/reject', [AdminController::class, 'withdrawalReject'])->name('withdrawals.reject');
    Route::post('/deposits/{id}/approve', [AdminController::class, 'depositApprove'])->name('deposits.approve');
    Route::post('/deposits/{id}/reject', [AdminController::class, 'depositReject'])->name('deposits.reject');
    Route::post('/inventory-increase-requests/{id}/approve', [AdminController::class, 'inventoryIncreaseApprove'])->name('inventory-increase-requests.approve');
    Route::post('/inventory-increase-requests/{id}/reject', [AdminController::class, 'inventoryIncreaseReject'])->name('inventory-increase-requests.reject');

    Route::put('/users/{uid}', [AdminController::class, 'userUpdate'])->name('users.update');
    Route::post('/users/{uid}/password', [AdminController::class, 'userPasswordReset'])->name('users.password.reset');
    Route::delete('/users/{uid}', [AdminController::class, 'userDestroy'])->name('users.destroy');
    Route::get('/users/{uid}/trades', [AdminController::class, 'userTrades'])->name('users.trades');

    Route::put('/transactions/{id}', [AdminController::class, 'transactionUpdate'])->name('transactions.update');
    Route::delete('/transactions/{id}', [AdminController::class, 'transactionDestroy'])->name('transactions.destroy');
    Route::post('/transactions/{id}/reject', [AdminController::class, 'transactionReject'])->name('transactions.reject');
    Route::post('/trade-room/{id}/reject', [AdminController::class, 'tradeRoomReject'])->name('trade-room.reject');
});
