import { useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout, { faNum } from '../../Layouts/AppLayout';

export default function OnlineUsers({ users }) {
    useEffect(() => {
        const id = setInterval(() => {
            router.reload({ only: ['users'] });
        }, 15000);
        return () => clearInterval(id);
    }, []);

    return (
        <AppLayout>
            <div className="page-wide">
                <div className="no-print" style={{ marginBottom: 16 }}>
                    <Link href="/admin" className="btn-sm">← بازگشت به پنل مدیریت</Link>
                </div>

                <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 6 }}>🟢 کاربران آنلاین</h2>
                <p style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 20 }}>
                    کاربرانی که در ۵ دقیقه‌ی اخیر فعال بوده‌اند — هر ۱۵ ثانیه به‌روزرسانی می‌شود.
                </p>

                {users.length ? (
                    <div className="table-wrap">
                        <table>
                            <thead><tr><th>نام</th><th>موبایل</th><th>سطح</th><th>آخرین فعالیت</th></tr></thead>
                            <tbody>
                                {users.map(u => (
                                    <tr key={u.id}>
                                        <td><strong>{u.name}</strong></td>
                                        <td dir="ltr">{u.phone}</td>
                                        <td>
                                            {u.is_admin && <span className="badge gold" style={{ marginInlineEnd: 6 }}>ادمین</span>}
                                            {u.is_vip && <span className="badge silver">ویژه</span>}
                                        </td>
                                        <td style={{ fontSize: 12, color: 'var(--muted)' }}>
                                            {u.seconds_ago < 60 ? 'هم‌اکنون' : `${faNum(Math.floor(u.seconds_ago / 60))} دقیقه پیش`}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="empty"><div className="ico">🟢</div>در حال حاضر کاربری آنلاین نیست.</div>
                )}
            </div>
        </AppLayout>
    );
}
