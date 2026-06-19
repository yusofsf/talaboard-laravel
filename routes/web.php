<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// تابلوی قیمت (عمومی)
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/api/prices', [HomeController::class, 'prices'])->name('prices.api');

// احراز هویت
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->name('forgot-password');
    Route::post('/forgot-password', [AuthController::class, 'forgot']);
    Route::get('/reset-password', [AuthController::class, 'resetForm'])->name('reset-password');
    Route::post('/reset-password', [AuthController::class, 'reset']);
});

Route::get('/verify-otp', [AuthController::class, 'otpForm'])->name('verify-otp');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
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

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/read/all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/read/{id}', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/membership', [MembershipController::class, 'show'])->name('membership');
    Route::post('/membership', [MembershipController::class, 'activate']);
});

// پنل ادمین
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::post('/set-level/{uid}', [AdminController::class, 'setLevel'])->name('set-level');
    Route::post('/wallet-credit', [AdminController::class, 'walletCredit'])->name('wallet-credit');
    Route::post('/notify', [AdminController::class, 'notify'])->name('notify');
    Route::delete('/notify/{id}', [AdminController::class, 'deleteNotification'])->name('notify.delete');
    Route::post('/generate-code', [AdminController::class, 'generateCode'])->name('generate-code');
});
