import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppLayout, { faNum } from '../../Layouts/AppLayout';

const TYPE_ICON = { trade: '📊', wallet: '💰', system: '⚙️', promo: '🎁', info: '🔔' };

export default function Dashboard({ users, txns, wTxns, notifs, invites, stats, memberApplications, deliveryRequests }) {
    const { auth } = usePage().props;
    const [tab, setTab] = useState('users');

    const wallet = useForm({ user_id: '', amount: '', description: '' });
    const notify = useForm({ title: '', body: '', type: 'info', target: 'all' });

    function setLevel(uid, level) {
        router.post(`/admin/set-level/${uid}`, { level }, { preserveScroll: true });
    }

    function deleteNotif(id) {
        if (!confirm('حذف شود؟')) return;
        router.delete(`/admin/notify/${id}`, { preserveScroll: true });
    }

    function approveMembership(uid) {
        router.post(`/admin/membership/approve/${uid}`, {}, { preserveScroll: true });
    }

    function rejectMembership(uid) {
        if (!confirm('درخواست رد شود؟')) return;
        router.post(`/admin/membership/reject/${uid}`, {}, { preserveScroll: true });
    }

    function updateDelivery(id, status) {
        if (status === 'rejected' && !confirm('رد شود؟ نقره به موجودی کاربر برمی‌گردد.')) return;
        router.post(`/admin/delivery/${id}/update`, { status }, { preserveScroll: true });
    }

    const TABS = [
        ['users', 'کاربران'],
        ['txns', 'معاملات'],
        ['wallet', 'کیف پول'],
        ['notifs', 'اعلان‌ها'],
        ['codes', 'کدهای دعوت'],
        ['membership', `درخواست‌های عضویت${memberApplications?.length ? ` (${memberApplications.length})` : ''}`],
        ['delivery', `تحویل فیزیکی نقره${deliveryRequests?.length ? ` (${deliveryRequests.length})` : ''}`],
    ];

    return (
        <AppLayout>
            <div className="page-wide">
                <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 20 }}>پنل مدیریت</h2>

                {/* آمار */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 14, marginBottom: 28 }}>
                    {[
                        ['کاربران', stats.user_count, 'var(--gold-1)'],
                        ['معاملات', stats.txn_count, 'var(--txt)'],
                        ['حجم خریدها', faNum(stats.buy_volume) + ' ت', 'var(--up)'],
                        ['حجم فروش‌ها', faNum(stats.sell_volume) + ' ت', 'var(--down)'],
                    ].map(([label, val, color]) => (
                        <div key={label} style={{ background: 'linear-gradient(160deg,var(--card),var(--card-2))', border: '1px solid var(--line)', borderRadius: 18, padding: '20px 22px' }}>
                            <div style={{ fontSize: 12, color: 'var(--muted)', fontWeight: 700, marginBottom: 6 }}>{label}</div>
                            <div style={{ fontSize: 24, fontWeight: 800, color }}>{val}</div>
                        </div>
                    ))}
                </div>

                {/* تب‌ها */}
                <div className="tabs">
                    {TABS.map(([key, label]) => (
                        <button key={key} className={`tab-btn${tab === key ? ' active' : ''}`} onClick={() => setTab(key)}>{label}</button>
                    ))}
                </div>

                {/* کاربران */}
                {tab === 'users' && (
                    <div className="table-wrap">
                        <table>
                            <thead><tr><th>#</th><th>نام</th><th>موبایل</th><th>معاملات</th><th>عضویت از</th><th>سطح</th></tr></thead>
                            <tbody>
                                {users.map(u => (
                                    <tr key={u.id}>
                                        <td className="num" style={{ color: 'var(--muted)' }}>{u.id}</td>
                                        <td><strong>{u.name}</strong></td>
                                        <td className="num" dir="ltr">{u.phone}</td>
                                        <td className="num">{u.txn_count}</td>
                                        <td style={{ fontSize: 12, color: 'var(--muted)' }}>{u.created_at}</td>
                                        <td>
                                            {u.id === auth.user.id ? (
                                                <span className="badge gold">ادمین (شما)</span>
                                            ) : (
                                                <select value={u.is_admin ? 'admin' : u.is_vip ? 'vip' : 'regular'}
                                                    onChange={e => setLevel(u.id, e.target.value)}
                                                    style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '4px 10px', fontFamily: 'inherit', fontSize: 13 }}>
                                                    <option value="regular">عادی</option>
                                                    <option value="vip">👑 ویژه</option>
                                                    <option value="admin">⚙️ ادمین</option>
                                                </select>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* معاملات */}
                {tab === 'txns' && (
                    <div className="table-wrap">
                        <table>
                            <thead><tr><th>تاریخ</th><th>کاربر</th><th>موبایل</th><th>نوع</th><th>کالا</th><th>مقدار</th><th>مبلغ کل</th></tr></thead>
                            <tbody>
                                {txns.map(t => (
                                    <tr key={t.id}>
                                        <td style={{ fontSize: 12, color: 'var(--muted)' }}>{t.created_at}</td>
                                        <td><strong>{t.user_name}</strong></td>
                                        <td className="num" dir="ltr" style={{ fontSize: 13 }}>{t.user_phone}</td>
                                        <td><span className={`badge ${t.type === 'buy' ? 'buy-b' : 'sell-b'}`}>{t.type === 'buy' ? 'خرید' : 'فروش'}</span></td>
                                        <td>{t.item_label}</td>
                                        <td className="num">{t.quantity}</td>
                                        <td className="num"><strong>{faNum(t.total)}</strong></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* کیف پول */}
                {tab === 'wallet' && (
                    <>
                        <div className="fcard" style={{ maxWidth: 520, marginBottom: 20 }}>
                            <h2 style={{ fontSize: 16 }}>شارژ / برداشت کیف پول</h2>
                            <div style={{ height: 16 }} />
                            <form onSubmit={e => { e.preventDefault(); wallet.post('/admin/wallet-credit', { preserveScroll: true, onSuccess: () => wallet.reset() }); }}>
                                <div className="field"><label>کاربر</label>
                                    <select value={wallet.data.user_id} onChange={e => wallet.setData('user_id', e.target.value)} required style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 12, padding: '11px 14px', fontFamily: 'inherit', fontSize: 14, width: '100%' }}>
                                        <option value="">— انتخاب کاربر —</option>
                                        {users.map(u => <option key={u.id} value={u.id}>{u.name} — {u.phone}</option>)}
                                    </select></div>
                                <div className="field"><label>مبلغ (منفی = برداشت)</label>
                                    <input type="number" value={wallet.data.amount} onChange={e => wallet.setData('amount', e.target.value)} placeholder="500000" required /></div>
                                <div className="field"><label>شرح</label>
                                    <input value={wallet.data.description} onChange={e => wallet.setData('description', e.target.value)} placeholder="شارژ دستی" /></div>
                                <button className="btn" type="submit" style={{ width: 'auto', padding: '11px 28px' }}>ثبت تراکنش</button>
                            </form>
                        </div>
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>تاریخ</th><th>کاربر</th><th>نوع</th><th>مبلغ</th><th>شرح</th></tr></thead>
                                <tbody>
                                    {wTxns.map(w => (
                                        <tr key={w.id}>
                                            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{w.created_at}</td>
                                            <td>{w.user_name}</td>
                                            <td><span className={`badge ${w.amount > 0 ? 'buy-b' : 'sell-b'}`}>{w.amount > 0 ? 'واریز' : 'برداشت'}</span></td>
                                            <td className="num" style={{ color: w.amount > 0 ? 'var(--up)' : 'var(--down)', fontWeight: 700 }}>{w.amount > 0 ? '+' : ''}{faNum(w.amount)}</td>
                                            <td style={{ color: 'var(--muted)', fontSize: 13 }}>{w.description || '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {/* اعلان‌ها */}
                {tab === 'notifs' && (
                    <>
                        <div className="fcard" style={{ maxWidth: 520, marginBottom: 20 }}>
                            <h2 style={{ fontSize: 16 }}>ارسال اعلان</h2>
                            <div style={{ height: 16 }} />
                            <form onSubmit={e => { e.preventDefault(); notify.post('/admin/notify', { preserveScroll: true, onSuccess: () => notify.reset() }); }}>
                                <div className="field"><label>عنوان</label>
                                    <input value={notify.data.title} onChange={e => notify.setData('title', e.target.value)} required /></div>
                                <div className="field"><label>متن (اختیاری)</label>
                                    <input value={notify.data.body} onChange={e => notify.setData('body', e.target.value)} /></div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                                    <div className="field"><label>نوع</label>
                                        <select value={notify.data.type} onChange={e => notify.setData('type', e.target.value)} style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 12, padding: '11px 14px', fontFamily: 'inherit', fontSize: 14, width: '100%' }}>
                                            <option value="info">🔔 اطلاعیه</option>
                                            <option value="trade">📊 معامله</option>
                                            <option value="wallet">💰 کیف پول</option>
                                            <option value="promo">🎁 تبلیغات</option>
                                            <option value="system">⚙️ سیستمی</option>
                                        </select></div>
                                    <div className="field"><label>گیرنده</label>
                                        <select value={notify.data.target} onChange={e => notify.setData('target', e.target.value)} style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 12, padding: '11px 14px', fontFamily: 'inherit', fontSize: 14, width: '100%' }}>
                                            <option value="all">همه کاربران</option>
                                            {users.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                                        </select></div>
                                </div>
                                <button className="btn" type="submit" style={{ width: 'auto', padding: '11px 28px' }}>ارسال</button>
                            </form>
                        </div>
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>تاریخ</th><th>عنوان</th><th>نوع</th><th>گیرنده</th><th></th></tr></thead>
                                <tbody>
                                    {notifs.map(n => (
                                        <tr key={n.id}>
                                            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{n.created_at}</td>
                                            <td><strong>{n.title}</strong></td>
                                            <td>{TYPE_ICON[n.type] || '🔔'}</td>
                                            <td style={{ fontSize: 13, color: 'var(--muted)' }}>{n.user_id ? `کاربر #${n.user_id}` : <span className="badge silver">همه</span>}</td>
                                            <td>
                                                <button onClick={() => deleteNotif(n.id)} style={{ padding: '4px 12px', borderRadius: 8, cursor: 'pointer', border: '1px solid rgba(255,107,120,.4)', background: 'rgba(255,107,120,.08)', color: '#ff6b78', fontFamily: 'inherit', fontSize: 12 }}>حذف</button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {/* کدهای دعوت */}
                {tab === 'codes' && (
                    <>
                        <div style={{ marginBottom: 16 }}>
                            <button onClick={() => router.post('/admin/generate-code', {}, { preserveScroll: true })}
                                className="btn" style={{ width: 'auto', padding: '10px 24px', display: 'inline-block' }}>
                                + تولید کد جدید
                            </button>
                        </div>
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>کد</th><th>وضعیت</th><th>استفاده‌کننده</th><th>موبایل</th><th>تاریخ استفاده</th><th>تاریخ ایجاد</th></tr></thead>
                                <tbody>
                                    {invites.map(c => (
                                        <tr key={c.id}>
                                            <td><code style={{ fontSize: 15, letterSpacing: 3, color: 'var(--gold-1)' }}>{c.code}</code></td>
                                            <td><span className={`badge ${c.used_by_name ? 'sell-b' : 'silver'}`}>{c.used_by_name ? 'استفاده‌شده' : 'آزاد'}</span></td>
                                            <td>{c.used_by_name || '—'}</td>
                                            <td dir="ltr" className="num">{c.used_by_phone || '—'}</td>
                                            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{c.used_at || '—'}</td>
                                            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{c.created_at}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {/* درخواست‌های عضویت ویژه */}
                {tab === 'membership' && (
                    memberApplications?.length ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            {memberApplications.map(m => (
                                <div key={m.id} className="fcard" style={{ padding: 20 }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 10, marginBottom: 14 }}>
                                        <div>
                                            <strong style={{ fontSize: 16 }}>{m.name}</strong>
                                            <span style={{ color: 'var(--muted)', fontSize: 13, marginInlineStart: 10 }} dir="ltr">{m.phone}</span>
                                            {m.national_id && <span style={{ color: 'var(--muted)', fontSize: 13, marginInlineStart: 10 }}>کد ملی: {m.national_id}</span>}
                                        </div>
                                        <span style={{ fontSize: 12, color: 'var(--muted)' }}>ارسال: {m.submitted_at}</span>
                                    </div>

                                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 14, marginBottom: 16 }}>
                                        <div>
                                            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>تصویر کارت ملی</div>
                                            {m.national_id_doc
                                                ? <a href={m.national_id_doc} target="_blank" rel="noopener noreferrer">
                                                    <img src={m.national_id_doc} alt="کارت ملی" style={{ width: '100%', borderRadius: 10, border: '1px solid var(--line)' }} />
                                                  </a>
                                                : <div style={{ color: 'var(--muted)' }}>—</div>}
                                        </div>
                                        <div>
                                            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>تصویر مدرک شناسایی</div>
                                            {m.identity_doc
                                                ? <a href={m.identity_doc} target="_blank" rel="noopener noreferrer">
                                                    <img src={m.identity_doc} alt="مدرک شناسایی" style={{ width: '100%', borderRadius: 10, border: '1px solid var(--line)' }} />
                                                  </a>
                                                : <div style={{ color: 'var(--muted)' }}>—</div>}
                                        </div>
                                        <div>
                                            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>فیلم اعتبارسنجی</div>
                                            {m.verification_video
                                                ? <video src={m.verification_video} controls style={{ width: '100%', borderRadius: 10, border: '1px solid var(--line)' }} />
                                                : <div style={{ color: 'var(--muted)' }}>—</div>}
                                        </div>
                                    </div>

                                    <div style={{ display: 'flex', gap: 10 }}>
                                        <button onClick={() => approveMembership(m.id)} className="btn"
                                            style={{ width: 'auto', padding: '9px 22px', background: 'linear-gradient(135deg,var(--up),#1f9d72)' }}>
                                            تأیید عضویت ویژه
                                        </button>
                                        <button onClick={() => rejectMembership(m.id)} className="btn-sm danger" style={{ padding: '9px 22px' }}>
                                            رد درخواست
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="empty"><div className="ico">👑</div>درخواست عضویت ویژه‌ای در انتظار بررسی نیست.</div>
                    )
                )}

                {/* تحویل فیزیکی نقره */}
                {tab === 'delivery' && (
                    deliveryRequests?.length ? (
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>کاربر</th><th>موبایل</th><th>عیار</th><th>مقدار</th><th>گیرنده</th><th>آدرس</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
                                <tbody>
                                    {deliveryRequests.map(r => (
                                        <tr key={r.id}>
                                            <td><strong>{r.user_name}</strong></td>
                                            <td className="num" dir="ltr" style={{ fontSize: 13 }}>{r.user_phone}</td>
                                            <td>{r.purity}</td>
                                            <td className="num">{r.grams} گرم</td>
                                            <td>{r.recipient_name}<br /><span dir="ltr" style={{ fontSize: 12, color: 'var(--muted)' }}>{r.phone}</span></td>
                                            <td style={{ fontSize: 12, color: 'var(--muted)', maxWidth: 220 }}>{r.address}</td>
                                            <td>
                                                <span className={`badge ${r.status === 'pending' ? 'silver' : r.status === 'rejected' ? 'sell-b' : 'buy-b'}`}>
                                                    {{ pending: 'در انتظار', approved: 'تأییدشده', shipped: 'ارسال‌شده', rejected: 'رد‌شده' }[r.status]}
                                                </span>
                                            </td>
                                            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{r.created_at}</td>
                                            <td>
                                                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                                    {r.status === 'pending' && (
                                                        <button onClick={() => updateDelivery(r.id, 'approved')} className="btn-sm">تأیید</button>
                                                    )}
                                                    {r.status === 'approved' && (
                                                        <button onClick={() => updateDelivery(r.id, 'shipped')} className="btn-sm">ارسال شد</button>
                                                    )}
                                                    {r.status === 'shipped' && (
                                                        <button onClick={() => updateDelivery(r.id, 'delivered')} className="btn-sm" style={{ borderColor: 'rgba(65,225,166,.4)', color: 'var(--up)', background: 'rgba(65,225,166,.08)' }}>تحویل داده شد</button>
                                                    )}
                                                    {r.status !== 'rejected' && (
                                                        <button onClick={() => updateDelivery(r.id, 'rejected')} className="btn-sm danger">رد</button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="empty"><div className="ico">🚚</div>درخواست تحویل فیزیکی‌ای ثبت نشده.</div>
                    )
                )}

            </div>
        </AppLayout>
    );
}
