import { useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const STATUS = {
    open:     ['در انتظار پاسخ', 'silver'],
    answered: ['پاسخ‌داده‌شده', 'buy-b'],
    resolved: ['حل شد', 'buy-b'],
    closed:   ['بسته‌شده', 'sell-b'],
};

export default function TicketsIndex({ tickets }) {
    const { errors } = usePage().props;
    const [showForm, setShowForm] = useState(false);
    const form = useForm({ subject: '', message: '' });

    function submit(e) {
        e.preventDefault();
        form.post('/tickets', { onSuccess: () => { form.reset(); setShowForm(false); } });
    }

    return (
        <AppLayout>
            <div className="page-wide">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20, flexWrap: 'wrap', gap: 10 }}>
                    <h2 style={{ fontSize: 22, fontWeight: 800 }}>🎫 تیکت‌های پشتیبانی</h2>
                    <button onClick={() => setShowForm(s => !s)} className="btn" style={{ width: 'auto', padding: '10px 24px' }}>
                        {showForm ? 'انصراف' : '+ تیکت جدید'}
                    </button>
                </div>

                {showForm && (
                    <div className="fcard" style={{ maxWidth: 560, marginBottom: 24 }}>
                        {errors.subject && <div className="alert err">{errors.subject}</div>}
                        {errors.message && <div className="alert err">{errors.message}</div>}
                        <form onSubmit={submit}>
                            <div className="field"><label>موضوع</label>
                                <input value={form.data.subject} onChange={e => form.setData('subject', e.target.value)} placeholder="مثلاً: مشکل در واریز کیف پول" required /></div>
                            <div className="field"><label>پیام</label>
                                <textarea rows={5} value={form.data.message} onChange={e => form.setData('message', e.target.value)} required
                                    style={{ width: '100%', padding: '11px 14px', borderRadius: 12, fontFamily: 'inherit', background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', fontSize: 15, resize: 'vertical' }} /></div>
                            <button className="btn" type="submit" disabled={form.processing}>{form.processing ? '...' : 'ارسال تیکت'}</button>
                        </form>
                    </div>
                )}

                {tickets.length ? (
                    <div className="table-wrap">
                        <table>
                            <thead><tr><th>موضوع</th><th>وضعیت</th><th>تعداد پیام</th><th>تاریخ</th><th></th></tr></thead>
                            <tbody>
                                {tickets.map(t => {
                                    const [label, badge] = STATUS[t.status] || STATUS.open;
                                    return (
                                        <tr key={t.id}>
                                            <td><strong>{t.subject}</strong></td>
                                            <td><span className={`badge ${badge}`}>{label}</span></td>
                                            <td className="num">{t.msg_count}</td>
                                            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{t.created_at}</td>
                                            <td><Link href={`/tickets/${t.id}`} className="btn-sm">مشاهده</Link></td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="empty"><div className="ico">🎫</div>هنوز تیکتی ثبت نکرده‌اید.</div>
                )}
            </div>
        </AppLayout>
    );
}
