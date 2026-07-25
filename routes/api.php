<?php

use App\Http\Controllers\TelegramMembershipController;
use Illuminate\Support\Facades\Route;

Route::prefix('telegram')->middleware('throttle:20,1')->group(function () {
    Route::post('/link', [TelegramMembershipController::class, 'link']);
    Route::post('/member', [TelegramMembershipController::class, 'member']);
    Route::post('/inventory-increase', [TelegramMembershipController::class, 'inventoryIncrease']);
});
