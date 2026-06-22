import JalaliDatePicker from './JalaliDatePicker';

/** فیلتر بازه‌ی تاریخ (از تاریخ — تا تاریخ) برای فهرست‌ها و چاپ. مقادیر رشته‌ی میلادی YYYY-MM-DD یا '' هستند. */
export default function DateRangeFilter({ from, to, onFromChange, onToChange, yearsBack = 5, allowCurrentYear = true }) {
    return (
        <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
            <div style={{ minWidth: 260 }}>
                <label style={{ display: 'block', fontSize: 13, color: 'var(--muted)', marginBottom: 6, fontWeight: 600 }}>از تاریخ</label>
                <JalaliDatePicker value={from} onChange={onFromChange} yearsBack={yearsBack} allowCurrentYear={allowCurrentYear} />
            </div>
            <div style={{ minWidth: 260 }}>
                <label style={{ display: 'block', fontSize: 13, color: 'var(--muted)', marginBottom: 6, fontWeight: 600 }}>تا تاریخ</label>
                <JalaliDatePicker value={to} onChange={onToChange} yearsBack={yearsBack} allowCurrentYear={allowCurrentYear} />
            </div>
        </div>
    );
}

/** فیلتر یک لیست بر اساس date_raw (رشته‌ی YYYY-MM-DD) درون بازه‌ی [from, to] (هرکدام خالی باشد، آن سمت باز است). */
export function filterByDateRange(items, from, to, dateField = 'date_raw') {
    if (!from && !to) return items;
    return items.filter(item => {
        const d = item[dateField];
        if (!d) return false;
        if (from && d < from) return false;
        if (to && d > to) return false;
        return true;
    });
}
