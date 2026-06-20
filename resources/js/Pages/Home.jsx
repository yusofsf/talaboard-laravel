import { useEffect, useState } from 'react';
import AppLayout, { faNum } from '../Layouts/AppLayout';

const ITEMS = [
    { key: 'mithqal', label: 'مثقال طلا',  unit: 'مثقال', group: 'gold' },
    { key: 'geram',   label: 'گرم طلا',     unit: 'گرم',   group: 'gold' },
    { key: 'bahar',   label: 'سکه تمام',    unit: 'عدد',   group: 'gold' },
    { key: 'nim',     label: 'نیم سکه',      unit: 'عدد',   group: 'gold' },
    { key: 'rob',     label: 'ربع سکه',      unit: 'عدد',   group: 'gold' },
];

export default function Home({ prices: initial }) {
    const [prices, setPrices] = useState(initial);
    const [updatedAt, setUpdatedAt] = useState(initial?.updated_at);

    useEffect(() => {
        const refresh = parseInt(document.querySelector('meta[name=refresh]')?.content || '30') * 1000;
        const id = setInterval(async () => {
            const res = await fetch('/api/prices');
            const data = await res.json();
            setPrices(data);
            setUpdatedAt(data.updated_at);
        }, refresh || 30000);
        return () => clearInterval(id);
    }, []);

    return (
        <AppLayout>
            <div className="page-wide">
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 20, flexWrap: 'wrap', gap: 10 }}>
                    <h2 style={{ fontSize: 22, fontWeight: 800 }}>تابلوی قیمت طلا و نقره</h2>
                    {updatedAt && (
                        <span style={{ fontSize: 12, color: 'var(--muted)' }}>آخرین بروزرسانی: {updatedAt}</span>
                    )}
                </div>

                {prices?.dollar && (
                    <div style={{
                        background: 'linear-gradient(135deg,rgba(246,207,99,.12),rgba(199,154,46,.08))',
                        border: '1px solid rgba(246,207,99,.25)', borderRadius: 16,
                        padding: '16px 22px', marginBottom: 24, display: 'flex', alignItems: 'center', gap: 16,
                    }}>
                        <span style={{ fontSize: 24 }}>💵</span>
                        <div>
                            <div style={{ fontSize: 12, color: 'var(--muted)', fontWeight: 700 }}>نرخ دلار</div>
                            <div style={{ fontSize: 24, fontWeight: 900, color: 'var(--gold-1)' }}>
                                {faNum(prices.dollar)} <span style={{ fontSize: 14, fontWeight: 400 }}>تومان</span>
                            </div>
                        </div>
                    </div>
                )}

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(240px,1fr))', gap: 16 }}>
                    {ITEMS.map(item => {
                        const price = item.group === 'gold'
                            ? prices?.gold?.[item.key]
                            : prices?.silver;
                        return (
                            <div key={item.key} style={{
                                background: 'linear-gradient(160deg,var(--card),var(--card-2))',
                                border: '1px solid var(--line)', borderRadius: 18, padding: '22px 20px',
                            }}>
                                <div style={{ fontSize: 13, color: 'var(--muted)', fontWeight: 700, marginBottom: 8 }}>
                                    {item.label}
                                </div>
                                <div style={{ fontSize: 26, fontWeight: 900, color: 'var(--gold-1)', marginBottom: 4 }}>
                                    {price ? faNum(price) : '—'}
                                </div>
                                <div style={{ fontSize: 12, color: 'var(--muted)' }}>تومان / {item.unit}</div>
                                <div style={{ marginTop: 14, display: 'flex', gap: 8 }}>
                                    <a href={`/trade/${item.key}`} style={{
                                        flex: 1, textAlign: 'center', padding: '8px 0', borderRadius: 10,
                                        background: 'rgba(65,225,166,.12)', color: 'var(--up)', fontWeight: 700,
                                        fontSize: 13, border: '1px solid rgba(65,225,166,.25)',
                                    }}>خرید</a>
                                    <a href={`/trade/${item.key}`} style={{
                                        flex: 1, textAlign: 'center', padding: '8px 0', borderRadius: 10,
                                        background: 'rgba(255,107,120,.1)', color: 'var(--down)', fontWeight: 700,
                                        fontSize: 13, border: '1px solid rgba(255,107,120,.25)',
                                    }}>فروش</a>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
