/** فیلتر یک لیست بر اساس عبارت جستجو در چند فیلد متنی مشخص (case-insensitive). */
export function filterBySearch(items, query, fields) {
    const q = (query || '').trim().toLowerCase();
    if (!q) return items;
    return items.filter(item => fields.some(f => String(item[f] ?? '').toLowerCase().includes(q)));
}

export default function SearchBox({ value, onChange, placeholder = '🔍 جستجو...' }) {
    return (
        <input
            value={value}
            onChange={e => onChange(e.target.value)}
            placeholder={placeholder}
            className="no-print"
            style={{ minWidth: 220, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 12, padding: '9px 14px', fontFamily: 'inherit', fontSize: 14 }}
        />
    );
}
