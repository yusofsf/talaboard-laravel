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
                                <MenuLink href="/cart" hint={user.cart_count > 0 ? `${user.cart_count} مورد انتخاب شده` : 'خریدهای انتخابی'} onClick={() => setOpen(false)}>سبد خرید</MenuLink>
                                <MenuLink href="/inventory" hint="طلا و نقره شما" onClick={() => setOpen(false)}>دارایی‌های من</MenuLink>
                                <MenuLink href="/history" hint="کارهای قبلی" onClick={() => setOpen(false)}>سوابق من</MenuLink>
                                <MenuLink href="/notifications" hint={user.unread_count > 0 ? `${user.unread_count} پیام تازه` : 'پیام‌های سایت'} onClick={() => setOpen(false)}>پیام‌ها</MenuLink>
                                <MenuLink href="/profile" hint="نام و اطلاعات شما" onClick={() => setOpen(false)}>حساب من</MenuLink>
                                {!user.is_admin && <MenuLink href="/tickets" hint="سؤال یا مشکل خود را بنویس" onClick={() => setOpen(false)}>تیکت پشتیبانی</MenuLink>}
                                {showMembershipLink && <MenuLink href="/membership" hint="امکانات بیشتر" onClick={() => setOpen(false)}>عضویت ویژه</MenuLink>}
                                {(user.is_vip || user.membership_level === 2) && <MenuLink href="/trade-room" hint="خرید و فروش با اعضا" onClick={() => setOpen(false)}>اتاق معامله</MenuLink>}
                                {user.is_admin && <MenuLink href="/admin" hint="کارهای مدیر سایت" onClick={() => setOpen(false)}>مدیریت سایت</MenuLink>}
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
            <strong>{children}</strong>
            {hint && <small>{hint}</small>}
        </Link>
    );
}
