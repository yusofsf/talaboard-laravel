<?php

use App\Http\Controllers\PriceApiController;
use App\Http\Controllers\TelegramMembershipController;
use App\Http\Controllers\TokenApiController;
use App\Http\Middleware\ForceHttps;
use Illuminate\Support\Facades\Route;

Route::get('/v1/prices', [PriceApiController::class, 'index'])
    ->middleware([ForceHttps::class, 'price-api.auth', 'throttle:60,1'])
    ->name('api.v1.prices');

Route::prefix('v1')->middleware(ForceHttps::class)->group(function () {
    Route::get('/prices/live', [PriceApiController::class, 'index'])->middleware(['api.token:prices:read', 'throttle:60,1']);
    Route::get('/trade-room/offers', [TokenApiController::class, 'offers'])->middleware(['api.token:trades:read', 'throttle:60,1']);
    Route::post('/trade-room/offers', [TokenApiController::class, 'storeOffer'])->middleware(['api.token:trades:create', 'throttle:20,1']);
    Route::post('/shop/orders', [TokenApiController::class, 'storeShopOrder'])->middleware(['api.token:trades:create', 'throttle:20,1']);
    Route::get('/profile', [TokenApiController::class, 'me'])->middleware(['api.token:profile:read', 'throttle:30,1']);
    Route::get('/user', [TokenApiController::class, 'me'])->middleware(['api.token:profile:read', 'throttle:30,1']);
    Route::get('/wallet', [TokenApiController::class, 'wallet'])->middleware(['api.token:wallet:read', 'throttle:30,1']);
    Route::get('/alerts', [TokenApiController::class, 'alerts'])->middleware(['api.token:alerts:manage', 'throttle:30,1']);
    Route::post('/alerts/{id}/read', [TokenApiController::class, 'markAlertRead'])->middleware(['api.token:alerts:manage', 'throttle:30,1']);
    Route::post('/telegram/connect', [TelegramMembershipController::class, 'connect'])->middleware('throttle:10,1');
});

Route::prefix('telegram')->middleware('throttle:20,1')->group(function () {
    Route::post('/link', [TelegramMembershipController::class, 'link']);
    Route::post('/member', [TelegramMembershipController::class, 'member']);
    Route::post('/inventory-increase', [TelegramMembershipController::class, 'inventoryIncrease']);
    Route::post('/overview', [TelegramMembershipController::class, 'overview']);
    Route::post('/deposits', [TelegramMembershipController::class, 'deposit']);
    Route::post('/receipts', [TelegramMembershipController::class, 'receipt']);
    Route::post('/trade-room/offers', [TelegramMembershipController::class, 'tradeRoomOffers']);
    Route::post('/trade-room/offers/create', [TelegramMembershipController::class, 'tradeRoomOffer']);
    Route::post('/trade-room/offers/{id}/accept', [TelegramMembershipController::class, 'tradeRoomAccept']);
    Route::post('/asset-collaterals', [TelegramMembershipController::class, 'assetCollateral']);
    Route::post('/deliveries', [TelegramMembershipController::class, 'delivery']);
    Route::post('/deliveries/{id}', [TelegramMembershipController::class, 'deliveryStatus']);
});
