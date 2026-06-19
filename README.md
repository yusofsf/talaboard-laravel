# آبشده صفرپور — Laravel + React

تابلوی قیمت طلا و نقره، همراه با سیستم ثبت معامله، کیف پول، اعلانات، و پنل مدیریت.

---

## معماری

| لایه | فناوری |
|------|--------|
| Backend | Laravel 12 (PHP 8.2+) |
| Frontend | React 19 + Inertia.js |
| Build | Vite |
| Database | SQLite |
| پیامک | Kavenegar |
| استایل | CSS Variables (طراحی تاریک طلایی) |

Inertia.js پل بین Laravel و React است — نیازی به REST API جداگانه نیست.

---

## نصب محلی

```bash
# ۱. وابستگی‌های PHP
composer install

# ۲. وابستگی‌های JS
npm install

# ۳. فایل محیط
cp .env.example .env
php artisan key:generate

# ۴. تنظیم .env: ADMIN_PHONE، TALALAND_* و اختیاری KAVENEGAR_*

# ۵. دیتابیس
touch database/database.sqlite
php artisan migrate

# ۶. ساخت assets
npm run build
# یا برای توسعه با hot-reload:
npm run dev

# ۷. اجرا
php artisan serve
```

---

## ساختار پروژه

```
app/
├── Helpers/Jalali.php              # تبدیل تاریخ میلادی→شمسی + اعداد فارسی
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php      # ثبت‌نام، ورود، OTP، فراموشی رمز
│   │   ├── TradeController.php     # خرید و فروش
│   │   ├── HistoryController.php   # سوابق + حسابداری
│   │   ├── WalletController.php    # کیف پول کاربر
│   │   ├── NotificationController  # اعلانات
│   │   ├── ProfileController.php   # اطلاعات + تغییر رمز
│   │   ├── MembershipController    # عضویت ویژه با کد دعوت
│   │   ├── AdminController.php     # پنل مدیریت
│   │   └── HomeController.php      # تابلوی قیمت
│   └── Middleware/
│       ├── AdminMiddleware.php      # محافظت مسیرهای ادمین
│       └── HandleInertiaRequests   # shared props (user, flash)
├── Models/
│   ├── User · Transaction · OtpToken
│   ├── InviteCode · WalletTransaction
│   └── Notification · NotificationRead
└── Services/
    ├── PriceService.php            # قیمت طلا (Talaland) + دلار (Alanchand)
    └── SmsService.php              # Kavenegar

resources/js/
├── app.jsx + app.css               # entry point + استایل سراسری
├── Layouts/AppLayout.jsx           # ناوبار + flash messages
└── Pages/
    ├── Home.jsx                    # تابلوی قیمت (auto-refresh هر ۳۰ ثانیه)
    ├── Auth/{Login,Register,VerifyOtp,ForgotPassword,ResetPassword}.jsx
    ├── Trade.jsx                   # فرم خرید/فروش با محاسبه لحظه‌ای
    ├── History.jsx                 # تب معاملات + تب حسابداری
    ├── Wallet.jsx · Notifications.jsx · Membership.jsx · Profile.jsx
    └── Admin/Dashboard.jsx         # پنل کامل ادمین با ۵ تب
```

---

## جداول دیتابیس

| جدول | توضیح |
|------|-------|
| `users` | phone (unique)، is_vip، is_admin |
| `transactions` | معاملات خرید/فروش |
| `otp_tokens` | کدهای OTP موقت (۲ دقیقه) |
| `invite_codes` | کدهای دعوت عضویت ویژه |
| `wallet_transactions` | واریز/برداشت (مثبت/منفی) |
| `notifications` | اعلانات (user_id=NULL = عمومی) |
| `notification_reads` | خوانده/نخوانده هر اعلان per کاربر |

---

## متغیرهای محیطی مهم

| متغیر | توضیح |
|-------|-------|
| `ADMIN_PHONE` | شماره ادمین — اولین ورود خودکار ادمین می‌شود |
| `TWO_FA_ENABLED` | true = ورود دو مرحله‌ای با OTP |
| `KAVENEGAR_API_KEY` | کلید API کاوه‌نگار (اگر خالی باشد پیامک ارسال نمی‌شود) |
| `TALALAND_API_BASE` | آدرس API قیمت طلا |
| `TALALAND_USERNAME` | نام کاربری ریسلر تالالند |
| `TALALAND_TOKEN` | توکن API تالالند |
| `CACHE_TTL` | زمان کش قیمت‌ها (ثانیه، پیش‌فرض: ۳۰) |

---

## ویژگی‌های کلیدی

- **تاریخ شمسی** — همه تاریخ‌ها با `Jalali::format()` شمسی و ارقام فارسی
- **OTP دو مرحله‌ای** — Kavenegar lookup (قابل غیرفعال‌سازی)
- **ادمین خودکار** — شماره `ADMIN_PHONE` در اولین ورود ادمین می‌شود
- **اعلان خودکار** — معامله، تغییر اطلاعات، تغییر رمز، تغییر سطح
- **پنل ادمین** — مدیریت کاربران، کیف پول، اعلانات، کدهای دعوت
- **Auto-refresh** — تابلوی قیمت هر ۳۰ ثانیه بروزرسانی می‌شود

---

## deploy روی cPanel

```bash
# ۱. PHP 8.2+ و Composer روی هاست نصب باشد
# ۲. فایل‌ها را آپلود کنید (بدون vendor/ و node_modules/)
# ۳. روی سرور اجرا کنید:
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan config:cache && php artisan route:cache

# ۴. public/build/ را از build محلی آپلود کنید
# ۵. Document Root را به پوشه public/ تنظیم کنید
```
