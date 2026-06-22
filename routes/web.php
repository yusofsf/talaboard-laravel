<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SilverDeliveryController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\TradeRoomController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// تابلوی قیمت (عمومی)
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/api/prices', [HomeController::class, 'prices'])->name('prices.api');
Route::get('/calculator', fn () => \Inertia\Inertia::render('Calculator'))->name('calculator');

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

    Route::get('/history', [HistoryController::class, 'index'])->name('history');

    Route::get('/trade/{item}', [TradeController::class, 'show'])->name('trade');
    Route::post('/trade/{item}', [TradeController::class, 'store']);

    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet');
    Route::post('/wallet/withdraw', [WalletController::class, 'requestWithdrawal'])->name('wallet.withdraw');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::get('/chart', fn () => \Inertia\Inertia::render('Chart'))->name('chart');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/read/all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/read/{id}', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/membership', [MembershipController::class, 'show'])->name('membership');
    Route::post('/membership/apply', [MembershipController::class, 'apply'])->name('membership.apply');

    Route::get('/speed-test', fn () => \Inertia\Inertia::render('SpeedTest'))->name('speed-test');

    Route::get('/trade-room', [TradeRoomController::class, 'index'])->name('trade-room');
    Route::post('/trade-room', [TradeRoomController::class, 'store'])->name('trade-room.store');
    Route::post('/trade-room/{id}/accept', [TradeRoomController::class, 'accept'])->name('trade-room.accept');
    Route::post('/trade-room/{id}/cancel', [TradeRoomController::class, 'cancel'])->name('trade-room.cancel');

    Route::post('/silver-delivery', [SilverDeliveryController::class, 'store'])->name('silver-delivery.store');
});

// پنل ادمین
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::post('/set-level/{uid}', [AdminController::class, 'setLevel'])->name('set-level');
    Route::post('/wallet-credit', [AdminController::class, 'walletCredit'])->name('wallet-credit');
    Route::post('/inventory-adjust/{uid}', [AdminController::class, 'inventoryAdjust'])->name('inventory-adjust');
    Route::post('/notify', [AdminController::class, 'notify'])->name('notify');
    Route::post('/notify/{id}/update', [AdminController::class, 'updateNotification'])->name('notify.update');
    Route::delete('/notify/{id}', [AdminController::class, 'deleteNotification'])->name('notify.delete');
    Route::post('/membership/approve/{uid}', [AdminController::class, 'membershipApprove'])->name('membership.approve');
    Route::post('/membership/reject/{uid}', [AdminController::class, 'membershipReject'])->name('membership.reject');
    Route::post('/delivery/{id}/update', [AdminController::class, 'deliveryUpdate'])->name('delivery.update');
    Route::post('/withdrawals/{id}/approve', [AdminController::class, 'withdrawalApprove'])->name('withdrawals.approve');
    Route::post('/withdrawals/{id}/reject', [AdminController::class, 'withdrawalReject'])->name('withdrawals.reject');

    Route::put('/users/{uid}', [AdminController::class, 'userUpdate'])->name('users.update');
    Route::delete('/users/{uid}', [AdminController::class, 'userDestroy'])->name('users.destroy');
    Route::get('/users/{uid}/trades', [AdminController::class, 'userTrades'])->name('users.trades');

    Route::put('/transactions/{id}', [AdminController::class, 'transactionUpdate'])->name('transactions.update');
    Route::delete('/transactions/{id}', [AdminController::class, 'transactionDestroy'])->name('transactions.destroy');
    Route::post('/transactions/{id}/reject', [AdminController::class, 'transactionReject'])->name('transactions.reject');
    Route::post('/trade-room/{id}/reject', [AdminController::class, 'tradeRoomReject'])->name('trade-room.reject');
});
