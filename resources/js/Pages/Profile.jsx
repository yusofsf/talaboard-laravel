import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

export default function Profile({ user, bankCards }) {
    const info = useForm({
        name: user.name || '', phone: user.phone || '',
        email: user.email || '', national_id: user.national_id || '',
    });
    const pw = useForm({ old_password: '', new_password: '', new_password_confirmation: '' });

    const [showCardForm, setShowCardForm] = useState(false);
    const cardForm = useForm({ bank_name: '', card_number: '', account_number: '', shaba: '' });

    function submitCard(e) {
        e.preventDefault();
        cardForm.post('/profile/bank-cards', { onSuccess: () => { cardForm.reset(); setShowCardForm(false); } });
    }

    function destroyCard(id) {
        if (!confirm('این کارت بانکی حذف شود؟')) return;
        router.delete(`/profile/bank-cards/${id}`);
    }

    return (
        <AppLayout>
            <div className="page">
                <div className="fcard" style={{ marginBottom: 24 }}>
                    <h2>اطلاعات حساب</h2>
                    <div style={{ height: 20 }} />
                    {Object.values(info.errors).map((e, i) => <div key={i} className="alert err">{e}</div>)}
                    <form onSubmit={e => { e.preventDefault(); info.post('/profile/info'); }}>
                        <div className="field"><label>نام</label>
                            <input value={info.data.name} onChange={e => info.setData('name', e.target.value)} required /></div>
                        <div className="field"><label>موبایل</label>
                            <input type="tel" inputMode="numeric" value={info.data.phone} onChange={e => info.setData('phone', e.target.value)} required /></div>
                        <div className="field"><label>ایمیل</label>
                            <input type="email" value={info.data.email} onChange={e => info.setData('email', e.target.value)} /></div>
                        <div className="field"><label>کد ملی</label>
                            <input inputMode="numeric" value={info.data.national_id} onChange={e => info.setData('national_id', e.target.value)} /></div>
                        <button className="btn" type="submit" disabled={info.processing}>ذخیره</button>
                    </form>
                </div>

                <div className="fcard">
                    <h2>تغییر رمز عبور</h2>
                    <div style={{ height: 20 }} />
                    {Object.values(pw.errors).map((e, i) => <div key={i} className="alert err">{e}</div>)}
                    <form onSubmit={e => { e.preventDefault(); pw.post('/profile/password'); }}>
                        <div className="field"><label>رمز فعلی</label>
                            <input type="password" value={pw.data.old_password} onChange={e => pw.setData('old_password', e.target.value)} required /></div>
                        <div className="field"><label>رمز جدید</label>
                            <input type="password" value={pw.data.new_password} onChange={e => pw.setData('new_password', e.target.value)} required /></div>
                        <div className="field"><label>تکرار رمز جدید</label>
                            <input type="password" value={pw.data.new_password_confirmation} onChange={e => pw.setData('new_password_confirmation', e.target.value)} required /></div>
                        <button className="btn" type="submit" disabled={pw.processing}>تغییر رمز</button>
                    </form>
                </div>

                <div className="fcard" style={{ marginTop: 24 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 10 }}>
                        <h2>💳 کارت‌های بانکی</h2>
                        <button onClick={() => setShowCardForm(s => !s)} className="btn-sm" style={{ borderColor: 'rgba(246,207,99,.4)', color: 'var(--gold-1)', background: 'rgba(246,207,99,.08)' }}>
                            {showCardForm ? 'انصراف' : '+ افزودن کارت'}
                        </button>
                    </div>
                    <div style={{ height: 16 }} />

                    {showCardForm && (
                        <div style={{ marginBottom: 20 }}>
                            {Object.values(cardForm.errors).map((e, i) => <div key={i} className="alert err">{e}</div>)}
                            <form onSubmit={submitCard}>
                                <div className="field"><label>نام بانک (اختیاری)</label>
                                    <input value={cardForm.data.bank_name} onChange={e => cardForm.setData('bank_name', e.target.value)} placeholder="مثلاً ملت" /></div>
                                <div className="field"><label>شماره کارت (۱۶ رقم)</label>
                                    <input value={cardForm.data.card_number} dir="ltr" inputMode="numeric" maxLength={16}
                                        onChange={e => cardForm.setData('card_number', e.target.value)} placeholder="6037xxxxxxxxxxxx" required /></div>
                                <div className="field"><label>شماره حساب (اختیاری)</label>
                                    <input value={cardForm.data.account_number} dir="ltr" onChange={e => cardForm.setData('account_number', e.target.value)} /></div>
                                <div className="field"><label>شماره شبا</label>
                                    <input value={cardForm.data.shaba} dir="ltr" onChange={e => cardForm.setData('shaba', e.target.value)} placeholder="IRxxxxxxxxxxxxxxxxxxxxxxxx" required /></div>
                                <button className="btn" type="submit" disabled={cardForm.processing}>
                                    {cardForm.processing ? 'در حال ذخیره...' : 'ذخیره کارت'}
                                </button>
                            </form>
                        </div>
                    )}

                    {bankCards?.length ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                            {bankCards.map(c => (
                                <div key={c.id} style={{
                                    display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 10,
                                    background: 'rgba(255,255,255,.04)', border: '1px solid var(--line)', borderRadius: 12, padding: '12px 16px',
                                }}>
                                    <div>
                                        <div style={{ fontWeight: 700 }}>{c.bank_name || 'کارت بانکی'}</div>
                                        <div dir="ltr" style={{ fontSize: 13, color: 'var(--muted)' }}>{c.card_number}</div>
                                        <div dir="ltr" style={{ fontSize: 12, color: 'var(--muted)' }}>{c.shaba}</div>
                                    </div>
                                    <button onClick={() => destroyCard(c.id)} className="btn-sm danger">حذف</button>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div style={{ color: 'var(--muted)', fontSize: 13 }}>هنوز کارت بانکی‌ای ثبت نکرده‌اید.</div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
