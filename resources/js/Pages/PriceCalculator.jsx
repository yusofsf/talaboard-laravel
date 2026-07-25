import { useEffect, useState } from 'react';
import AppLayout, { faNum } from '../Layouts/AppLayout';

const METALS = [
    { key: 'gold', label: 'طلا (گرم)', priceField: ['gold', 'geram'] },
    { key: 'silver_999', label: 'نقره ۹۹۹/۹ (گرم)', priceField: ['silver', 'gram_999'] },
    { key: 'silver_995', label: 'نقره ۹۹۵ (گرم)', priceField: ['silver', 'gram_995'] },
];

function ResultRow({ label, value, highlight = false }) {
    return <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: highlight ? 0 : 8 }}>
        <span style={{ color: 'var(--muted)' }}>{label}</span>
        <strong className="num" style={{ fontSize: highlight ? 22 : 15, color: highlight ? 'var(--gold-1)' : 'var(--txt)' }}>{faNum(Math.round(value))} تومان</strong>
    </div>;
}

export default function PriceCalculator() {
    const [prices, setPrices] = useState(null);
    const [metal, setMetal] = useState('gold');
    const [pricePerGram, setPricePerGram] = useState('');
    const [grams, setGrams] = useState('');
    const [feePercent, setFeePercent] = useState('0');

    useEffect(() => {
        fetch('/api/prices').then(response => response.json()).then(setPrices).catch(() => {});
    }, []);

    useEffect(() => {
        const selected = METALS.find(item => item.key === metal);
        const price = prices?.[selected.priceField[0]]?.[selected.priceField[1]];
        if (typeof price === 'number') setPricePerGram(String(price));
    }, [prices, metal]);

    const base = (parseFloat(pricePerGram) || 0) * (parseFloat(grams) || 0);
    const fee = base * ((parseFloat(feePercent) || 0) / 100);

    return <AppLayout>
        <main className="page" style={{ maxWidth: 680 }}>
            <div className="fcard">
                <h2>محاسبه قیمت طلا و نقره</h2>
                <p style={{ color: 'var(--muted)', fontSize: 13, marginTop: 6 }}>قیمت لحظه‌ای را مبنا بگیرید یا آن را به‌دلخواه ویرایش کنید.</p>
                <div className="field"><label>نوع فلز</label><select value={metal} onChange={event => setMetal(event.target.value)}>{METALS.map(item => <option key={item.key} value={item.key}>{item.label}</option>)}</select></div>
                <div className="field"><label>قیمت هر گرم (تومان)</label><input type="number" min="0" value={pricePerGram} onChange={event => setPricePerGram(event.target.value)} /></div>
                <div className="field"><label>مقدار (گرم)</label><input type="number" min="0" step="any" value={grams} onChange={event => setGrams(event.target.value)} /></div>
                <div className="field"><label>کارمزد / سود (٪)</label><input type="number" min="0" step="any" value={feePercent} onChange={event => setFeePercent(event.target.value)} /></div>
                <div style={{ background: 'rgba(255,255,255,.04)', border: '1px solid var(--line)', borderRadius: 12, padding: '16px' }}>
                    <ResultRow label="مبلغ پایه" value={base} />
                    <ResultRow label={`کارمزد / سود (${feePercent || 0}٪)`} value={fee} />
                    <hr style={{ border: 'none', borderTop: '1px solid var(--line)', margin: '10px 0' }} />
                    <ResultRow label="مبلغ نهایی" value={base + fee} highlight />
                </div>
            </div>
        </main>
    </AppLayout>;
}
