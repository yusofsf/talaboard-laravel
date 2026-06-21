import { useMemo } from 'react';
import { gregorianToJalali, jalaliMonthLength, jalaliToGregorian, JALALI_MONTHS, todayJalali } from '../jalali';

const FA = n => String(n).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * انتخابگر تاریخ شمسی (روز/ماه/سال) — مقدار به‌صورت رشته‌ی میلادی YYYY-MM-DD برگردانده می‌شود.
 * پیش‌فرض برای تاریخ تولد (حداقل ۱ سال سن، حداکثر ۱۰۰ سال) — برای فیلتر تاریخ معاملات
 * yearsBack/allowCurrentYear را بده تا بازه‌ی سال شامل امسال هم بشود.
 */
export default function JalaliDatePicker({ value, onChange, yearsBack = 100, allowCurrentYear = false }) {
    const today = todayJalali();
    const minYear = today.jy - yearsBack;
    const maxYear = allowCurrentYear ? today.jy : today.jy - 1;

    const current = useMemo(() => {
        if (!value) return { jy: '', jm: '', jd: '' };
        const [gy, gm, gd] = value.split('-').map(Number);
        if (!gy || !gm || !gd) return { jy: '', jm: '', jd: '' };
        return gregorianToJalali(gy, gm, gd);
    }, [value]);

    function emit(jy, jm, jd) {
        if (!jy || !jm || !jd) return;
        const maxDay = jalaliMonthLength(jy, jm);
        if (jd > maxDay) jd = maxDay;
        const g = jalaliToGregorian(jy, jm, jd);
        const pad = n => String(n).padStart(2, '0');
        onChange(`${g.gy}-${pad(g.gm)}-${pad(g.gd)}`);
    }

    const dayCount = current.jy && current.jm ? jalaliMonthLength(current.jy, current.jm) : 31;
    const selectStyle = {
        background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)',
        borderRadius: 12, padding: '11px 10px', fontFamily: 'inherit', fontSize: 14, flex: 1,
    };

    return (
        <div style={{ display: 'flex', gap: 8 }}>
            <select style={selectStyle} value={current.jd || ''} onChange={e => emit(current.jy, current.jm, Number(e.target.value))}>
                <option value="">روز</option>
                {Array.from({ length: dayCount }, (_, i) => i + 1).map(d => (
                    <option key={d} value={d}>{FA(d)}</option>
                ))}
            </select>
            <select style={selectStyle} value={current.jm || ''} onChange={e => emit(current.jy, Number(e.target.value), current.jd || 1)}>
                <option value="">ماه</option>
                {JALALI_MONTHS.map((m, i) => (
                    <option key={m} value={i + 1}>{m}</option>
                ))}
            </select>
            <select style={selectStyle} value={current.jy || ''} onChange={e => emit(Number(e.target.value), current.jm || 1, current.jd || 1)}>
                <option value="">سال</option>
                {Array.from({ length: maxYear - minYear + 1 }, (_, i) => maxYear - i).map(y => (
                    <option key={y} value={y}>{FA(y)}</option>
                ))}
            </select>
        </div>
    );
}
