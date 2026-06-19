import { Link, router, usePage } from '@inertiajs/react';

const FA = s => String(s ?? '').replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]).replace(/,/g, '٬');
export const faNum = n => n == null ? '—' : FA(Number(n).toLocaleString('en'));

export default function AppLayout({ children, title }) {
    const { auth, flash } = usePage().props;
    const user = auth?.user;

    function logout(e) {
        e.preventDefault();
        router.post('/logout');
    }

    return (
        <>
            <nav>
                <Link href="/" className="nav-brand">
                    <div className="ico">←</div>
                    <span>آبشده صفرپور</span>
                </Link>
                <div className="nav-links">
                    <Link href="/">تابلوی قیمت</Link>
                    {user ? (
                        <>
                            <Link href="/history">سوابق</Link>
                            <Link href="/wallet" title="کیف پول" style={{ position: 'relative' }}>
                                💰{' '}
                                {user.wallet_balance > 0 && (
                                    <span style={{ fontSize: 11, color: 'var(--gold-1)' }}>
                                        {faNum(user.wallet_balance)}
                                    </span>
                                )}
                            </Link>
                            <Link href="/notifications" style={{ position: 'relative' }}>
                                🔔
                                {user.unread_count > 0 && (
                                    <span className="nav-badge">{user.unread_count}</span>
                                )}
                            </Link>
                            <Link href="/membership">
                                {user.is_vip ? '👑 ویژه' : 'عضویت ویژه'}
                            </Link>
                            {user.is_admin && (
                                <Link href="/admin" className="btn-gold">مدیریت</Link>
                            )}
                            <Link href="/profile" className="nav-user">{user.name}</Link>
                            <button onClick={logout}>خروج</button>
                        </>
                    ) : (
                        <>
                            <Link href="/login">ورود</Link>
                            <Link href="/register" className="btn-gold">ثبت‌نام</Link>
                        </>
                    )}
                </div>
            </nav>

            {flash?.success && <div className="alert ok" style={{ margin: '16px auto', maxWidth: 700 }}>{flash.success}</div>}
            {flash?.error   && <div className="alert err" style={{ margin: '16px auto', maxWidth: 700 }}>{flash.error}</div>}

            {children}
        </>
    );
}
