import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import AppLayout, { faNum } from '../Layouts/AppLayout';

const STATUS = {
    pending: ['در انتظار بررسی', 'silver'],
    approved: ['تأییدشده', 'buy-b'],
    rejected: ['رد‌شده', 'sell-b'],
};

export default function Wallet({ balance, txns, withdrawals }) {
    const { errors } = usePage().props;
    const [showForm, setShowForm] = useState(false);
    const form = useForm({ amount: '', card_number: '', shaba: '' });

    function submit(e) {
        e.preventDefault();
        form.post('/wallet/withdraw', { onSuccess: () => { form.reset(); setShowForm(false); } });
    }

    return (
        <AppLayout>
            <div className="page-wide">
                <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 20 }}>کیف پول</h2>

                <div style={{
                    background: 'linear-gradient(135deg,var(--gold-1),var(--gold-2))',
                    borderRadius: 22, padding: '32px 28px', marginBottom: 20,
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16,
                }}>
                    <div>
                        <div style={{ fontSize: 13, fontWeight: 700, color: '#5a3a00', opacity: .8 }}>موجودی فعلی</div>
                        <div style={{ fontSize: 38, fontWeight: 900, color: '#1a1200', lineHeight: 1 }}>{faNum(balance)}</div>
                        <div style={{ fontSize: 15, fontWeight: 700, color: '#5a3a00', marginTop: 6 }}>تومان</div>
                    </div>
                    <div style={{ fontSize: 52, opacity: .35 }}>💰</div>
                </div>

                <div style={{ marginBottom: 28 }}>
                    <button onClick={() => setShowForm(s => !s)} className="btn" style={{ width: 'auto', padding: '10px 24px' }} disabled={balance <= 0}>
                        🏦 {showForm ? 'بستن فرم' : 'درخواست تسویه حساب'}
                    </button>
                    {balance <= 0 && <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 6 }}>موجودی کیف پول شما صفر است.</div>}

                    {showForm && (
                        <div className="fcard" style={{ marginTop: 16, maxWidth: 480 }}>
                            {Object.values(errors).map((e, i) => <div key={i} className="alert err">{e}</div>)}
                            <form onSubmit={submit}>
                                <div className="field">
                                    <label>مبلغ (تومان) — حداکثر {faNum(balance)}</label>
                                    <input type="number" min="1000" max={balance} value={form.data.amount}
                                        onChange={e => form.setData('amount', e.target.value)} required />
                                </div>
                                <div className="field">
                                    <label>شماره کارت</label>
                                    <input value={form.data.card_number} dir="ltr" placeholder="xxxx-xxxx-xxxx-xxxx"
                                        onChange={e => form.setData('card_number', e.target.value)} required />
                                </div>
                                <div className="field">
                                    <label>شماره شبا</label>
                                    <input value={form.data.shaba} dir="ltr" placeholder="IRxxxxxxxxxxxxxxxxxxxxxxxx"
                                        onChange={e => form.setData('shaba', e.target.value)} required />
                                </div>
                                <button className="btn" type="submit" disabled={form.processing}>
                                    {form.processing ? 'در حال ارسال...' : 'ثبت درخواست'}
                                </button>
                            </form>
                        </div>
                    )}
                </div>

                {withdrawals.length > 0 && (
                    <>
                        <div className="section-title">درخواست‌های تسویه حساب</div>
                        <div className="table-wrap" style={{ marginBottom: 28 }}>
                            <table>
                                <thead><tr><th>تاریخ</th><th>مبلغ</th><th>شماره کارت</th><th>وضعیت</th><th>یادداشت ادمین</th></tr></thead>
                                <tbody>
                                    {withdrawals.map(w => {
                                        const [label, cls] = STATUS[w.status] || [w.status, 'silver'];
                                        return (
                                            <tr key={w.id}>
                                                <td style={{ fontSize: 12, color: 'var(--muted)' }}>{w.created_at}</td>
                                                <td className="num">{faNum(w.amount)}</td>
                                                <td className="num" dir="ltr" style={{ fontSize: 13 }}>{w.card_number}</td>
                                                <td><span className={`badge ${cls}`}>{label}</span></td>
                                                <td style={{ color: 'var(--muted)', fontSize: 13 }}>{w.admin_note || '—'}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                <div style={{ fontSize: 16, fontWeight: 800, marginBottom: 14, paddingBottom: 10, borderBottom: '1px solid var(--line)' }}>
                    تاریخچه تراکنش‌ها
                </div>

                {txns.length ? (
                    <div className="table-wrap">
                        <table>
                            <thead><tr><th>تاریخ</th><th>شرح</th><th>نوع</th><th>مبلغ (تومان)</th></tr></thead>
                            <tbody>
                                {txns.map(t => (
                                    <tr key={t.id}>
                                        <td style={{ fontSize: 12, color: 'var(--muted)' }}>{t.created_at}</td>
                                        <td>{t.description || '—'}</td>
                                        <td><span className={`badge ${t.amount > 0 ? 'buy-b' : 'sell-b'}`}>{t.amount > 0 ? 'واریز' : 'برداشت'}</span></td>
                                        <td className="num" style={{ color: t.amount > 0 ? 'var(--up)' : 'var(--down)', fontWeight: 700 }}>
                                            {t.amount > 0 ? '+' : ''}{faNum(t.amount)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="empty"><div className="ico">💸</div><div>هنوز تراکنشی ثبت نشده.</div></div>
                )}
            </div>
        </AppLayout>
    );
}
