# API اتصال ربات تلگرام

ربات نباید به SQLite یا هیچ دیتابیس سایت دسترسی مستقیم داشته باشد. همهٔ درخواست‌ها به `https://metalsp.ir/api/telegram` ارسال می‌شوند و باید این هدر را داشته باشند:

```http
Authorization: Bearer <TELEGRAM_LINK_API_TOKEN>
Accept: application/json
```

بدنهٔ درخواست‌ها JSON است، مگر هنگام ارسال فیش که باید `multipart/form-data` باشد. همهٔ درخواست‌های دارای کاربر باید `telegram_chat_id` را داشته باشند. این شناسه فقط پس از اتصال کاربر از سایت (`POST /link`) معتبر است.

## اطلاعات کاربر و موجودی

`POST /member`

```json
{ "telegram_chat_id": "123456" }
```

اطلاعات اتصال و وضعیت VIP را بازمی‌گرداند.

`POST /overview`

```json
{ "telegram_chat_id": "123456" }
```

پاسخ شامل `wallet_balance` (تومان)، دارایی‌های `gold`، `silver_999` و `silver_995` (گرم)، قیمت‌ها، معاملات اخیر و درخواست‌های افزایش موجودی است.

## افزایش موجودی کیف پول و فیش

`POST /deposits` — ثبت درخواست افزایش موجودی؛ با تأیید ادمین، کیف پول افزایش می‌یابد.

```json
{ "telegram_chat_id": "123456", "amount": 500000, "note": "شماره پیگیری" }
```

فیش را هم‌زمان با فیلد اختیاری `receipt` (تصویر حداکثر ۵ مگابایت) به‌صورت multipart بفرستید، یا پس از پاسخ این API:

`POST /receipts` (multipart): `telegram_chat_id`، `deposit_id` و `receipt`.

## افزایش موجودی انبار و فیش

`POST /inventory-increase` — درخواست تأیید ادمین برای افزودن دارایی به انبار کاربر.

```json
{
  "telegram_chat_id": "123456",
  "item": "gold",
  "quantity": 12.5,
  "note": "خرید حضوری",
  "receipt": "<multipart image, optional>"
}
```

`item`: `gold`، `silver_999`، `silver_995`، `full_coin`، `half_coin`، `quarter_coin`. فیش و مبلغ/مقدار در تب‌های جداگانهٔ «افزایش موجودی ربات» و «افزایش موجودی انبار ربات» پنل مدیریت قابل مشاهده، تأیید یا رد است.

## اتاق معاملاتی

`POST /trade-room/offers` فهرست سفارش‌های باز را برمی‌گرداند.

`POST /trade-room/offers/create` سفارش خرید یا فروش ثبت می‌کند و مبلغ یا دارایی را همان لحظه در امانت رزرو می‌کند.

```json
{
  "telegram_chat_id": "123456",
  "asset": "gold",
  "side": "buy",
  "unit": "gram",
  "quantity": 100,
  "unit_price": 7500000
}
```

`asset`: همان مقادیر بخش انبار؛ `unit`: برای طلا/نقره `gram` یا `mesghal` و برای سکه `piece`. حداقل سفارش طلا و نقره ۱۰۰ گرم است. قیمت مثقال در API به قیمت گرمی تبدیل و فقط مقدار گرمی در سیستم ذخیره می‌شود.

## تحویل از فروشگاه

`POST /deliveries` درخواست تحویل را ثبت و دارایی را تا تعیین تکلیف ادمین رزرو می‌کند.

```json
{
  "telegram_chat_id": "123456",
  "asset": "gold",
  "quantity": 10,
  "recipient_name": "نام گیرنده",
  "phone": "09...",
  "delivery_method": "pickup"
}
```

برای `delivery_method: "address"`، `address` و `postal_code` اجباری‌اند. وضعیت با `POST /deliveries/{id}` و بدنهٔ `{ "telegram_chat_id": "123456" }` خوانده می‌شود. وضعیت‌ها: `pending`، `approved`، `shipped`، `delivered` و `rejected`.
