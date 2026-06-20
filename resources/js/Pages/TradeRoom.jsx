import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppLayout, { faNum } from '../Layouts/AppLayout';

export default function TradeRoom({ offers, myOffers, silverBalance }) {
    const { errors, auth } = usePage().props;
    const [tab, setTab] = useState('open');
    const form = useForm({ side: 'sell', purity: '999', grams: '', price_per_gram: '' });

    const total = form.data.grams && form.data.price_per_gram
        ? Math.round(parseFloat(form.data.grams) * parseInt(form.data.price_per_gram, 10))
        : null;

    function submit(e) {
        e.preventDefault();
        form.post('/trade-room', { onSuccess: () => form.reset('grams', 'price_per_gram') });
    }

    function accept(id) {
        if (!confirm('این پیشنهاد پذیرفته شود؟ معامله فوراً نهایی می‌شود.')) return;
        router.post(`/trade-room/${id}/accept`, {}, { preserveScroll: true });
    }

    function cancel(id) {
        if (!confirm('این پیشنهاد لغو شود؟')) return;
        router.post(`/trade-room/${id}/cancel`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <div className="page-wide">
                <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 8 }}>🤝 اتاق معاملاتی</h2>
                <p style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 20 }}>
                    خرید و فروش نقره بین اعضای ویژه — مستقیماً با یکدیگر، بدون واسطه‌ی فروشگاه.
                </p>

                {/* موجودی نقره */}
                <div style={{ display: 'flex', gap: 14, marginBottom: 24, flexWrap: 'wrap' }}>
                    {['999', '995'].map(p => (
                        <div key={p} style={{
                            flex: '1', minWidth: 180, background: 'linear-gradient(160deg,var(--card),var(--card-2))',
                            border: '1px solid var(--line)', borderRadius: 16, padding: '16px 18px',
                        }}>
                            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>موجودی نقره {p}</div>
                            <div style={{ fontSize: 22, fontWeight: 800, color: 'var(--silver-1)' }}>
                                {faNum(silverBalance[p])} <span style={{ fontSize: 13, fontWeight: 400, color: 'var(--muted)' }}>گرم</span>
                            </div>
                        </div>
                    ))}
                    <a href="/silver-delivery" className="fcard" style={{
                        flex: '1', minWidth: 180, display: 'flex', flexDirection: 'column',
                        justifyContent: 'center', alignItems: 'center', textAlign: 'center',
                        padding: '16px 18px', textDecoration: 'none',
                    }}>
                        <div style={{ fontSize: 24, marginBottom: 4 }}>🚚</div>
                        <div style={{ fontSize: 13, fontWeight: 700 }}>درخواست تحویل فیزیکی نقره</div>
                    </a>
                </div>

                {/* فرم ثبت پیشنهاد */}
                <div className="fcard" style={{ marginBottom: 24 }}>
                    <h2 style={{ fontSize: 16 }}>ثبت پیشنهاد جدید</h2>
                    <div style={{ height: 16 }} />
                    {errors.grams && <div className="alert err">{errors.grams}</div>}
                    {errors.offer && <div className="alert err">{errors.offer}</div>}
                    <form onSubmit={submit}>
                        <div className="btn-row" style={{ marginBottom: 16 }}>
                            {['sell', 'buy'].map(s => (
                                <button key={s} type="button" onClick={() => form.setData('side', s)}
                                    style={{
                                        padding: '11px', borderRadius: 12, fontFamily: 'inherit', fontSize: 14, fontWeight: 700,
                                        cursor: 'pointer', border: 'none',
                                        background: form.data.side === s ? (s === 'sell' ? 'rgba(255,107,120,.2)' : 'rgba(65,225,166,.2)') : 'rgba(255,255,255,.06)',
                                        color: form.data.side === s ? (s === 'sell' ? 'var(--down)' : 'var(--up)') : 'var(--muted)',
                                        outline: form.data.side === s ? `2px solid ${s === 'sell' ? 'var(--down)' : 'var(--up)'}` : '2px solid transparent',
                                    }}>
                                    {s === 'sell' ? '🔴 می‌فروشم' : '🟢 می‌خرم'}
                                </button>
                            ))}
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                            <div className="field">
                                <label>عیار</label>
                                <select value={form.data.purity} onChange={e => form.setData('purity', e.target.value)}
                                    style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 12, padding: '11px 14px', fontFamily: 'inherit', fontSize: 15, width: '100%' }}>
                                    <option value="999">نقره ۹۹۹/۹</option>
                                    <option value="995">نقره ۹۹۵</option>
                                </select>
                            </div>
                            <div className="field">
                                <label>مقدار (گرم)</label>
                                <input type="number" step="any" min="0.1" value={form.data.grams}
                                    onChange={e => form.setData('grams', e.target.value)} required />
                            </div>
                        </div>
                        <div className="field">
                            <label>قیمت هر گرم (تومان)</label>
                            <input type="number" min="1" value={form.data.price_per_gram}
                                onChange={e => form.setData('price_per_gram', e.target.value)} required />
                        </div>
                        {total != null && (
                            <div style={{ background: 'rgba(255,255,255,.04)', border: '1px solid var(--line)', borderRadius: 12, padding: '12px 16px', marginBottom: 16 }}>
                                <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 4 }}>مبلغ کل</div>
                                <div style={{ fontSize: 20, fontWeight: 800, color: 'var(--gold-1)' }}>{faNum(total)} تومان</div>
                            </div>
                        )}
                        <button className="btn" type="submit" disabled={form.processing}>
                            {form.processing ? '...' : 'ثبت پیشنهاد'}
                        </button>
                    </form>
                </div>

                <div className="tabs">
                    <button className={`tab-btn${tab === 'open' ? ' active' : ''}`} onClick={() => setTab('open')}>پیشنهادهای باز</button>
                    <button className={`tab-btn${tab === 'mine' ? ' active' : ''}`} onClick={() => setTab('mine')}>تاریخچه‌ی من</button>
                </div>

                {tab === 'open' && (
                    offers.length ? (
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>نوع</th><th>عیار</th><th>مقدار (گرم)</th><th>قیمت هر گرم</th><th>مبلغ کل</th><th>کاربر</th><th>تاریخ</th><th></th></tr></thead>
                                <tbody>
                                    {offers.map(o => (
                                        <tr key={o.id}>
                                            <td><span className={`badge ${o.side === 'sell' ? 'sell-b' : 'buy-b'}`}>{o.side === 'sell' ? 'فروش' : 'خرید'}</span></td>
                                            <td>{o.purity_label}</td>
                                            <td className="num">{o.grams}</td>
                                            <td className="num">{faNum(o.price_per_gram)}</td>
                                            <td className="num" style={{ color: 'var(--gold-1)', fontWeight: 700 }}>{faNum(o.total)}</td>
                                            <td>{o.is_mine ? <span className="badge gold">شما</span> : o.user_name}</td>
                                            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{o.created_at}</td>
                                            <td>
                                                {o.is_mine ? (
                                                    <button onClick={() => cancel(o.id)} className="btn-sm danger">لغو</button>
                                                ) : (
                                                    <button onClick={() => accept(o.id)} className="btn-sm" style={{ borderColor: 'rgba(65,225,166,.4)', color: 'var(--up)', background: 'rgba(65,225,166,.08)' }}>
                                                        پذیرفتن
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="empty"><div className="ico">🤝</div>هیچ پیشنهاد بازی در اتاق معاملاتی نیست.</div>
                    )
                )}

                {tab === 'mine' && (
                    myOffers.length ? (
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>نوع</th><th>عیار</th><th>مقدار</th><th>قیمت هر گرم</th><th>مبلغ کل</th><th>طرف معامله</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
                                <tbody>
                                    {myOffers.map(o => (
                                        <tr key={o.id}>
                                            <td><span className={`badge ${o.side === 'sell' ? 'sell-b' : 'buy-b'}`}>{o.side === 'sell' ? 'فروش' : 'خرید'}</span></td>
                                            <td>{o.purity_label}</td>
                                            <td className="num">{o.grams}</td>
                                            <td className="num">{faNum(o.price_per_gram)}</td>
                                            <td className="num">{faNum(o.total)}</td>
                                            <td>{o.counterparty_name || '—'}</td>
                                            <td>
                                                {o.status === 'completed' && <span className="badge buy-b">تکمیل‌شده</span>}
                                                {o.status === 'cancelled' && <span className="badge silver">لغوشده</span>}
                                            </td>
                                            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{o.completed_at || o.created_at}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="empty"><div className="ico">📋</div>هنوز معامله‌ای انجام نداده‌اید.</div>
                    )
                )}
            </div>
        </AppLayout>
    );
}
