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
                            <Link href="/speed-test" title="تست سرعت اینترنت">⚡ تست سرعت</Link>
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

            <footer style={{
                borderTop: '1px solid var(--line)', marginTop: 48,
                padding: '28px 16px 32px', textAlign: 'center',
            }}>
                <div style={{ display: 'flex', justifyContent: 'center', flexWrap: 'wrap', gap: 18, marginBottom: 14 }}>
                    <a href="https://instagram.com/noghresazanshargh" target="_blank" rel="noopener noreferrer"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: 'var(--muted)', fontSize: 14 }}>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <defs>
                                <linearGradient id="ig-grad" x1="0" y1="24" x2="24" y2="0">
                                    <stop offset="0" stopColor="#FED576" />
                                    <stop offset=".26" stopColor="#F47133" />
                                    <stop offset=".61" stopColor="#BC3081" />
                                    <stop offset="1" stopColor="#4C63D2" />
                                </linearGradient>
                            </defs>
                            <rect x="2" y="2" width="20" height="20" rx="6" fill="url(#ig-grad)" />
                            <rect x="6.5" y="6.5" width="11" height="11" rx="3.5" fill="none" stroke="#fff" strokeWidth="1.6" />
                            <circle cx="12" cy="12" r="3.2" fill="none" stroke="#fff" strokeWidth="1.6" />
                            <circle cx="17" cy="7" r="1" fill="#fff" />
                        </svg>
                        noghresazanshargh@
                    </a>
                    <a href="https://t.me/sachme_kaf" target="_blank" rel="noopener noreferrer"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: 'var(--muted)', fontSize: 14 }}>
                        <svg width="18" height="18" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="11" fill="#27A7E7" />
                            <path d="M5.6 11.8 16.9 7.3c.5-.2 1 .1.8.9l-1.9 9.1c-.1.6-.5.7-1 .4l-3-2.2-1.4 1.4c-.2.2-.3.3-.6.3l.2-3.1 5.7-5.1c.2-.2 0-.3-.3-.1l-7 4.4-3-1c-.6-.2-.6-.6.1-.9Z" fill="#fff" />
                        </svg>
                        sachme_kaf@
                    </a>
                    <a href="tel:09158952885"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: 'var(--muted)', fontSize: 14 }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M6.6 10.8c1.5 3 3.6 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.5 2.3.8 3.6.8.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.2c.6 0 1 .4 1 1 0 1.3.3 2.5.8 3.6.2.3.1.7-.2 1l-2.2 2.2Z" />
                        </svg>
                        <span dir="ltr">۰۹۱۵۸۹۵۲۸۸۵</span>
                    </a>
                </div>
                <div style={{ fontSize: 12, color: 'var(--muted)', opacity: .7 }}>
                    آبشده صفرپور — تمامی حقوق محفوظ است
                </div>
            </footer>
        </>
    );
}
