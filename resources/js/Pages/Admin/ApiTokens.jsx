import { useMemo, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const Icon = ({ children, size = 20 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor"
        strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        {children}
    </svg>
);

const KeyIcon = () => <Icon><circle cx="8" cy="15" r="4" /><path d="m11 12 8-8M15 8l2 2M17 6l2 2" /></Icon>;
const UserIcon = () => <Icon><circle cx="12" cy="8" r="4" /><path d="M4 21c.8-4 3.4-6 8-6s7.2 2 8 6" /></Icon>;
const GlobeIcon = () => <Icon><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" /></Icon>;
const CopyIcon = () => <Icon size={18}><rect x="8" y="8" width="11" height="11" rx="2" /><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" /></Icon>;
const TrashIcon = () => <Icon size={17}><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13" /></Icon>;

export default function ApiTokens({ tokens, users, abilities, userAbilities }) {
    const issuedToken = usePage().props.flash?.issued_token;
    const [copied, setCopied] = useState(false);
    const [search, setSearch] = useState('');
    const form = useForm({
        owner_type: 'user',
        user_id: '',
        client_name: '',
        name: '',
        abilities: [],
        expires_at: '',
    });

    const filteredTokens = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fa');
        if (!query) return tokens;
        return tokens.filter(token => [
            token.name,
            token.client_name,
            token.user?.name,
            token.user?.phone,
        ].some(value => String(value || '').toLocaleLowerCase('fa').includes(query)));
    }, [tokens, search]);

    function setOwnerType(ownerType) {
        form.setData({
            ...form.data,
            owner_type: ownerType,
            user_id: ownerType === 'user' ? form.data.user_id : '',
            client_name: ownerType === 'guest' ? form.data.client_name : '',
            abilities: ownerType === 'guest'
                ? form.data.abilities.filter(ability => !userAbilities.includes(ability))
                : form.data.abilities,
        });
    }

    function toggleAbility(ability) {
        form.setData('abilities', form.data.abilities.includes(ability)
            ? form.data.abilities.filter(item => item !== ability)
            : [...form.data.abilities, ability]);
    }

    function submit(event) {
        event.preventDefault();
        form.post('/admin/api-tokens', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    async function copyToken() {
        if (!issuedToken) return;
        await navigator.clipboard.writeText(issuedToken);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    }

    return (
        <AppLayout title="مدیریت توکن‌های API">
            <main className="admin-tool-page">
                <header className="admin-tool-hero">
                    <div className="admin-tool-hero-icon"><KeyIcon /></div>
                    <div>
                        <span className="admin-tool-eyebrow">دسترسی برنامه‌نویسی</span>
                        <h1>مدیریت توکن‌های API</h1>
                        <p>برای هر سرویس فقط دسترسی‌های لازم را صادر کنید. مقدار توکن بعد از ساخت دوباره نمایش داده نمی‌شود.</p>
                    </div>
                    <div className="admin-tool-stat">
                        <strong>{tokens.length.toLocaleString('fa-IR')}</strong>
                        <span>توکن فعال</span>
                    </div>
                </header>

                {issuedToken && (
                    <section className="issued-token-panel" aria-live="polite">
                        <div>
                            <strong>توکن جدید آماده است</strong>
                            <span>همین حالا آن را در محل امن ذخیره کنید.</span>
                        </div>
                        <code dir="ltr">{issuedToken}</code>
                        <button type="button" className="admin-secondary-button" onClick={copyToken}>
                            <CopyIcon /> {copied ? 'کپی شد' : 'کپی توکن'}
                        </button>
                    </section>
                )}

                <div className="admin-tool-grid">
                    <section className="admin-panel">
                        <div className="admin-panel-heading">
                            <div>
                                <span>صدور دسترسی</span>
                                <h2>توکن جدید</h2>
                            </div>
                            <KeyIcon />
                        </div>

                        <form onSubmit={submit} className="api-token-form">
                            <fieldset className="owner-switch">
                                <legend>توکن برای چه کسی صادر می‌شود؟</legend>
                                <button type="button" className={form.data.owner_type === 'user' ? 'active' : ''}
                                    onClick={() => setOwnerType('user')}>
                                    <UserIcon />
                                    <span><strong>کاربر سایت</strong><small>برای سفارش و اطلاعات حساب</small></span>
                                </button>
                                <button type="button" className={form.data.owner_type === 'guest' ? 'active' : ''}
                                    onClick={() => setOwnerType('guest')}>
                                    <GlobeIcon />
                                    <span><strong>سرویس مهمان</strong><small>بدون ثبت‌نام در سایت</small></span>
                                </button>
                            </fieldset>

                            {form.data.owner_type === 'user' ? (
                                <div className="field">
                                    <label htmlFor="token-user">کاربر صاحب توکن</label>
                                    <select id="token-user" value={form.data.user_id}
                                        onChange={event => form.setData('user_id', event.target.value)} required>
                                        <option value="">کاربر را انتخاب کنید</option>
                                        {users.map(user => (
                                            <option key={user.id} value={user.id}>{user.name} — {user.phone}</option>
                                        ))}
                                    </select>
                                    {form.errors.user_id && <small className="form-error">{form.errors.user_id}</small>}
                                </div>
                            ) : (
                                <div className="field">
                                    <label htmlFor="client-name">نام شخص یا سرویس مهمان</label>
                                    <input id="client-name" value={form.data.client_name}
                                        onChange={event => form.setData('client_name', event.target.value)}
                                        placeholder="مثلاً اپلیکیشن حسابداری" maxLength="100" required />
                                    <small className="field-help">برای شناسایی صاحب توکن در پنل مدیریت استفاده می‌شود.</small>
                                    {form.errors.client_name && <small className="form-error">{form.errors.client_name}</small>}
                                </div>
                            )}

                            <div className="field">
                                <label htmlFor="token-name">عنوان توکن</label>
                                <input id="token-name" value={form.data.name}
                                    onChange={event => form.setData('name', event.target.value)}
                                    placeholder="مثلاً اتصال ربات قیمت" maxLength="100" required />
                                {form.errors.name && <small className="form-error">{form.errors.name}</small>}
                            </div>

                            <fieldset className="ability-picker">
                                <legend>قابلیت‌های مجاز</legend>
                                {Object.entries(abilities).map(([ability, label]) => {
                                    const requiresUser = userAbilities.includes(ability);
                                    const disabled = form.data.owner_type === 'guest' && requiresUser;
                                    return (
                                        <label key={ability} className={`${form.data.abilities.includes(ability) ? 'selected' : ''}${disabled ? ' disabled' : ''}`}>
                                            <input type="checkbox" checked={form.data.abilities.includes(ability)}
                                                disabled={disabled} onChange={() => toggleAbility(ability)} />
                                            <span className="ability-check" aria-hidden="true">✓</span>
                                            <span><strong>{label}</strong><code dir="ltr">{ability}</code>{requiresUser && <small>نیازمند حساب کاربری</small>}</span>
                                        </label>
                                    );
                                })}
                            </fieldset>
                            {form.errors.abilities && <small className="form-error">{form.errors.abilities}</small>}

                            <div className="field">
                                <label htmlFor="token-expiry">تاریخ انقضا <span>اختیاری</span></label>
                                <input id="token-expiry" type="datetime-local" dir="ltr" value={form.data.expires_at}
                                    onChange={event => form.setData('expires_at', event.target.value)} />
                                {form.errors.expires_at && <small className="form-error">{form.errors.expires_at}</small>}
                            </div>

                            <button className="admin-primary-button" disabled={form.processing || !form.data.abilities.length}>
                                <KeyIcon /> {form.processing ? 'در حال صدور…' : 'صدور توکن امن'}
                            </button>
                        </form>
                    </section>

                    <section className="admin-panel token-list-panel">
                        <div className="admin-panel-heading">
                            <div><span>دسترسی‌های موجود</span><h2>توکن‌های فعال</h2></div>
                            <span className="count-badge">{filteredTokens.length.toLocaleString('fa-IR')}</span>
                        </div>
                        <div className="admin-list-toolbar">
                            <input type="search" value={search} onChange={event => setSearch(event.target.value)}
                                placeholder="جستجو در نام، کاربر یا شماره موبایل…" aria-label="جستجوی توکن‌ها" />
                        </div>

                        <div className="token-card-list">
                            {filteredTokens.map(token => (
                                <article className="token-record-card" key={token.id}>
                                    <div className="token-record-main">
                                        <div className="token-record-icon"><KeyIcon /></div>
                                        <div>
                                            <h3>{token.name}</h3>
                                            <div className="token-owner">
                                                {token.user ? <UserIcon /> : <GlobeIcon />}
                                                <span>{token.user?.name || token.client_name || 'سرویس مهمان'}</span>
                                                {token.user?.phone && <bdi>{token.user.phone}</bdi>}
                                            </div>
                                        </div>
                                        <span className={`owner-badge ${token.user ? 'user' : 'guest'}`}>
                                            {token.user ? 'کاربر سایت' : 'مهمان'}
                                        </span>
                                    </div>
                                    <div className="token-abilities">
                                        {token.abilities.map(ability => <span key={ability}>{abilities[ability] || ability} <code dir="ltr">{ability}</code></span>)}
                                    </div>
                                    <div className="token-record-footer">
                                        <div>
                                            <small>آخرین استفاده</small>
                                            <span dir="ltr">{token.last_used_at || 'هنوز استفاده نشده'}</span>
                                        </div>
                                        <div>
                                            <small>انقضا</small>
                                            <span dir="ltr">{token.expires_at || 'بدون انقضا'}</span>
                                        </div>
                                        <button type="button" className="admin-danger-button"
                                            onClick={() => confirm('این توکن لغو و غیرفعال شود؟') && router.delete(`/admin/api-tokens/${token.id}`, { preserveScroll: true })}>
                                            <TrashIcon /> لغو توکن
                                        </button>
                                    </div>
                                </article>
                            ))}
                            {!filteredTokens.length && (
                                <div className="admin-empty-state"><KeyIcon /><strong>توکنی پیدا نشد</strong><span>عبارت جستجو را تغییر دهید یا توکن جدید بسازید.</span></div>
                            )}
                        </div>
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}
