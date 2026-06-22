import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const STATUS = {
    open:     ['در انتظار پاسخ', 'silver'],
    answered: ['پاسخ‌داده‌شده', 'buy-b'],
    closed:   ['بسته‌شده', 'sell-b'],
};

export default function AdminTicketShow({ ticket }) {
    const { errors } = usePage().props;
    const form = useForm({ message: '' });

    function submit(e) {
        e.preventDefault();
        form.post(`/admin/tickets/${ticket.id}/reply`, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    function closeTicket() {
        if (!confirm('این تیکت بسته شود؟')) return;
        router.post(`/admin/tickets/${ticket.id}/close`, {}, { preserveScroll: true });
    }

    const [label, badge] = STATUS[ticket.status] || STATUS.open;

    return (
        <AppLayout>
            <div className="page-wide">
                <div className="no-print" style={{ marginBottom: 16 }}>
                    <Link href="/admin" className="btn-sm">← بازگشت به پنل مدیریت</Link>
                </div>

                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6, flexWrap: 'wrap', gap: 10 }}>
                    <h2 style={{ fontSize: 20, fontWeight: 800 }}>{ticket.subject}</h2>
                    <span className={`badge ${badge}`}>{label}</span>
                </div>
                <div style={{ color: 'var(--muted)', fontSize: 14, marginBottom: 20 }}>
                    <strong style={{ color: 'var(--txt)' }}>{ticket.user_name}</strong>
                    <span dir="ltr" style={{ marginInlineStart: 10 }}>{ticket.user_phone}</span>
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: 14, marginBottom: 24 }}>
                    {ticket.messages.map(m => (
                        <div key={m.id} style={{
                            alignSelf: m.is_admin ? 'flex-end' : 'flex-start',
                            maxWidth: '75%',
                            background: m.is_admin ? 'linear-gradient(135deg,rgba(246,207,99,.18),rgba(199,154,46,.1))' : 'linear-gradient(160deg,var(--card),var(--card-2))',
                            border: `1px solid ${m.is_admin ? 'rgba(246,207,99,.3)' : 'var(--line)'}`,
                            borderRadius: 16, padding: '14px 18px',
                        }}>
                            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6, fontWeight: 700 }}>
                                {m.is_admin ? `ادمین: ${m.admin_name || '—'}` : ticket.user_name}
                            </div>
                            <div style={{ fontSize: 14, lineHeight: 1.8, whiteSpace: 'pre-wrap' }}>{m.message}</div>
                            <div style={{ fontSize: 11, color: 'var(--muted)', marginTop: 8 }}>{m.created_at}</div>
                        </div>
                    ))}
                </div>

                {ticket.status === 'closed' ? (
                    <div className="alert info">این تیکت بسته شده است.</div>
                ) : (
                    <div className="fcard" style={{ maxWidth: 560, marginBottom: 16 }}>
                        {errors.message && <div className="alert err">{errors.message}</div>}
                        <form onSubmit={submit}>
                            <div className="field"><label>پاسخ ادمین</label>
                                <textarea rows={4} value={form.data.message} onChange={e => form.setData('message', e.target.value)} required
                                    style={{ width: '100%', padding: '11px 14px', borderRadius: 12, fontFamily: 'inherit', background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', fontSize: 15, resize: 'vertical' }} /></div>
                            <div style={{ display: 'flex', gap: 10 }}>
                                <button className="btn" type="submit" disabled={form.processing} style={{ width: 'auto', padding: '11px 28px' }}>
                                    {form.processing ? '...' : 'ارسال پاسخ'}
                                </button>
                                <button type="button" onClick={closeTicket} className="btn-sm danger">بستن تیکت</button>
                            </div>
                        </form>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
