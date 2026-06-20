import { useForm, usePage } from '@inertiajs/react';
import AppLayout, { faNum } from '../Layouts/AppLayout';

const STATUS_LABEL = {
    pending: ['در انتظار بررسی', 'silver'],
    approved: ['تأیید شد', 'buy-b'],
    shipped: ['ارسال شد', 'buy-b'],
    delivered: ['تحویل داده شد', 'buy-b'],
    rejected: ['رد شد', 'sell-b'],
};

export default function SilverDelivery({ requests, silverBalance }) {
    const { errors } = usePage().props;
    const form = useForm({ purity: '999', grams: '', recipient_name: '', phone: '', address: '' });

    function submit(e) {
        e.preventDefault();
        form.post('/silver-delivery', { onSuccess: () => form.reset() });
    }

    return (
        <AppLayout>
            <div className="page">
                <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 8 }}>🚚 تحویل فیزیکی نقره</h2>
                <p style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 20 }}>
                    نقره‌ی موجود در حساب دیجیتال خود را به‌صورت فیزیکی درخواست کنید.
                </p>

                <div style={{ display: 'flex', gap: 14, marginBottom: 24 }}>
                    {['999', '995'].map(p => (
                        <div key={p} style={{
                            flex: 1, background: 'linear-gradient(160deg,var(--card),var(--card-2))',
                            border: '1px solid var(--line)', borderRadius: 16, padding: '14px 16px',
                        }}>
                            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 4 }}>موجودی {p}</div>
                            <div style={{ fontSize: 18, fontWeight: 800, color: 'var(--silver-1)' }}>{faNum(silverBalance[p])} گرم</div>
                        </div>
                    ))}
                </div>

                <div className="fcard" style={{ marginBottom: 24 }}>
                    <h2 style={{ fontSize: 16 }}>ثبت درخواست جدید</h2>
                    <div style={{ height: 16 }} />
                    {errors.grams && <div className="alert err">{errors.grams}</div>}
                    <form onSubmit={submit}>
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
                                <input type="number" step="any" min="1" value={form.data.grams}
                                    onChange={e => form.setData('grams', e.target.value)} required />
                            </div>
                        </div>
                        <div className="field">
                            <label>نام تحویل‌گیرنده</label>
                            <input value={form.data.recipient_name} onChange={e => form.setData('recipient_name', e.target.value)} required />
                        </div>
                        <div className="field">
                            <label>شماره موبایل</label>
                            <input type="tel" inputMode="numeric" value={form.data.phone} onChange={e => form.setData('phone', e.target.value)} required />
                        </div>
                        <div className="field">
                            <label>آدرس کامل</label>
                            <textarea value={form.data.address} onChange={e => form.setData('address', e.target.value)} required
                                rows={3} style={{ width: '100%', padding: '11px 14px', borderRadius: 12, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', fontFamily: 'inherit', fontSize: 15 }} />
                        </div>
                        <button className="btn" type="submit" disabled={form.processing}>
                            {form.processing ? '...' : 'ثبت درخواست'}
                        </button>
                    </form>
                </div>

                <div style={{ fontSize: 16, fontWeight: 800, marginBottom: 14, paddingBottom: 10, borderBottom: '1px solid var(--line)' }}>
                    درخواست‌های قبلی
                </div>
                {requests.length ? (
                    <div className="table-wrap">
                        <table>
                            <thead><tr><th>عیار</th><th>مقدار</th><th>وضعیت</th><th>یادداشت ادمین</th><th>تاریخ</th></tr></thead>
                            <tbody>
                                {requests.map(r => {
                                    const [label, cls] = STATUS_LABEL[r.status] || [r.status, 'silver'];
                                    return (
                                        <tr key={r.id}>
                                            <td>{r.purity}</td>
                                            <td className="num">{r.grams} گرم</td>
                                            <td><span className={`badge ${cls}`}>{label}</span></td>
                                            <td style={{ color: 'var(--muted)', fontSize: 13 }}>{r.admin_note || '—'}</td>
                                            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{r.created_at}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="empty"><div className="ico">📦</div>هنوز درخواستی ثبت نکرده‌اید.</div>
                )}
            </div>
        </AppLayout>
    );
}
