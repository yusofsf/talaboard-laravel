import { router, useForm, usePage, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const STATUS = {
    open:     ['در انتظار پاسخ', 'silver'],
    answered: ['پاسخ‌داده‌شده', 'buy-b'],
    resolved: ['حل شد', 'buy-b'],
    closed:   ['بسته‌شده', 'sell-b'],
};

export default function TicketShow({ ticket }) {
    const { errors } = usePage().props;
    const form = useForm({ message: '' });
    const isOver = ticket.status === 'closed' || ticket.status === 'resolved';

    function submit(e) {
        e.preventDefault();
        form.post(`/tickets/${ticket.id}/reply`, { onSuccess: () => form.reset() });
    }

    function markResolved() {
        if (!confirm('مشکل شما حل شد؟ پس از این دیگر نمی‌توانید در این تیکت پیام بفرستید.')) return;
        router.post(`/tickets/${ticket.id}/resolve`);
    }

    const [label, badge] = STATUS[ticket.status] || STATUS.open;

    return (
        <AppLayout>
            <div className="page-wide">
                <div style={{ marginBottom: 16 }}>
                    <Link href="/tickets" className="btn-sm">← بازگشت به تیکت‌ها</Link>
                </div>

                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20, flexWrap: 'wrap', gap: 10 }}>
                    <h2 style={{ fontSize: 20, fontWeight: 800 }}>{ticket.subject}</h2>
                    <span className={`badge ${badge}`}>{label}</span>
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: 14, marginBottom: 24 }}>
                    {ticket.messages.map(m => (
                        <div key={m.id} style={{
                            alignSelf: m.is_admin ? 'flex-end' : 'flex-start',
                            maxWidth: '75%',
                            background: m.is_admin ? 'linear-gradient(160deg,var(--card),var(--card-2))' : 'rgba(246,207,99,.1)',
                            border: `1px solid ${m.is_admin ? 'var(--line)' : 'rgba(246,207,99,.3)'}`,
                            borderRadius: 16, padding: '14px 18px',
                        }}>
                            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6, fontWeight: 700 }}>
                                {m.is_admin ? 'پشتیبانی' : 'شما'}
                            </div>
                            <div style={{ fontSize: 14, lineHeight: 1.8, whiteSpace: 'pre-wrap' }}>{m.message}</div>
                            <div style={{ fontSize: 11, color: 'var(--muted)', marginTop: 8 }}>{m.created_at}</div>
                        </div>
                    ))}
                </div>

                {isOver ? (
                    <div className="alert info">
                        {ticket.status === 'resolved'
                            ? 'شما این تیکت را حل‌شده اعلام کردید. در صورت بروز مشکل جدید، یک تیکت تازه ثبت کنید.'
                            : 'این تیکت بسته شده است. در صورت نیاز یک تیکت جدید ثبت کنید.'}
                    </div>
                ) : (
                    <div className="fcard" style={{ maxWidth: 560 }}>
                        {errors.message && <div className="alert err">{errors.message}</div>}
                        <form onSubmit={submit}>
                            <div className="field"><label>پاسخ شما</label>
                                <textarea rows={4} value={form.data.message} onChange={e => form.setData('message', e.target.value)} required
                                    style={{ width: '100%', padding: '11px 14px', borderRadius: 12, fontFamily: 'inherit', background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', fontSize: 15, resize: 'vertical' }} /></div>
                            <div style={{ display: 'flex', gap: 10 }}>
                                <button className="btn" type="submit" disabled={form.processing} style={{ width: 'auto', padding: '11px 28px' }}>
                                    {form.processing ? '...' : 'ارسال پاسخ'}
                                </button>
                                <button type="button" onClick={markResolved} className="btn-sm" style={{ borderColor: 'rgba(65,225,166,.4)', color: 'var(--up)', background: 'rgba(65,225,166,.08)' }}>
                                    ✅ مشکلم حل شد
                                </button>
                            </div>
                        </form>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
