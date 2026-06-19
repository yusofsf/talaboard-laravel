import { router } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

const TYPE_ICON = { trade: '📊', wallet: '💰', system: '⚙️', promo: '🎁', info: '🔔' };

export default function Notifications({ notifications }) {
    function markRead(id) {
        router.post(`/notifications/read/${id}`, {}, { preserveScroll: true });
    }
    function markAll() {
        router.post('/notifications/read/all', {}, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <div className="page">
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 20, flexWrap: 'wrap', gap: 10 }}>
                    <h2 style={{ fontSize: 22, fontWeight: 800 }}>اعلان‌ها</h2>
                    {notifications.length > 0 && (
                        <button onClick={markAll} style={{
                            padding: '7px 16px', borderRadius: 999, fontFamily: 'inherit', fontSize: 13, cursor: 'pointer',
                            border: '1px solid var(--line)', background: 'rgba(255,255,255,.06)', color: 'var(--txt)',
                        }}>علامت‌گذاری همه</button>
                    )}
                </div>

                {notifications.length ? notifications.map(n => (
                    <div key={n.id} style={{
                        background: n.is_read
                            ? 'linear-gradient(160deg,var(--card),var(--card-2))'
                            : 'linear-gradient(160deg,#1d2440,#1e2b50)',
                        border: `1px solid ${n.is_read ? 'var(--line)' : 'rgba(246,207,99,.35)'}`,
                        borderRadius: 16, padding: '18px 20px', marginBottom: 12,
                        display: 'flex', gap: 16, alignItems: 'flex-start',
                    }}>
                        <div style={{ fontSize: 28, flexShrink: 0 }}>{TYPE_ICON[n.type] || '🔔'}</div>
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <div style={{ fontWeight: 700, fontSize: 15, marginBottom: 4 }}>{n.title}</div>
                            {n.body && <div style={{ fontSize: 13, color: 'var(--muted)', lineHeight: 1.7 }}>{n.body}</div>}
                            <div style={{ fontSize: 11, color: 'var(--muted)', marginTop: 8 }}>{n.created_at}</div>
                        </div>
                        {!n.is_read && (
                            <button onClick={() => markRead(n.id)} title="خوانده شد" style={{
                                background: 'none', border: 'none', cursor: 'pointer', color: 'var(--gold-1)', fontSize: 18,
                            }}>✓</button>
                        )}
                        {!n.is_read && (
                            <div style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--gold-1)', flexShrink: 0, marginTop: 8 }} />
                        )}
                    </div>
                )) : (
                    <div className="empty"><div className="ico">🔔</div><div>اعلانی وجود ندارد.</div></div>
                )}
            </div>
        </AppLayout>
    );
}
