import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout, { faNum } from '../Layouts/AppLayout';
import TradingViewChart from '../Components/TradingViewChart';

const CHART_SYMBOLS = {
    gold:   'OANDA:XAUUSD',
    silver: 'OANDA:XAGUSD',
};

export default function Trade({ item, meta, sellPrice, buyPrice }) {
    const { data, setData, post, processing, errors } = useForm({ trade_type: 'buy', quantity: '' });
    const [showChart, setShowChart] = useState(false);
    const [chartAsset, setChartAsset] = useState(meta.group === 'silver' ? 'silver' : 'gold');

    // مشتری «خرید» بزند → قیمت فروش ما؛ «فروش» بزند → قیمت خرید ما
    const price = data.trade_type === 'buy' ? sellPrice : buyPrice;
    const total = data.quantity && price ? Math.round(parseFloat(data.quantity) * price) : null;

    function submit(e) {
        e.preventDefault();
        post(`/trade/${item}`);
    }

    return (
        <AppLayout>
            <div className="page">
                <div className="fcard">
                    <h2>{meta.label}</h2>
                    <div style={{ height: 20 }} />

                    {price ? (
                        <div style={{
                            background: data.trade_type === 'buy'
                                ? 'linear-gradient(135deg,rgba(65,225,166,.14),rgba(31,157,114,.06))'
                                : 'linear-gradient(135deg,rgba(255,107,120,.14),rgba(199,60,70,.06))',
                            border: `1px solid ${data.trade_type === 'buy' ? 'rgba(65,225,166,.3)' : 'rgba(255,107,120,.3)'}`,
                            borderRadius: 14, padding: '16px 20px', marginBottom: 20,
                        }}>
                            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 4 }}>
                                قیمت {data.trade_type === 'buy' ? 'فروش (شما می‌خرید)' : 'خرید (شما می‌فروشید)'}
                            </div>
                            <div style={{ fontSize: 28, fontWeight: 900, color: data.trade_type === 'buy' ? 'var(--up)' : 'var(--down)' }}>
                                {faNum(price)} <span style={{ fontSize: 14, fontWeight: 400, color: 'var(--muted)' }}>تومان</span>
                            </div>
                        </div>
                    ) : (
                        <div className="alert err">قیمت در حال حاضر در دسترس نیست.</div>
                    )}

                    {errors.quantity && <div className="alert err">{errors.quantity}</div>}

                    <form onSubmit={submit}>
                        <div className="btn-row" style={{ marginBottom: 18 }}>
                            {['buy', 'sell'].map(t => (
                                <button key={t} type="button"
                                    onClick={() => setData('trade_type', t)}
                                    style={{
                                        padding: '12px', borderRadius: 12, fontFamily: 'inherit',
                                        fontSize: 15, fontWeight: 700, cursor: 'pointer', border: 'none',
                                        background: data.trade_type === t
                                            ? (t === 'buy' ? 'rgba(65,225,166,.2)' : 'rgba(255,107,120,.2)')
                                            : 'rgba(255,255,255,.06)',
                                        color: data.trade_type === t
                                            ? (t === 'buy' ? 'var(--up)' : 'var(--down)')
                                            : 'var(--muted)',
                                        outline: data.trade_type === t
                                            ? `2px solid ${t === 'buy' ? 'var(--up)' : 'var(--down)'}`
                                            : '2px solid transparent',
                                    }}>
                                    {t === 'buy' ? '🟢 خرید' : '🔴 فروش'}
                                </button>
                            ))}
                        </div>
                        <div className="field">
                            <label>مقدار</label>
                            <input type="number" step="any" min="0.001"
                                value={data.quantity} onChange={e => setData('quantity', e.target.value)}
                                placeholder="مثال: ۱ یا ۰.۵" required />
                        </div>
                        {total && (
                            <div style={{ background: 'rgba(255,255,255,.04)', border: '1px solid var(--line)', borderRadius: 12, padding: '12px 16px', marginBottom: 18 }}>
                                <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 4 }}>مبلغ کل (تخمینی)</div>
                                <div style={{ fontSize: 22, fontWeight: 800, color: 'var(--gold-1)' }}>{faNum(total)} تومان</div>
                            </div>
                        )}
                        <button className="btn" type="submit" disabled={processing || !price}>
                            {processing ? '...' : `ثبت ${data.trade_type === 'buy' ? 'خرید' : 'فروش'}`}
                        </button>
                    </form>
                    <div className="form-foot"><a href="/history">سوابق معاملات</a></div>
                </div>

                {/* نمایش چارت */}
                <div className="fcard" style={{ marginTop: 20 }}>
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 10 }}>
                        <button type="button" onClick={() => setShowChart(s => !s)} className="btn btn-outline" style={{ width: 'auto', padding: '9px 22px' }}>
                            {showChart ? 'بستن چارت' : '📈 نمایش چارت'}
                        </button>
                        {showChart && (
                            <div className="btn-row" style={{ width: 'auto', display: 'inline-flex', gap: 8 }}>
                                {[['gold', 'طلا'], ['silver', 'نقره']].map(([key, label]) => (
                                    <button key={key} type="button" onClick={() => setChartAsset(key)}
                                        style={{
                                            padding: '8px 18px', borderRadius: 10, fontFamily: 'inherit',
                                            fontSize: 13, fontWeight: 700, cursor: 'pointer', border: 'none',
                                            background: chartAsset === key ? 'rgba(246,207,99,.2)' : 'rgba(255,255,255,.06)',
                                            color: chartAsset === key ? 'var(--gold-1)' : 'var(--muted)',
                                            outline: chartAsset === key ? '2px solid var(--gold-1)' : '2px solid transparent',
                                        }}>
                                        {label}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {showChart && (
                        <div style={{ marginTop: 16 }}>
                            <TradingViewChart symbol={CHART_SYMBOLS[chartAsset]} />
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
