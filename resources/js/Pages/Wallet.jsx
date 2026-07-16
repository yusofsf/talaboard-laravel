import { useMemo, useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import AppLayout, { faNum } from '../Layouts/AppLayout';
import SearchableSelect from '../Components/SearchableSelect';

const STATUS = {
    pending: ['در انتظار بررسی', 'silver'],
    approved: ['تأییدشده', 'buy-b'],
    rejected: ['رد‌شده', 'sell-b'],
};

export default function Wallet({ balance, txns, withdrawals, deposits, bankCards }) {
    const { errors } = usePage().props;
    const [showForm, setShowForm] = useState(false);
    const form = useForm({ amount: '', bank_card_id: '' });

    const [showDepositForm, setShowDepositForm] = useState(false);
    const depositForm = useForm({ amount: '', note: '' });
    const bankCardOptions = useMemo(() => (bankCards || []).map(card => ({
        value: String(card.id),
        label: card.bank_name || 'کارت بانکی',
        description: card.card_number,
        descriptionDir: 'ltr',
        search: `${card.bank_name || ''} ${card.card_number} ${card.shaba || ''}`,
    })), [bankCards]);

    function submit(e) {
        e.preventDefault();
        form.post('/wallet/withdraw', { onSuccess: () => { form.reset(); setShowForm(false); } });
    }

    function submitDeposit(e) {
        e.preventDefault();
        depositForm.post('/wallet/deposit', { onSuccess: () => { depositForm.reset(); setShowDepositForm(false); } });
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

                <div style={{ marginBottom: 8, display: 'flex', gap: 12, flexWrap: 'wrap' }}>
                    <button onClick={() => setShowDepositForm(s => !s)} className="btn" style={{ width: 'auto', padding: '10px 24px' }}>
                        ➕ {showDepositForm ? 'بستن فرم' : 'افزایش موجودی'}
                    </button>
                    <button onClick={() => setShowForm(s => !s)} className="btn-outline btn" style={{ width: 'auto', padding: '10px 24px' }} disabled={balance <= 0}>
                        🏦 {showForm ? 'بستن فرم' : 'درخواست تسویه حساب'}
                    </button>
                </div>
                {balance <= 0 && <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 20 }}>موجودی کیف پول شما برای تسویه حساب صفر است.</div>}

                {showDepositForm && (
                    <div className="fcard" style={{ marginBottom: 28, maxWidth: 480 }}>
                        <div className="alert info" style={{ marginBottom: 16 }}>
                            فعلاً افزایش موجودی به‌صورت دستی و با بررسی ادمین انجام می‌شود. به‌زودی به درگاه پرداخت آنلاین متصل خواهد شد.
                        </div>
                        {Object.values(errors).map((e, i) => <div key={i} className="alert err">{e}</div>)}
                        <form onSubmit={submitDeposit}>
                            <div className="field">
                                <label>مبلغ (تومان)</label>
                                <input type="number" min="1000" value={depositForm.data.amount}
                                    onChange={e => depositForm.setData('amount', e.target.value)} required />
                            </div>
                            <div className="field">
                                <label>توضیح / شماره پیگیری واریز (اختیاری)</label>
                                <input value={depositForm.data.note} onChange={e => depositForm.setData('note', e.target.value)} placeholder="مثلاً شماره پیگیری کارت‌به‌کارت" />
                            </div>
                            <button className="btn" type="submit" disabled={depositForm.processing}>
                                {depositForm.processing ? 'در حال ارسال...' : 'ثبت درخواست'}
                            </button>
                        </form>
                    </div>
                )}

                {showForm && (
                    <div className="fcard" style={{ marginBottom: 28, maxWidth: 480 }}>
                        {Object.values(errors).map((e, i) => <div key={i} className="alert err">{e}</div>)}
                        {bankCards?.length ? (
                            <form onSubmit={submit}>
                                <div className="field">
                                    <label>مبلغ (تومان) — حداکثر {faNum(balance)}</label>
                                    <input type="number" min="1000" max={balance} value={form.data.amount}
                                        onChange={e => form.setData('amount', e.target.value)} required />
                                </div>
                                <div className="field">
                                    <label>کارت بانکی</label>
                                    <SearchableSelect
                                        value={form.data.bank_card_id}
                                        onChange={value => form.setData('bank_card_id', value)}
                                        options={bankCardOptions}
                                        placeholder="— انتخاب کارت —"
                                        searchPlaceholder="جستجو با نام بانک، شماره کارت یا شبا..."
                                        required
                                    />
                                </div>
                                <button className="btn" type="submit" disabled={form.processing}>
                                    {form.processing ? 'در حال ارسال...' : 'ثبت درخواست'}
                                </button>
                            </form>
                        ) : (
                            <div className="alert info">
                                ابتدا باید یک کارت بانکی ثبت کنید. <Link href="/profile" style={{ color: 'var(--gold-1)', fontWeight: 700 }}>افزودن کارت بانکی در پروفایل</Link>
                            </div>
                        )}
                    </div>
                )}

                {deposits?.length > 0 && (
                    <>
                        <div className="section-title">درخواست‌های افزایش موجودی</div>
                        <div className="table-wrap" style={{ marginBottom: 28 }}>
                            <table>
                                <thead><tr><th>تاریخ</th><th>مبلغ</th><th>توضیح</th><th>وضعیت</th><th>یادداشت ادمین</th></tr></thead>
                                <tbody>
                                    {deposits.map(d => {
                                        const [label, cls] = STATUS[d.status] || [d.status, 'silver'];
                                        return (
                                            <tr key={d.id}>
                                                <td style={{ fontSize: 12, color: 'var(--muted)' }}>{d.created_at}</td>
                                                <td className="num">{faNum(d.amount)}</td>
                                                <td style={{ color: 'var(--muted)', fontSize: 13 }}>{d.note || '—'}</td>
                                                <td><span className={`badge ${cls}`}>{label}</span></td>
                                                <td style={{ color: 'var(--muted)', fontSize: 13 }}>{d.admin_note || '—'}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

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
