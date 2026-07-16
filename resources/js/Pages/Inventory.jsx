import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import AppLayout, { faNum } from '../Layouts/AppLayout';
import Pager, { usePager } from '../Components/Pager';

const TYPE_LABEL = {
    purchase: 'خرید از فروشگاه', sale: 'فروش به فروشگاه',
    p2p_buy: 'خرید (اتاق معاملاتی)', p2p_sell: 'فروش (اتاق معاملاتی)',
    offer_escrow: 'رزرو پیشنهاد', offer_refund: 'بازگشت رزرو',
    delivery: 'تحویل فیزیکی', delivery_refund: 'بازگشت از تحویل',
    admin_adjust: 'اصلاح توسط ادمین',
};

const DELIVERY_STATUS = {
    pending: ['در انتظار بررسی', 'silver'],
    approved: ['تأییدشده', 'buy-b'],
    shipped: ['ارسال‌شده', 'buy-b'],
    delivered: ['تحویل داده‌شده', 'buy-b'],
    rejected: ['رد‌شده', 'sell-b'],
};

const DELIVERY_METHOD = {
    address: 'ارسال به آدرس',
    pickup: 'تحویل حضوری از فروشگاه',
};

function HistoryTable({ rows, showPurity }) {
    const pager = usePager(rows);

    if (!rows.length) {
        return <div className="empty" style={{ padding: '24px 0' }}><div className="ico">📦</div>هنوز تراکنشی ثبت نشده.</div>;
    }
    return (
        <>
            <div className="table-wrap">
                <table>
                    <thead><tr>
                        <th>تاریخ</th>{showPurity && <th>عیار</th>}<th>نوع</th><th>گرم</th><th>توضیحات</th>
                    </tr></thead>
                    <tbody>
                        {pager.pageItems.map(r => (
                            <tr key={r.id}>
                                <td style={{ fontSize: 12, color: 'var(--muted)' }}>{r.created_at}</td>
                                {showPurity && <td>{r.purity}</td>}
                                <td>{TYPE_LABEL[r.type] || r.type}</td>
                                <td className="num" style={{ color: r.grams > 0 ? 'var(--up)' : 'var(--down)', fontWeight: 700 }}>
                                    {r.grams > 0 ? '+' : ''}{r.grams}
                                </td>
                                <td style={{ color: 'var(--muted)', fontSize: 13 }}>{r.description || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager page={pager.page} totalPages={pager.totalPages} onChange={pager.setPage} />
        </>
    );
}

export default function Inventory({ goldBalance, silverBalance, goldHistory, silverHistory, deliveryRequests }) {
    const { errors } = usePage().props;
    const [showForm, setShowForm] = useState(false);
    const deliveryPager = usePager(deliveryRequests);
    const form = useForm({
        metal: 'gold', purity: '999', grams: '',
        recipient_name: '', phone: '', delivery_method: 'address', address: '',
    });

    function submit(e) {
        e.preventDefault();
        form.post('/silver-delivery', {
            onSuccess: () => { form.reset('grams', 'recipient_name', 'phone', 'delivery_method', 'address'); setShowForm(false); },
        });
    }

    const maxGrams = form.data.metal === 'gold' ? goldBalance : silverBalance[form.data.purity];

    return (
        <AppLayout>
            <div className="page-wide">
                <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 20 }}>📦 موجودی انبار</h2>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 14, marginBottom: 20 }}>
                    <div style={{ background: 'linear-gradient(135deg,rgba(246,207,99,.16),rgba(199,154,46,.08))', border: '1px solid rgba(246,207,99,.3)', borderRadius: 16, padding: '18px 20px' }}>
                        <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>موجودی طلا</div>
                        <div style={{ fontSize: 24, fontWeight: 800, color: 'var(--gold-1)' }}>{faNum(goldBalance)} <span style={{ fontSize: 13, fontWeight: 400 }}>گرم</span></div>
                    </div>
                    <div style={{ background: 'rgba(255,255,255,.04)', border: '1px solid var(--line)', borderRadius: 16, padding: '18px 20px' }}>
                        <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>موجودی نقره ۹۹۹/۹</div>
                        <div style={{ fontSize: 24, fontWeight: 800, color: 'var(--silver-1)' }}>{faNum(silverBalance['999'])} <span style={{ fontSize: 13, fontWeight: 400 }}>گرم</span></div>
                    </div>
                    <div style={{ background: 'rgba(255,255,255,.04)', border: '1px solid var(--line)', borderRadius: 16, padding: '18px 20px' }}>
                        <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>موجودی نقره ۹۹۵</div>
                        <div style={{ fontSize: 24, fontWeight: 800, color: 'var(--silver-1)' }}>{faNum(silverBalance['995'])} <span style={{ fontSize: 13, fontWeight: 400 }}>گرم</span></div>
                    </div>
                </div>

                <div style={{ marginBottom: 28 }}>
                    <button onClick={() => setShowForm(s => !s)} className="btn" style={{ width: 'auto', padding: '10px 24px' }}>
                        🚚 {showForm ? 'بستن فرم' : 'درخواست تحویل فیزیکی'}
                    </button>

                    {showForm && (
                        <div className="fcard" style={{ marginTop: 16, maxWidth: 560 }}>
                            {Object.values(errors).map((e, i) => <div key={i} className="alert err">{e}</div>)}
                            <form onSubmit={submit}>
                                <div className="btn-row" style={{ marginBottom: 14 }}>
                                    {[['gold', 'طلا'], ['silver', 'نقره']].map(([m, label]) => (
                                        <button key={m} type="button" onClick={() => form.setData('metal', m)}
                                            style={{
                                                padding: '9px', borderRadius: 10, fontFamily: 'inherit', fontSize: 13, fontWeight: 700,
                                                cursor: 'pointer', border: 'none',
                                                background: form.data.metal === m ? 'rgba(246,207,99,.2)' : 'rgba(255,255,255,.06)',
                                                color: form.data.metal === m ? 'var(--gold-1)' : 'var(--muted)',
                                                outline: form.data.metal === m ? '2px solid var(--gold-1)' : '2px solid transparent',
                                            }}>
                                            {label}
                                        </button>
                                    ))}
                                </div>
                                {form.data.metal === 'silver' && (
                                    <div className="field">
                                        <label>عیار</label>
                                        <select value={form.data.purity} onChange={e => form.setData('purity', e.target.value)}>
                                            <option value="999">نقره ۹۹۹/۹</option>
                                            <option value="995">نقره ۹۹۵</option>
                                        </select>
                                    </div>
                                )}
                                <div className="field">
                                    <label>مقدار (گرم) — موجودی فعلی: {faNum(maxGrams)} گرم</label>
                                    <input type="number" step="any" min="1" max={maxGrams} value={form.data.grams}
                                        onChange={e => form.setData('grams', e.target.value)} required />
                                </div>
                                <div className="field">
                                    <label>نام گیرنده</label>
                                    <input value={form.data.recipient_name} onChange={e => form.setData('recipient_name', e.target.value)} required />
                                </div>
                                <div className="field">
                                    <label>شماره موبایل گیرنده</label>
                                    <input value={form.data.phone} onChange={e => form.setData('phone', e.target.value)} required />
                                </div>
                                <div className="field">
                                    <label>روش تحویل</label>
                                    <select value={form.data.delivery_method} onChange={e => form.setData('delivery_method', e.target.value)}>
                                        <option value="address">ارسال به آدرس</option>
                                        <option value="pickup">تحویل حضوری از فروشگاه</option>
                                    </select>
                                </div>
                                {form.data.delivery_method === 'address' && (
                                    <div className="field">
                                        <label>آدرس تحویل</label>
                                        <input value={form.data.address} onChange={e => form.setData('address', e.target.value)} required />
                                    </div>
                                )}
                                <button className="btn" type="submit" disabled={form.processing}>
                                    {form.processing ? 'در حال ارسال...' : 'ثبت درخواست'}
                                </button>
                            </form>
                        </div>
                    )}
                </div>

                {deliveryRequests.length > 0 && (
                    <>
                        <div className="section-title">درخواست‌های تحویل فیزیکی</div>
                        <div className="table-wrap" style={{ marginBottom: 28 }}>
                            <table>
                                <thead><tr><th>تاریخ</th><th>مورد</th><th>مقدار</th><th>روش تحویل</th><th>وضعیت</th><th>یادداشت ادمین</th></tr></thead>
                                <tbody>
                                    {deliveryPager.pageItems.map(r => {
                                        const [label, cls] = DELIVERY_STATUS[r.status] || [r.status, 'silver'];
                                        return (
                                            <tr key={r.id}>
                                                <td style={{ fontSize: 12, color: 'var(--muted)' }}>{r.created_at}</td>
                                                <td>{r.metal === 'gold' ? 'طلا' : `نقره ${r.purity}`}</td>
                                                <td className="num">{r.grams} گرم</td>
                                                <td>
                                                    {DELIVERY_METHOD[r.delivery_method] || DELIVERY_METHOD.address}
                                                    {r.delivery_method === 'address' && r.address && (
                                                        <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>{r.address}</div>
                                                    )}
                                                </td>
                                                <td><span className={`badge ${cls}`}>{label}</span></td>
                                                <td style={{ color: 'var(--muted)', fontSize: 13 }}>{r.admin_note || '—'}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <Pager page={deliveryPager.page} totalPages={deliveryPager.totalPages} onChange={deliveryPager.setPage} />
                    </>
                )}

                <div className="section-title">تاریخچه‌ی طلا</div>
                <HistoryTable rows={goldHistory} showPurity={false} />

                <div className="section-title" style={{ marginTop: 28 }}>تاریخچه‌ی نقره</div>
                <HistoryTable rows={silverHistory} showPurity />
            </div>
        </AppLayout>
    );
}
