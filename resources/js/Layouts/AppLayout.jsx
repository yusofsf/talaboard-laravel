import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';

const FA = s => String(s ?? '').replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]).replace(/,/g, '٬');
export const faNum = n => n == null ? '—' : FA(Number(n).toLocaleString('en'));

function levelLabel(user) {
    if (user.is_admin && (user.is_vip || user.membership_level === 2)) return 'مدیر ویژه';
    if (user.is_admin) return 'مدیر سایت';
    if (user.is_vip || user.membership_level === 2) return 'عضو ویژه';
    return 'عادی';
}

export default function AppLayout({ children }) {
    const page = usePage();
    const { auth, flash, seo, seoDefaults = {} } = page.props;
    const user = auth?.user;
    const [open, setOpen] = useState(false);
    const canonical = seo?.canonical || `${seoDefaults.url || ''}${String(page.url || '/').split('?')[0]}`;
    const title = seo?.title || seoDefaults.title || '';
    const description = seo?.description || seoDefaults.description || '';
    const image = seo?.image || seoDefaults.logo || '';
    const robots = seo?.robots || (seo ? 'index, follow, max-image-preview:large' : 'noindex, nofollow');

    function logout(e) {
        e.preventDefault();
        router.post('/logout');
    }

    const showMembershipLink = user && !user.is_vip && user.membership_level !== 2 && user.membership_status !== 'pending';

    return (
        <>
            <Head>
                <title>{title}</title>
                <meta head-key="description" name="description" content={description} />
                <meta head-key="robots" name="robots" content={robots} />
                <link head-key="canonical" rel="canonical" href={canonical} />
                <link head-key="alternate-fa" rel="alternate" hrefLang="fa-IR" href={canonical} />
                <meta head-key="og-type" property="og:type" content={seo?.type || 'website'} />
                <meta head-key="og-site-name" property="og:site_name" content={seoDefaults.siteName || ''} />
                <meta head-key="og-title" property="og:title" content={title} />
                <meta head-key="og-description" property="og:description" content={description} />
                <meta head-key="og-url" property="og:url" content={canonical} />
                <meta head-key="og-image" property="og:image" content={image} />
                <meta head-key="og-locale" property="og:locale" content={seoDefaults.locale || 'fa_IR'} />
                <meta head-key="twitter-card" name="twitter:card" content={seoDefaults.twitterCard || 'summary_large_image'} />
                <meta head-key="twitter-title" name="twitter:title" content={title} />
                <meta head-key="twitter-description" name="twitter:description" content={description} />
                <meta head-key="twitter-image" name="twitter:image" content={image} />
                {seo?.schema && (
                    <script
                        head-key="page-schema"
                        type="application/ld+json"
                        dangerouslySetInnerHTML={{ __html: JSON.stringify(seo.schema) }}
                    />
                )}
            </Head>
            <nav className="simple-nav">
                <div className="simple-nav-actions">
                    {user && <span className="simple-user-level">{levelLabel(user)}</span>}
                    <button type="button" onClick={() => setOpen(o => !o)} aria-expanded={open} aria-controls="main-menu" className="simple-menu-button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                        <span>منوی ساده</span>
                    </button>
                    {!user && <Link href="/login" className="simple-login-link">ورود یا ثبت‌نام</Link>}

                    {open && <div onClick={() => setOpen(false)} style={{ position: 'fixed', inset: 0, zIndex: 1001 }} />}
                    {open && (
                        <div id="main-menu" className="simple-menu">
                            <div className="simple-menu-title">چه کاری می‌خواهی انجام دهی؟</div>
                            <div className="simple-menu-help">روی هر گزینه بزن؛ هرکدام یک کار مشخص دارد.</div>
                            <div className="simple-menu-group">
                                <MenuLink href="/" hint="قیمت‌ها را ببین" onClick={() => setOpen(false)}>تابلوی قیمت</MenuLink>
                                <MenuLink href="/calculator" hint="حساب‌وکتاب سریع" onClick={() => setOpen(false)}>ماشین حساب</MenuLink>
                                <MenuLink href="/chart" hint="تغییر قیمت‌ها" onClick={() => setOpen(false)}>نمودار قیمت</MenuLink>
                                <MenuLink href="/articles" hint="یاد بگیر" onClick={() => setOpen(false)}>راهنما و مقاله</MenuLink>
                            </div>
                            {user && <div className="simple-menu-group">
                                <MenuLink href="/wallet" hint={user.wallet_balance > 0 ? `${faNum(user.wallet_balance)} تومان` : 'پول شما'} onClick={() => setOpen(false)}>کیف پول</MenuLink>
                                <MenuLink href="/accounting" hint="گردش و مانده‌های من" onClick={() => setOpen(false)}>حسابداری من</MenuLink>
                                <MenuLink href="/cart" hint={user.cart_count > 0 ? `${user.cart_count} مورد انتخاب شده` : 'خریدهای انتخابی'} onClick={() => setOpen(false)}>سبد خرید</MenuLink>
                                <MenuLink href="/inventory" hint="طلا و نقره شما" onClick={() => setOpen(false)}>دارایی‌های من</MenuLink>
                                <MenuLink href="/history" hint="کارهای قبلی" onClick={() => setOpen(false)}>سوابق من</MenuLink>
                                <MenuLink href="/notifications" hint={user.unread_count > 0 ? `${user.unread_count} پیام تازه` : 'پیام‌های سایت'} onClick={() => setOpen(false)}>پیام‌ها</MenuLink>
                                <MenuLink href="/profile" hint="نام و اطلاعات شما" onClick={() => setOpen(false)}>حساب من</MenuLink>
                                {!user.is_admin && <MenuLink href="/tickets" hint="سؤال یا مشکل خود را بنویس" onClick={() => setOpen(false)}>تیکت پشتیبانی</MenuLink>}
                                {showMembershipLink && <MenuLink href="/membership" hint="امکانات بیشتر" onClick={() => setOpen(false)}>عضویت ویژه</MenuLink>}
                                {(user.is_vip || user.membership_level === 2) && <MenuLink href="/trade-room" hint="خرید و فروش با اعضا" onClick={() => setOpen(false)}>اتاق معامله</MenuLink>}
                                {user.is_admin && <MenuLink href="/admin" hint="کارهای مدیر سایت" onClick={() => setOpen(false)}>مدیریت سایت</MenuLink>}
                                {user.is_admin && <MenuLink href="/admin/accounting" hint="گزارش تجمیعی و حرفه‌ای" onClick={() => setOpen(false)}>حسابداری مدیریت</MenuLink>}
                                {user.is_admin && <MenuLink href="/admin/articles" hint="نوشتن و تغییر مقاله" onClick={() => setOpen(false)}>مدیریت مقاله‌ها</MenuLink>}
                                {user.is_admin && <MenuLink href="/admin/online-users" hint="کاربران حاضر" onClick={() => setOpen(false)}>کاربران آنلاین</MenuLink>}
                                <button type="button" onClick={logout} className="simple-menu-link danger"><strong>خروج از حساب</strong><small>بعداً دوباره وارد می‌شوی</small></button>
                            </div>}
                            <div className="simple-menu-group">
                                <MenuLink href="/contact" hint="با ما حرف بزن" onClick={() => setOpen(false)}>تماس با ما</MenuLink>
                                <MenuLink href="/about" hint="ما را بشناس" onClick={() => setOpen(false)}>درباره ما</MenuLink>
                            </div>
                        </div>
                    )}
                </div>
                <Link href="/" className="simple-nav-brand">
                    <img src="/logo.jpg" alt="آبشده صفرپور" />
                    <span>آبشده صفرپور</span>
                </Link>
            </nav>

            {flash?.success && <div className="alert ok" style={{ margin: '16px auto', maxWidth: 700 }}>{flash.success}</div>}
            {flash?.error   && <div className="alert err" style={{ margin: '16px auto', maxWidth: 700 }}>{flash.error}</div>}

            {children}

            <footer style={{
                borderTop: '1px solid var(--line)', marginTop: 48,
                padding: '28px 16px 32px', textAlign: 'center',
            }}>
                <div style={{ display: 'flex', justifyContent: 'center', flexWrap: 'wrap', gap: 18, marginBottom: 14 }}>
                    <a href="https://instagram.com/safarpour.metals" target="_blank" rel="noopener noreferrer"
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
                        safarpour.metals@
                    </a>
                    <a href="https://t.me/sachme_kaf" target="_blank" rel="noopener noreferrer"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: 'var(--muted)', fontSize: 14 }}>
                        <svg width="18" height="18" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="11" fill="#27A7E7" />
                            <path d="M5.6 11.8 16.9 7.3c.5-.2 1 .1.8.9l-1.9 9.1c-.1.6-.5.7-1 .4l-3-2.2-1.4 1.4c-.2.2-.3.3-.6.3l.2-3.1 5.7-5.1c.2-.2 0-.3-.3-.1l-7 4.4-3-1c-.6-.2-.6-.6.1-.9Z" fill="#fff" />
                        </svg>
                        sachme_kaf@
                    </a>
                    <a href="tel:09936578235"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: 'var(--muted)', fontSize: 14 }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M6.6 10.8c1.5 3 3.6 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.5 2.3.8 3.6.8.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.2c.6 0 1 .4 1 1 0 1.3.3 2.5.8 3.6.2.3.1.7-.2 1l-2.2 2.2Z" />
                        </svg>
                        <span>پشتیبانی</span>
                        <span dir="ltr">۰۹۹۳۶۵۷۸۲۳۵</span>
                    </a>
                    <a href="mailto:info@metalsp.ir"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: 'var(--muted)', fontSize: 14 }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                            <rect x="3" y="5" width="18" height="14" rx="2" />
                            <path d="m4 7 8 6 8-6" />
                        </svg>
                        <span dir="ltr">info@metalsp.ir</span>
                    </a>
                </div>
                <div style={{ display: 'inline-flex', alignItems: 'flex-start', gap: 6, color: 'var(--muted)', fontSize: 13, marginBottom: 12, maxWidth: 520, textAlign: 'start' }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0, marginTop: 2 }}>
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z" />
                        <circle cx="12" cy="10" r="3" />
                    </svg>
                    <span>مشهد، بازار امام رضا، طبقه فوقانی بانک ملی، پلاک ۱/۶۳۹</span>
                </div>
                <div style={{ fontSize: 12, color: 'var(--muted)', opacity: .7 }}>
                    آبشده صفرپور — تمامی حقوق محفوظ است
                </div>
            </footer>
        </>
    );
}

function MenuLink({ href, children, hint, onClick }) {
    const current = (usePage().url || '/').split('?')[0];
    const active = current === href;
    return (
        <Link href={href} onClick={onClick} className={`simple-menu-link${active ? ' active' : ''}`}>
            <MenuIcon href={href} />
            <strong>{children}</strong>
            {hint && <small>{hint}</small>}
        </Link>
    );
}

function MenuIcon({ href }) {
    const common = { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '1.8', strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true };
    let icon = <><rect x="4" y="4" width="16" height="16" rx="3" /><path d="M8 12h8M12 8v8" /></>;
    if (href === '/' || href === '/chart' || href === '/history') icon = <><path d="M4 19V5M4 19h16" /><path d="m7 15 4-4 3 2 4-6" /></>;
    if (href === '/calculator') icon = <><rect x="5" y="3" width="14" height="18" rx="2" /><path d="M8 8h8M8 13h.01M12 13h.01M16 13h.01M8 17h.01M12 17h.01M16 17h.01" /></>;
    if (href === '/articles' || href === '/admin/articles') icon = <><path d="M6 3h9l4 4v14H6z" /><path d="M15 3v5h5M9 12h6M9 16h6" /></>;
    if (href === '/wallet') icon = <><path d="M4 7h15a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h12" /><path d="M16 13h3" /></>;
    if (href === '/accounting' || href === '/admin/accounting') icon = <><path d="M4 19V5M4 19h16" /><path d="m7 15 4-4 3 2 4-6" /><path d="M16 5h4v4" /></>;
    if (href === '/cart') icon = <><path d="M3 4h2l2 11h10l2-7H7" /><circle cx="9" cy="20" r="1" /><circle cx="17" cy="20" r="1" /></>;
    if (href === '/inventory') icon = <><path d="m4 8 8-4 8 4-8 4zM4 8v8l8 4 8-4V8M12 12v8" /></>;
    if (href === '/profile') icon = <><circle cx="12" cy="8" r="4" /><path d="M4 21c.8-4 3.4-6 8-6s7.2 2 8 6" /></>;
    if (href === '/membership') icon = <><path d="m4 7 3 3 5-6 5 6 3-3-2 11H6z" /><path d="M9 18h6" /></>;
    if (href === '/tickets' || href === '/contact') icon = <><path d="M5 4h14v11H9l-4 4z" /><path d="M9 9h6M9 12h4" /></>;
    if (href === '/notifications') icon = <><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></>;
    if (href === '/about') icon = <><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></>;
    if (href.startsWith('/admin')) icon = <><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.2 2.2-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.2h-3.2v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-2.2-2.2.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H5V11h.2a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 2.2-2.2.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V4.5h3.2v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 2.2 2.2-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.2v3.2h-.2a1.7 1.7 0 0 0-1.5 1Z" /></>;
    return <svg {...common}>{icon}</svg>;
}
