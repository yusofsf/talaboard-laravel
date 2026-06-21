// تبدیل تاریخ شمسی↔میلادی.
// محاسبه‌ی نوروز هر سال با الگوریتم استاندارد jalCal (بدون کتابخانه‌ی خارجی)،
// و محاسبات روزشمار با Date بومی جاوااسکریپت (که برای تقویم میلادی قطعاً درست است) —
// فرمول‌های دستی g2d/d2g/d2j جایگزین‌شده چون برای تاریخ‌های قدیمی‌تر (پیش از ۱۳۸۰) خطای
// یک‌ساله/یک‌روزه می‌دادند.

const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

const div = (a, b) => Math.floor(a / b);
const mod = (a, b) => a - div(a, b) * b;

function jalCal(jy) {
    const bl = BREAKS.length;
    const gy = jy + 621;
    let leapJ = -14;
    let jp = BREAKS[0];
    let jump = 0;
    for (let i = 1; i < bl; i += 1) {
        const jm = BREAKS[i];
        jump = jm - jp;
        if (jy < jm) break;
        leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
        jp = jm;
    }
    let n = jy - jp;
    leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
    if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
    const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
    const march = 20 + leapJ - leapG;
    if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
    let leap = mod(mod(n + 1, 33) - 1, 4);
    if (leap === -1) leap = 4;
    return { leap, gy, march };
}

function epochDays(gy, gm, gd) {
    return Math.floor(Date.UTC(gy, gm - 1, gd) / 86400000);
}

function nowruzEpoch(jy) {
    const r = jalCal(jy);
    return epochDays(r.gy, 3, r.march);
}

export function jalaliToGregorian(jy, jm, jd) {
    const dayOffset = jm <= 6
        ? (jm - 1) * 31 + (jd - 1)
        : 6 * 31 + (jm - 7) * 30 + (jd - 1);
    const d = new Date((nowruzEpoch(jy) + dayOffset) * 86400000);
    return { gy: d.getUTCFullYear(), gm: d.getUTCMonth() + 1, gd: d.getUTCDate() };
}

export function gregorianToJalali(gy, gm, gd) {
    const target = epochDays(gy, gm, gd);
    let jy = gy - 621;
    while (nowruzEpoch(jy + 1) <= target) jy += 1;
    while (nowruzEpoch(jy) > target) jy -= 1;

    const offset = target - nowruzEpoch(jy);
    if (offset <= 185) return { jy, jm: 1 + div(offset, 31), jd: mod(offset, 31) + 1 };
    const k = offset - 186;
    return { jy, jm: 7 + div(k, 30), jd: mod(k, 30) + 1 };
}

export function isLeapJalaliYear(jy) {
    return jalCal(jy).leap === 0;
}

export function jalaliMonthLength(jy, jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return isLeapJalaliYear(jy) ? 30 : 29;
}

export const JALALI_MONTHS = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
];

export function todayJalali() {
    const now = new Date();
    return gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
}
