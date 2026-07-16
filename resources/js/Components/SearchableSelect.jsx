import { useMemo, useState } from 'react';

const norm = value => String(value ?? '').trim().toLowerCase();

export default function SearchableSelect({
    value,
    onChange,
    options,
    placeholder = 'انتخاب کنید',
    searchPlaceholder = 'جستجو...',
    emptyText = 'موردی پیدا نشد.',
    required = false,
    disabled = false,
}) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const selected = options.find(option => String(option.value) === String(value));
    const filtered = useMemo(() => {
        const q = norm(query);
        if (!q) return options;

        return options.filter(option => {
            const haystack = [option.label, option.description, option.search].filter(Boolean).join(' ');
            return norm(haystack).includes(q);
        });
    }, [options, query]);

    function choose(nextValue) {
        onChange(String(nextValue));
        setQuery('');
        setOpen(false);
    }

    return (
        <div style={{ position: 'relative' }}>
            <button type="button" disabled={disabled}
                onClick={() => setOpen(v => !v)}
                onBlur={() => setTimeout(() => setOpen(false), 120)}
                style={{
                    width: '100%', minHeight: 44, textAlign: 'start',
                    background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)',
                    color: selected ? 'var(--txt)' : 'var(--muted)', borderRadius: 12,
                    padding: '10px 14px', fontFamily: 'inherit', fontSize: 14,
                    cursor: disabled ? 'not-allowed' : 'pointer',
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10,
                }}>
                <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {selected ? selected.label : placeholder}{required && !selected ? ' *' : ''}
                </span>
                <span aria-hidden="true" style={{ color: 'var(--muted)' }}>⌄</span>
            </button>

            {open && (
                <div style={{
                    position: 'absolute', zIndex: 30, insetInline: 0, top: 'calc(100% + 6px)',
                    background: 'linear-gradient(160deg,var(--card),var(--card-2))',
                    border: '1px solid var(--line)', borderRadius: 12,
                    boxShadow: '0 14px 35px rgba(0,0,0,.35)', padding: 8,
                }}>
                    <input autoFocus value={query}
                        onChange={e => setQuery(e.target.value)}
                        onKeyDown={e => {
                            if (e.key === 'Enter' && filtered[0]) {
                                e.preventDefault();
                                choose(filtered[0].value);
                            }
                            if (e.key === 'Escape') setOpen(false);
                        }}
                        placeholder={searchPlaceholder}
                        style={{
                            width: '100%', background: 'rgba(255,255,255,.06)',
                            border: '1px solid var(--line)', color: 'var(--txt)',
                            borderRadius: 10, padding: '9px 11px',
                            fontFamily: 'inherit', fontSize: 14, marginBottom: 8,
                        }} />
                    <div style={{ maxHeight: 260, overflowY: 'auto', display: 'grid', gap: 4 }}>
                        {filtered.length ? filtered.map(option => (
                            <button key={option.value} type="button"
                                onMouseDown={e => e.preventDefault()}
                                onClick={() => choose(option.value)}
                                style={{
                                    width: '100%', textAlign: 'start', border: 'none',
                                    background: String(option.value) === String(value) ? 'rgba(246,207,99,.16)' : 'transparent',
                                    color: String(option.value) === String(value) ? 'var(--gold-1)' : 'var(--txt)',
                                    borderRadius: 9, padding: '9px 10px', fontFamily: 'inherit',
                                    fontSize: 13, cursor: 'pointer',
                                }}>
                                <span style={{ display: 'block', fontWeight: 700 }}>{option.label}</span>
                                {option.description && (
                                    <span dir={option.descriptionDir || undefined} style={{ display: 'block', color: 'var(--muted)', fontSize: 12, marginTop: 2 }}>
                                        {option.description}
                                    </span>
                                )}
                            </button>
                        )) : (
                            <div style={{ color: 'var(--muted)', fontSize: 13, padding: '12px 10px', textAlign: 'center' }}>
                                {emptyText}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
