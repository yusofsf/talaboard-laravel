import { useMemo, useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout, { faNum } from '../../Layouts/AppLayout';
import Pager, { usePager } from '../../Components/Pager';
import DateRangeFilter, { filterByDateRange } from '../../Components/DateRangeFilter';
import SearchBox, { filterBySearch } from '../../Components/SearchBox';
import SearchableSelect from '../../Components/SearchableSelect';

const LOG_CAT = {
    auth:       { label: 'ورود/احراز', badge: 'silver' },
    trade:      { label: 'معامله',      badge: 'gold' },
    wallet:     { label: 'کیف پول',      badge: 'buy-b' },
    admin:      { label: 'مدیریت',       badge: 'sell-b' },
    membership: { label: 'عضویت',        badge: 'silver' },
    ticket:     { label: 'تیکت',         badge: 'gold' },
    other:      { label: 'سایر',         badge: 'silver' },
};

const TYPE_ICON = { trade: '📊', wallet: '💰', system: '⚙️', promo: '🎁', info: '🔔' };

const TICKET_STATUS = {
    open:     ['در انتظار پاسخ', 'silver'],
    answered: ['پاسخ‌داده‌شده', 'buy-b'],
    resolved: ['حل شد', 'buy-b'],
    closed:   ['بسته‌شده', 'sell-b'],
};

function levelOf(u) {
    if (u.is_admin && (u.is_vip || u.membership_level === 2)) return 'vip_admin';
    if (u.is_admin) return 'admin';
    if (u.is_vip || u.membership_level === 2) return 'vip';
    return 'regular';
}

function AdminMembershipForm({ member = null, userOptions }) {
    const [open, setOpen] = useState(!member);
    const form = useForm({
        user_id: '',
        national_id: member?.national_id || '',
        birth_date: member?.birth_date_raw || '',
        residence_address: member?.residence_address || '',
        national_id_doc: null,
        identity_doc: null,
        verification_video: null,
    });
    const isEdit = Boolean(member);

    function submit(e) {
        e.preventDefault();
        const url = isEdit ? `/admin/membership/${member.id}` : '/admin/membership';
        form.post(url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                if (isEdit) setOpen(false);
                else form.reset();
            },
        });
    }

    if (isEdit && !open) {
        return <button type="button" className="btn-sm" onClick={() => setOpen(true)}>ویرایش اطلاعات عضویت</button>;
    }

    return (
        <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
            {!isEdit && (
                <div className="field">
                    <label>کاربر *</label>
                    <SearchableSelect value={form.data.user_id} onChange={value => form.setData('user_id', value)}
                        options={userOptions} placeholder="کاربر را جستجو و انتخاب کنید" required />
                    {form.errors.user_id && <small style={{ color: 'var(--down)' }}>{form.errors.user_id}</small>}
                </div>
            )}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(190px,1fr))', gap: 12 }}>
                <div className="field">
                    <label>کد ملی (اختیاری)</label>
                    <input value={form.data.national_id} onChange={e => form.setData('national_id', e.target.value)} inputMode="numeric" />
                    {form.errors.national_id && <small style={{ color: 'var(--down)' }}>{form.errors.national_id}</small>}
                </div>
                <div className="field">
                    <label>تاریخ تولد (اختیاری)</label>
                    <input type="date" value={form.data.birth_date} onChange={e => form.setData('birth_date', e.target.value)} />
                    {form.errors.birth_date && <small style={{ color: 'var(--down)' }}>{form.errors.birth_date}</small>}
                </div>
            </div>
            <div className="field">
                <label>آدرس محل سکونت (اختیاری)</label>
                <textarea value={form.data.residence_address} onChange={e => form.setData('residence_address', e.target.value)} maxLength="500" rows="2" />
                {form.errors.residence_address && <small style={{ color: 'var(--down)' }}>{form.errors.residence_address}</small>}
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(190px,1fr))', gap: 12 }}>
                {[
                    ['national_id_doc', 'تصویر کارت ملی', 'image/jpeg,image/png'],
                    ['identity_doc', 'تصویر جواز صنفی', 'image/jpeg,image/png'],
                    ['verification_video', 'فیلم اعتبارسنجی', 'video/mp4,video/quicktime,video/x-msvideo,video/webm'],
                ].map(([field, label, accept]) => (
                    <div className="field" key={field}>
                        <label>{label} (اختیاری{isEdit ? ' — برای جایگزینی' : ''})</label>
                        <input type="file" accept={accept} onChange={e => form.setData(field, e.target.files?.[0] || null)} />
                        {form.errors[field] && <small style={{ color: 'var(--down)' }}>{form.errors[field]}</small>}
                    </div>
                ))}
            </div>
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                <button type="submit" className="btn" disabled={form.processing} style={{ width: 'auto', padding: '9px 20px' }}>
                    {form.processing ? 'در حال ذخیره...' : isEdit ? 'ذخیره اطلاعات' : 'فعال‌سازی عضویت ویژه'}
                </button>
                {isEdit && <button type="button" className="btn-sm" onClick={() => setOpen(false)}>انصراف</button>}
            </div>
        </form>
    );
}

function UserRow({ u, isSelf }) {
    const [expand, setExpand] = useState(null); // null | 'inventory' | 'edit' | 'password'

    const inv = useForm({ metal: 'gold', purity: '999', grams: '', description: '' });
    const edit = useForm({ name: u.name, phone: u.phone, email: u.email || '', national_id: u.national_id || '' });
    const passwordReset = useForm({ password: '', password_confirmation: '' });

    function setLevel(level) {
        router.post(`/admin/set-level/${u.id}`, { level }, { preserveScroll: true });
    }

    function submitInventory(e) {
        e.preventDefault();
        router.post(`/admin/inventory-adjust/${u.id}`, inv.data, {
            preserveScroll: true,
            onSuccess: () => { inv.reset('grams', 'description'); setExpand(null); },
        });
    }

    function submitEdit(e) {
        e.preventDefault();
        router.put(`/admin/users/${u.id}`, edit.data, { preserveScroll: true, onSuccess: () => setExpand(null) });
    }

    function submitPasswordReset(e) {
        e.preventDefault();
        passwordReset.post(`/admin/users/${u.id}/password`, {
            preserveScroll: true,
            onSuccess: () => { passwordReset.reset(); setExpand(null); },
        });
    }

    function destroyUser() {
        if (!confirm(`کاربر «${u.name}» حذف شود؟ این عمل قابل بازگشت نیست.`)) return;
        router.delete(`/admin/users/${u.id}`, { preserveScroll: true });
    }

    return (
        <>
            <tr>
                <td className="num" style={{ color: 'var(--muted)' }}>{u.id}</td>
                <td><strong>{u.name}</strong></td>
                <td className="num" dir="ltr">{u.phone}</td>
                <td className="num">{u.txn_count}</td>
                <td className="num" style={{ fontSize: 13 }}>{faNum(u.wallet_balance)}</td>
                <td className="num" style={{ fontSize: 13 }}>{faNum(u.gold_balance)} گ</td>
                <td className="num" style={{ fontSize: 13 }}>{faNum(u.silver_balance['999'])}/{faNum(u.silver_balance['995'])} گ</td>
                <td style={{ fontSize: 12, color: 'var(--muted)' }}>{u.created_at}</td>
                <td>
                    {isSelf ? (
                        <span className="badge gold">شما</span>
                    ) : (
                        <select value={levelOf(u)} onChange={e => setLevel(e.target.value)}
                            style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '4px 10px', fontFamily: 'inherit', fontSize: 13 }}>
                            <option value="regular">عادی</option>
                            <option value="vip">👑 ویژه</option>
                            <option value="admin">⚙️ ادمین</option>
                            <option value="vip_admin">👑⚙️ ویژه و ادمین</option>
                        </select>
                    )}
                </td>
                <td>
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                        <Link href={`/admin/users/${u.id}/trades`} className="btn-sm">ریز معاملات</Link>
                        <button onClick={() => setExpand(expand === 'inventory' ? null : 'inventory')} className="btn-sm">موجودی</button>
                        <button onClick={() => setExpand(expand === 'edit' ? null : 'edit')} className="btn-sm">ویرایش</button>
                        <button onClick={() => setExpand(expand === 'password' ? null : 'password')} className="btn-sm">تغییر رمز</button>
                        {!isSelf && <button onClick={destroyUser} className="btn-sm danger">حذف</button>}
                    </div>
                </td>
            </tr>

            {expand === 'inventory' && (
                <tr>
                    <td colSpan={10} style={{ background: 'rgba(255,255,255,.02)' }}>
                        <form onSubmit={submitInventory} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end', padding: '14px 6px' }}>
                            <div>
                                <label style={{ fontSize: 12, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>نوع</label>
                                <select value={inv.data.metal} onChange={e => inv.setData('metal', e.target.value)}
                                    style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '7px 10px', fontFamily: 'inherit', fontSize: 13 }}>
                                    <option value="gold">طلا</option>
                                    <option value="silver">نقره</option>
                                </select>
                            </div>
                            {inv.data.metal === 'silver' && (
                                <div>
                                    <label style={{ fontSize: 12, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>عیار</label>
                                    <select value={inv.data.purity} onChange={e => inv.setData('purity', e.target.value)}
                                        style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '7px 10px', fontFamily: 'inherit', fontSize: 13 }}>
                                        <option value="999">۹۹۹/۹</option>
                                        <option value="995">۹۹۵</option>
                                    </select>
                                </div>
                            )}
                            <div>
                                <label style={{ fontSize: 12, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>گرم (منفی = کاهش)</label>
                                <input type="number" step="any" value={inv.data.grams} onChange={e => inv.setData('grams', e.target.value)}
                                    placeholder="مثلاً 10 یا -5" required
                                    style={{ width: 140, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '7px 10px', fontFamily: 'inherit', fontSize: 13 }} />
                            </div>
                            <div style={{ flex: 1, minWidth: 160 }}>
                                <label style={{ fontSize: 12, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>شرح</label>
                                <input value={inv.data.description} onChange={e => inv.setData('description', e.target.value)}
                                    placeholder="توضیحات"
                                    style={{ width: '100%', background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '7px 10px', fontFamily: 'inherit', fontSize: 13 }} />
                            </div>
                            <button type="submit" className="btn-sm" style={{ background: 'linear-gradient(135deg,var(--gold-1),var(--gold-2))', color: '#1a1200', fontWeight: 700, border: 'none' }}>ثبت</button>
                        </form>
                    </td>
                </tr>
            )}

            {expand === 'edit' && (
                <tr>
                    <td colSpan={10} style={{ background: 'rgba(255,255,255,.02)' }}>
                        <form onSubmit={submitEdit} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end', padding: '14px 6px' }}>
                            {[['name', 'نام'], ['phone', 'موبایل'], ['email', 'ایمیل'], ['national_id', 'کد ملی']].map(([key, label]) => (
                                <div key={key}>
                                    <label style={{ fontSize: 12, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>{label}</label>
                                    <input value={edit.data[key]} onChange={e => edit.setData(key, e.target.value)}
                                        style={{ width: 160, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '7px 10px', fontFamily: 'inherit', fontSize: 13 }} />
                                </div>
                            ))}
                            <button type="submit" className="btn-sm" style={{ background: 'linear-gradient(135deg,var(--gold-1),var(--gold-2))', color: '#1a1200', fontWeight: 700, border: 'none' }}>ذخیره</button>
                        </form>
                    </td>
                </tr>
            )}

            {expand === 'password' && (
                <tr>
                    <td colSpan={10} style={{ background: 'rgba(255,255,255,.02)' }}>
                        <form onSubmit={submitPasswordReset} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end', padding: '14px 6px' }}>
                            <div>
                                <label htmlFor={`password-${u.id}`} style={{ fontSize: 12, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>رمز جدید</label>
                                <input id={`password-${u.id}`} type="password" minLength="6" autoComplete="new-password" required
                                    value={passwordReset.data.password} onChange={e => passwordReset.setData('password', e.target.value)}
                                    style={{ width: 180, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '7px 10px', fontFamily: 'inherit', fontSize: 13 }} />
                                {passwordReset.errors.password && <div style={{ color: 'var(--down)', fontSize: 12, marginTop: 4 }}>{passwordReset.errors.password}</div>}
                            </div>
                            <div>
                                <label htmlFor={`password-confirmation-${u.id}`} style={{ fontSize: 12, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>تکرار رمز جدید</label>
                                <input id={`password-confirmation-${u.id}`} type="password" minLength="6" autoComplete="new-password" required
                                    value={passwordReset.data.password_confirmation} onChange={e => passwordReset.setData('password_confirmation', e.target.value)}
                                    style={{ width: 180, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '7px 10px', fontFamily: 'inherit', fontSize: 13 }} />
                            </div>
                            <button type="submit" className="btn-sm" disabled={passwordReset.processing} style={{ background: 'linear-gradient(135deg,var(--gold-1),var(--gold-2))', color: '#1a1200', fontWeight: 700, border: 'none' }}>{passwordReset.processing ? 'در حال ثبت…' : 'ثبت رمز جدید'}</button>
                        </form>
                    </td>
                </tr>
            )}
        </>
    );
}

function TxnRow({ t, printOnly }) {
    const [editing, setEditing] = useState(false);
    const edit = useForm({ type: t.type, quantity: t.quantity, price_per_unit: t.price_per_unit });

    function submit(e) {
        e.preventDefault();
        router.put(`/admin/transactions/${t.id}`, edit.data, { preserveScroll: true, onSuccess: () => setEditing(false) });
    }

    function destroy() {
        if (!confirm('این معامله حذف شود؟')) return;
        router.delete(`/admin/transactions/${t.id}`, { preserveScroll: true });
    }

    if (editing) {
        return (
            <tr>
                <td colSpan={8}>
                    <form onSubmit={submit} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end', padding: '10px 0' }}>
                        <select value={edit.data.type} onChange={e => edit.setData('type', e.target.value)}
                            style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '7px 10px', fontFamily: 'inherit', fontSize: 13 }}>
                            <option value="buy">خرید</option>
                            <option value="sell">فروش</option>
                        </select>
                        <input type="number" step="any" value={edit.data.quantity} onChange={e => edit.setData('quantity', e.target.value)}
                            placeholder="مقدار" style={{ width: 110, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '7px 10px', fontFamily: 'inherit', fontSize: 13 }} />
                        <input type="number" value={edit.data.price_per_unit} onChange={e => edit.setData('price_per_unit', e.target.value)}
                            placeholder="قیمت واحد" style={{ width: 140, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '7px 10px', fontFamily: 'inherit', fontSize: 13 }} />
                        <button type="submit" className="btn-sm" style={{ background: 'linear-gradient(135deg,var(--gold-1),var(--gold-2))', color: '#1a1200', fontWeight: 700, border: 'none' }}>ذخیره</button>
                        <button type="button" onClick={() => setEditing(false)} className="btn-sm">لغو</button>
                    </form>
                </td>
            </tr>
        );
    }

    return (
        <tr>
            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{t.created_at}</td>
            <td><strong>{t.user_name}</strong></td>
            <td className="num" dir="ltr" style={{ fontSize: 13 }}>{t.user_phone}</td>
            <td><span className={`badge ${t.type === 'buy' ? 'buy-b' : 'sell-b'}`}>{t.type === 'buy' ? 'خرید' : 'فروش'}</span></td>
            <td>{t.item_label}</td>
            <td className="num">{t.quantity}</td>
            <td className="num"><strong>{faNum(t.total)}</strong></td>
            {!printOnly && (
                <td>
                    <div style={{ display: 'flex', gap: 6 }}>
                        <button onClick={() => setEditing(true)} className="btn-sm">ویرایش</button>
                        <button onClick={destroy} className="btn-sm danger">حذف</button>
                    </div>
                </td>
            )}
        </tr>
    );
}

function WTxnRow({ w }) {
    return (
        <tr>
            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{w.created_at}</td>
            <td>{w.user_name}</td>
            <td><span className={`badge ${w.amount > 0 ? 'buy-b' : 'sell-b'}`}>{w.amount > 0 ? 'واریز' : 'برداشت'}</span></td>
            <td className="num" style={{ color: w.amount > 0 ? 'var(--up)' : 'var(--down)', fontWeight: 700 }}>{w.amount > 0 ? '+' : ''}{faNum(w.amount)}</td>
            <td style={{ color: 'var(--muted)', fontSize: 13 }}>{w.description || '—'}</td>
        </tr>
    );
}

const DELIVERY_STATUS_LABEL = { pending: 'در انتظار', approved: 'تأییدشده', shipped: 'ارسال‌شده', rejected: 'رد‌شده', delivered: 'تحویل داده‌شده' };

const DELIVERY_METHOD_LABEL = { address: 'ارسال به آدرس', pickup: 'تحویل حضوری از فروشگاه' };

function DeliveryRow({ r, printOnly, deliveryNote, setDeliveryNote, updateDelivery }) {
    return (
        <tr>
            <td><strong>{r.user_name}</strong></td>
            <td className="num" dir="ltr" style={{ fontSize: 13 }}>{r.user_phone}</td>
            <td>{r.metal === 'gold' ? 'طلا' : `نقره ${r.purity}`}</td>
            <td className="num">{r.grams} گرم</td>
            <td>{r.recipient_name}<br /><span dir="ltr" style={{ fontSize: 12, color: 'var(--muted)' }}>{r.phone}</span></td>
            <td>{DELIVERY_METHOD_LABEL[r.delivery_method] || DELIVERY_METHOD_LABEL.address}</td>
            <td style={{ fontSize: 12, color: 'var(--muted)', maxWidth: 220 }}>
                {r.address}
                {r.postal_code && <><br />کد پستی: <span dir="ltr">{r.postal_code}</span></>}
            </td>
            <td>
                <span className={`badge ${r.status === 'pending' ? 'silver' : r.status === 'rejected' ? 'sell-b' : 'buy-b'}`}>
                    {DELIVERY_STATUS_LABEL[r.status]}
                </span>
            </td>
            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{r.created_at}</td>
            {!printOnly && (
                <td>
                    {r.status !== 'rejected' && r.status !== 'delivered' && (
                        <input placeholder="توضیح / دلیل (برای رد الزامی)" value={deliveryNote[r.id] || ''}
                            onChange={e => setDeliveryNote(s => ({ ...s, [r.id]: e.target.value }))}
                            style={{ width: '100%', minWidth: 160, marginBottom: 6, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '5px 8px', fontFamily: 'inherit', fontSize: 12 }} />
                    )}
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
            )}
        </tr>
    );
}

function WithdrawalRow({ w, printOnly, withdrawalNote, setWithdrawalNote, withdrawalReason, setWithdrawalReason, approveWithdrawal, rejectWithdrawal }) {
    return (
        <tr>
            <td><strong>{w.user_name}</strong></td>
            <td className="num" dir="ltr" style={{ fontSize: 13 }}>{w.user_phone}</td>
            <td className="num" style={{ color: 'var(--gold-1)', fontWeight: 700 }}>{faNum(w.amount)}</td>
            <td className="num" dir="ltr" style={{ fontSize: 13 }}>{w.card_number}</td>
            <td className="num" dir="ltr" style={{ fontSize: 13 }}>{w.shaba}</td>
            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{w.created_at}</td>
            {!printOnly && (
                <td>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6, minWidth: 200 }}>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                            <input placeholder="توضیح تأیید (اختیاری)" value={withdrawalNote[w.id] || ''}
                                onChange={e => setWithdrawalNote(s => ({ ...s, [w.id]: e.target.value }))}
                                style={{ flex: 1, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '5px 8px', fontFamily: 'inherit', fontSize: 12 }} />
                            <button onClick={() => approveWithdrawal(w.id)} className="btn-sm" style={{ borderColor: 'rgba(65,225,166,.4)', color: 'var(--up)', background: 'rgba(65,225,166,.08)' }}>تأیید</button>
                        </div>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                            <input placeholder="دلیل رد (الزامی)" value={withdrawalReason[w.id] || ''}
                                onChange={e => setWithdrawalReason(s => ({ ...s, [w.id]: e.target.value }))}
                                style={{ flex: 1, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '5px 8px', fontFamily: 'inherit', fontSize: 12 }} />
                            <button onClick={() => rejectWithdrawal(w.id)} className="btn-sm danger">رد</button>
                        </div>
                    </div>
                </td>
            )}
        </tr>
    );
}

function DepositRow({ d, printOnly, depositNote, setDepositNote, depositReason, setDepositReason, approveDeposit, rejectDeposit }) {
    return (
        <tr>
            <td><strong>{d.user_name}</strong></td>
            <td className="num" dir="ltr" style={{ fontSize: 13 }}>{d.user_phone}</td>
            <td className="num" style={{ color: 'var(--gold-1)', fontWeight: 700 }}>{faNum(d.amount)}</td>
            <td style={{ color: 'var(--muted)', fontSize: 13 }}>{d.note || '—'}</td>
            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{d.created_at}</td>
            {!printOnly && (
                <td>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6, minWidth: 200 }}>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                            <input placeholder="توضیح تأیید (اختیاری)" value={depositNote[d.id] || ''}
                                onChange={e => setDepositNote(s => ({ ...s, [d.id]: e.target.value }))}
                                style={{ flex: 1, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '5px 8px', fontFamily: 'inherit', fontSize: 12 }} />
                            <button onClick={() => approveDeposit(d.id)} className="btn-sm" style={{ borderColor: 'rgba(65,225,166,.4)', color: 'var(--up)', background: 'rgba(65,225,166,.08)' }}>تأیید</button>
                        </div>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                            <input placeholder="دلیل رد (الزامی)" value={depositReason[d.id] || ''}
                                onChange={e => setDepositReason(s => ({ ...s, [d.id]: e.target.value }))}
                                style={{ flex: 1, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '5px 8px', fontFamily: 'inherit', fontSize: 12 }} />
                            <button onClick={() => rejectDeposit(d.id)} className="btn-sm danger">رد</button>
                        </div>
                    </div>
                </td>
            )}
        </tr>
    );
}

function AllTradeRow({ t, printOnly, rejectingId, setRejectingId, rejectReason, setRejectReason, submitTradeReject }) {
    const rejected = t.status === 'rejected';
    return (
        <tr style={rejected ? { opacity: .6 } : undefined}>
            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{t.created_at}</td>
            <td><span className={`badge ${t.source === 'shop' ? 'gold' : 'silver'}`}>{t.source_label}</span></td>
            <td><strong>{t.user_name}</strong></td>
            <td style={{ color: 'var(--muted)', fontSize: 13 }}>{t.counterparty_name || '—'}</td>
            <td><span className={`badge ${t.side === 'buy' ? 'buy-b' : 'sell-b'}`}>{t.side === 'buy' ? 'خرید' : 'فروش'}</span></td>
            <td>
                <span style={rejected ? { textDecoration: 'line-through' } : undefined}>{t.item_label}</span>
                {rejected && <span className="badge sell-b" style={{ marginInlineStart: 6 }}>رد شد</span>}
                {rejected && t.admin_note && <div style={{ fontSize: 11, color: 'var(--down)', marginTop: 4 }}>دلیل: {t.admin_note}</div>}
            </td>
            <td className="num">{t.quantity}</td>
            <td className="num"><strong>{faNum(t.total)}</strong></td>
            {!printOnly && (
                <td>
                    {t.can_reject ? (
                        rejectingId === t.id ? (
                            <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                                <input autoFocus placeholder="دلیل رد" value={rejectReason} onChange={e => setRejectReason(e.target.value)}
                                    style={{ width: 150, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '5px 8px', fontFamily: 'inherit', fontSize: 12 }} />
                                <button className="btn-sm danger" onClick={() => submitTradeReject(t)}>ثبت رد</button>
                                <button className="btn-sm" onClick={() => { setRejectingId(null); setRejectReason(''); }}>لغو</button>
                            </div>
                        ) : (
                            <button className="btn-sm danger" onClick={() => { setRejectingId(t.id); setRejectReason(''); }}>رد با دلیل</button>
                        )
                    ) : <span style={{ color: 'var(--muted)', fontSize: 12 }}>—</span>}
                </td>
            )}
        </tr>
    );
}

function LogRow({ l }) {
    const cat = LOG_CAT[l.category] || LOG_CAT.other;
    return (
        <tr>
            <td style={{ fontSize: 12, color: 'var(--muted)', whiteSpace: 'nowrap' }}>{l.created_at}</td>
            <td><span className={`badge ${cat.badge}`}>{cat.label}</span></td>
            <td style={{ fontSize: 13 }}>{l.user_name || '—'}</td>
            <td style={{ fontSize: 13, whiteSpace: 'normal', minWidth: 280 }}>{l.description}</td>
            <td className="num" dir="ltr" style={{ fontSize: 12, color: 'var(--muted)' }}>{l.ip || '—'}</td>
        </tr>
    );
}

function SecurityEventRow({ e }) {
    const matches = (e.matched_fields || []).map(m => `${m.field}: ${m.sample}`).join(' | ');
    const payload = e.payload ? JSON.stringify(e.payload) : '—';

    return (
        <tr>
            <td style={{ fontSize: 12, color: 'var(--muted)', whiteSpace: 'nowrap' }}>{e.created_at}</td>
            <td><span className="badge sell-b">{e.event_type}</span></td>
            <td style={{ fontSize: 13 }}>
                {e.user_name ? (
                    <>{e.user_name}<br /><span dir="ltr" style={{ color: 'var(--muted)', fontSize: 12 }}>{e.user_phone}</span></>
                ) : 'مهمان'}
            </td>
            <td className="num" dir="ltr" style={{ fontSize: 12, color: 'var(--muted)' }}>{e.ip || '—'}</td>
            <td style={{ fontSize: 12 }}>
                <strong>{e.method}</strong> {e.path}
                {e.route_name && <div style={{ color: 'var(--muted)' }}>{e.route_name}</div>}
            </td>
            <td style={{ fontSize: 12, whiteSpace: 'normal', minWidth: 240 }}>{matches || '—'}</td>
            <td dir="ltr" style={{ fontSize: 11, color: 'var(--muted)', whiteSpace: 'normal', maxWidth: 320, wordBreak: 'break-word' }}>
                {payload}
                {e.user_agent && <div style={{ marginTop: 6 }}>{e.user_agent}</div>}
            </td>
        </tr>
    );
}

export default function Dashboard({ users, txns, wTxns, notifs, stats, memberApplications, vipMembers, deliveryRequests, withdrawalRequests, depositRequests, inventoryIncreaseRequests, allTrades, activityLogs, securityEvents, tickets, settings }) {
    const { auth } = usePage().props;
    const [tab, setTab] = useState('users');

    const wallet = useForm({ user_id: '', amount: '', description: '' });
    const notify = useForm({ title: '', body: '', type: 'info', target: 'all' });
    const [memberMsg, setMemberMsg] = useState({});
    const [withdrawalReason, setWithdrawalReason] = useState({});
    const [withdrawalNote, setWithdrawalNote] = useState({});
    const [depositReason, setDepositReason] = useState({});
    const [depositNote, setDepositNote] = useState({});
    const [deliveryNote, setDeliveryNote] = useState({});
    const [inventoryIncreaseNote, setInventoryIncreaseNote] = useState({});
    const [tradeFrom, setTradeFrom] = useState('');
    const [tradeTo, setTradeTo] = useState('');
    const [txnsFrom, setTxnsFrom] = useState('');
    const [txnsTo, setTxnsTo] = useState('');
    const [walletFrom, setWalletFrom] = useState('');
    const [walletTo, setWalletTo] = useState('');
    const [deliveryFrom, setDeliveryFrom] = useState('');
    const [deliveryTo, setDeliveryTo] = useState('');
    const [withdrawalsFrom, setWithdrawalsFrom] = useState('');
    const [withdrawalsTo, setWithdrawalsTo] = useState('');
    const [depositsFrom, setDepositsFrom] = useState('');
    const [depositsTo, setDepositsTo] = useState('');
    const [rejectingId, setRejectingId] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const [logCat, setLogCat] = useState('all');
    const [logFrom, setLogFrom] = useState('');
    const [logTo, setLogTo] = useState('');
    const [securityFrom, setSecurityFrom] = useState('');
    const [securityTo, setSecurityTo] = useState('');
    const [editingNotifId, setEditingNotifId] = useState(null);
    const [notifEdit, setNotifEdit] = useState({ title: '', body: '', type: 'info' });
    const settingsForm = useForm({
        trade_room_commission_percent: settings?.trade_room_commission_percent ?? 0.1,
        about_title: settings?.about_title ?? '',
        about_body: settings?.about_body ?? '',
        contact_title: settings?.contact_title ?? '',
        contact_intro: settings?.contact_intro ?? '',
    });
    const userOptions = useMemo(() => users.map(u => ({
        value: String(u.id),
        label: u.name,
        description: u.phone,
        descriptionDir: 'ltr',
        search: `${u.name} ${u.phone} ${u.email || ''} ${u.national_id || ''}`,
    })), [users]);
    const notificationTargetOptions = useMemo(() => [
        { value: 'all', label: 'همه کاربران', search: 'همه کاربران all' },
        ...userOptions,
    ], [userOptions]);

    const [usersQ, setUsersQ] = useState('');
    const [txnsQ, setTxnsQ] = useState('');
    const [tradesQ, setTradesQ] = useState('');
    const [walletQ, setWalletQ] = useState('');
    const [notifsQ, setNotifsQ] = useState('');
    const [memberQ, setMemberQ] = useState('');
    const [vipQ, setVipQ] = useState('');
    const [deliveryQ, setDeliveryQ] = useState('');
    const [withdrawalsQ, setWithdrawalsQ] = useState('');
    const [depositsQ, setDepositsQ] = useState('');
    const [logsQ, setLogsQ] = useState('');
    const [securityQ, setSecurityQ] = useState('');
    const [ticketsQ, setTicketsQ] = useState('');

    const filteredUsers = useMemo(() => filterBySearch(users, usersQ, ['name', 'phone', 'email', 'national_id']), [users, usersQ]);
    const filteredTxns = useMemo(
        () => filterBySearch(filterByDateRange(txns, txnsFrom, txnsTo), txnsQ, ['user_name', 'user_phone', 'item_label']),
        [txns, txnsFrom, txnsTo, txnsQ]
    );
    const filteredWTxns = useMemo(
        () => filterBySearch(filterByDateRange(wTxns, walletFrom, walletTo), walletQ, ['user_name', 'description']),
        [wTxns, walletFrom, walletTo, walletQ]
    );
    const filteredNotifs = useMemo(() => filterBySearch(notifs, notifsQ, ['title', 'body']), [notifs, notifsQ]);
    const filteredMemberApps = useMemo(() => filterBySearch(memberApplications || [], memberQ, ['name', 'phone', 'national_id']), [memberApplications, memberQ]);
    const filteredVipMembers = useMemo(() => filterBySearch(vipMembers || [], vipQ, ['name', 'phone', 'national_id', 'email']), [vipMembers, vipQ]);
    const filteredDeliveryRequests = useMemo(
        () => filterBySearch(filterByDateRange(deliveryRequests || [], deliveryFrom, deliveryTo), deliveryQ, ['user_name', 'user_phone', 'recipient_name', 'phone', 'address', 'postal_code', 'delivery_method']),
        [deliveryRequests, deliveryFrom, deliveryTo, deliveryQ]
    );
    const filteredWithdrawalRequests = useMemo(
        () => filterBySearch(filterByDateRange(withdrawalRequests || [], withdrawalsFrom, withdrawalsTo), withdrawalsQ, ['user_name', 'user_phone', 'card_number', 'shaba']),
        [withdrawalRequests, withdrawalsFrom, withdrawalsTo, withdrawalsQ]
    );
    const filteredDepositRequests = useMemo(
        () => filterBySearch(filterByDateRange(depositRequests || [], depositsFrom, depositsTo), depositsQ, ['user_name', 'user_phone', 'note']),
        [depositRequests, depositsFrom, depositsTo, depositsQ]
    );

    const filteredLogs = useMemo(() => filterBySearch(filterByDateRange(
        (activityLogs || []).filter(l => logCat === 'all' || l.category === logCat),
        logFrom, logTo
    ), logsQ, ['description', 'user_name', 'action']), [activityLogs, logCat, logFrom, logTo, logsQ]);

    const filteredSecurityEvents = useMemo(
        () => filterBySearch(filterByDateRange(securityEvents || [], securityFrom, securityTo), securityQ, ['event_type', 'user_name', 'user_phone', 'ip', 'path', 'route_name', 'method', 'user_agent']),
        [securityEvents, securityFrom, securityTo, securityQ]
    );

    const filteredTickets = useMemo(() => filterBySearch(tickets || [], ticketsQ, ['subject', 'user_name', 'user_phone']), [tickets, ticketsQ]);

    const usersPager = usePager(filteredUsers, usersQ);
    const txnsPager = usePager(filteredTxns, `${txnsFrom}|${txnsTo}|${txnsQ}`);
    const wTxnsPager = usePager(filteredWTxns, `${walletFrom}|${walletTo}|${walletQ}`);
    const notifsPager = usePager(filteredNotifs, notifsQ);
    const memberAppsPager = usePager(filteredMemberApps, memberQ);
    const vipPager = usePager(filteredVipMembers, vipQ);
    const deliveryPager = usePager(filteredDeliveryRequests, `${deliveryFrom}|${deliveryTo}|${deliveryQ}`);
    const withdrawalsPager = usePager(filteredWithdrawalRequests, `${withdrawalsFrom}|${withdrawalsTo}|${withdrawalsQ}`);
    const depositsPager = usePager(filteredDepositRequests, `${depositsFrom}|${depositsTo}|${depositsQ}`);
    const logsPager = usePager(filteredLogs, `${logCat}|${logFrom}|${logTo}|${logsQ}`);
    const securityPager = usePager(filteredSecurityEvents, `${securityFrom}|${securityTo}|${securityQ}`);
    const ticketsPager = usePager(filteredTickets, ticketsQ);

    function submitTradeReject(t) {
        const reason = rejectReason.trim();
        if (!reason) { alert('دلیل رد را وارد کنید.'); return; }
        const url = t.source === 'shop'
            ? `/admin/transactions/${t.ref_id}/reject`
            : `/admin/trade-room/${t.ref_id}/reject`;
        router.post(url, { reason }, {
            preserveScroll: true,
            onSuccess: () => { setRejectingId(null); setRejectReason(''); },
        });
    }

    const filteredAllTrades = useMemo(
        () => filterBySearch(filterByDateRange(allTrades || [], tradeFrom, tradeTo), tradesQ, ['user_name', 'counterparty_name', 'item_label']),
        [allTrades, tradeFrom, tradeTo, tradesQ]
    );
    const allTradesPager = usePager(filteredAllTrades, `${tradeFrom}|${tradeTo}|${tradesQ}`);

    function deleteNotif(id) {
        if (!confirm('حذف شود؟')) return;
        router.delete(`/admin/notify/${id}`, { preserveScroll: true });
    }

    function startEditNotif(n) {
        setEditingNotifId(n.id);
        setNotifEdit({ title: n.title, body: n.body || '', type: n.type });
    }

    function saveNotifEdit(id) {
        if (!notifEdit.title.trim()) { alert('عنوان را وارد کنید.'); return; }
        router.post(`/admin/notify/${id}/update`, notifEdit, {
            preserveScroll: true,
            onSuccess: () => setEditingNotifId(null),
        });
    }

    function approveMembership(uid) {
        router.post(`/admin/membership/approve/${uid}`, { message: memberMsg[uid] || '' }, { preserveScroll: true });
    }

    function rejectMembership(uid) {
        if (!confirm('درخواست رد شود؟')) return;
        router.post(`/admin/membership/reject/${uid}`, { message: memberMsg[uid] || '' }, { preserveScroll: true });
    }

    function makeRegular(uid, name) {
        if (!confirm(`عضویت ویژه «${name}» به عادی تبدیل شود؟`)) return;
        router.post(`/admin/set-level/${uid}`, { level: 'regular' }, { preserveScroll: true });
    }

    function updateDelivery(id, status) {
        const note = (deliveryNote[id] || '').trim();
        if (status === 'rejected') {
            if (!note) { alert('برای رد، دلیل را وارد کنید.'); return; }
            if (!confirm('رد شود؟ طلا/نقره به موجودی کاربر برمی‌گردد.')) return;
        }
        router.post(`/admin/delivery/${id}/update`, { status, note }, { preserveScroll: true });
    }

    function approveWithdrawal(id) {
        if (!confirm('تسویه حساب تأیید شود؟')) return;
        router.post(`/admin/withdrawals/${id}/approve`, { note: (withdrawalNote[id] || '').trim() }, { preserveScroll: true });
    }

    function rejectWithdrawal(id) {
        const reason = withdrawalReason[id];
        if (!reason || !reason.trim()) { alert('دلیل رد را وارد کنید.'); return; }
        router.post(`/admin/withdrawals/${id}/reject`, { reason }, { preserveScroll: true });
    }

    function approveDeposit(id) {
        if (!confirm('افزایش موجودی تأیید شود؟ مبلغ به کیف پول کاربر اضافه می‌شود.')) return;
        router.post(`/admin/deposits/${id}/approve`, { note: (depositNote[id] || '').trim() }, { preserveScroll: true });
    }

    function rejectDeposit(id) {
        const reason = depositReason[id];
        if (!reason || !reason.trim()) { alert('دلیل رد را وارد کنید.'); return; }
        router.post(`/admin/deposits/${id}/reject`, { reason }, { preserveScroll: true });
    }

    function approveInventoryIncrease(id) {
        if (!confirm('افزایش موجودی تأیید شود؟ مقدار درخواست‌شده به دفترکل کاربر اضافه می‌شود.')) return;
        router.post(`/admin/inventory-increase-requests/${id}/approve`, { note: (inventoryIncreaseNote[id] || '').trim() }, { preserveScroll: true });
    }

    function rejectInventoryIncrease(id) {
        const reason = (inventoryIncreaseNote[id] || '').trim();
        if (!reason) { alert('برای رد درخواست، دلیل را وارد کنید.'); return; }
        router.post(`/admin/inventory-increase-requests/${id}/reject`, { reason }, { preserveScroll: true });
    }

    const TABS = [
        ['users', 'کاربران'],
        ['txns', 'معاملات'],
        ['all_trades', 'تاریخچه کلی معاملات'],
        ['wallet', 'کیف پول'],
        ['notifs', 'اعلان‌ها'],
        ['membership', `درخواست‌های عضویت${memberApplications?.length ? ` (${memberApplications.length})` : ''}`],
        ['vip', `عضویت‌های ویژه${vipMembers?.length ? ` (${vipMembers.length})` : ''}`],
        ['delivery', `تحویل فیزیکی${deliveryRequests?.length ? ` (${deliveryRequests.length})` : ''}`],
        ['withdrawals', `تسویه حساب${withdrawalRequests?.length ? ` (${withdrawalRequests.length})` : ''}`],
        ['deposits', `افزایش موجودی${depositRequests?.length ? ` (${depositRequests.length})` : ''}`],
        ['inventory_increases', `افزایش موجودی انبار${inventoryIncreaseRequests?.length ? ` (${inventoryIncreaseRequests.length})` : ''}`],
        ['tickets', `تیکت‌ها${tickets?.filter(t => t.status === 'open').length ? ` (${tickets.filter(t => t.status === 'open').length})` : ''}`],
        ['security', `امنیت${securityEvents?.length ? ` (${securityEvents.length})` : ''}`],
        ['logs', 'گزارش فعالیت'],
        ['settings', '⚙️ تنظیمات'],
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
                <div className="tabs no-print">
                    {TABS.map(([key, label]) => (
                        <button key={key} className={`tab-btn${tab === key ? ' active' : ''}`} onClick={() => setTab(key)}>{label}</button>
                    ))}
                </div>

                {/* کاربران */}
                {tab === 'users' && (
                    <>
                        <div className="no-print" style={{ marginBottom: 14 }}>
                            <SearchBox value={usersQ} onChange={setUsersQ} placeholder="🔍 جستجو در نام، موبایل، ایمیل، کد ملی..." />
                        </div>
                        <div className="table-wrap">
                            <table>
                                <thead><tr>
                                    <th>#</th><th>نام</th><th>موبایل</th><th>معاملات</th><th>کیف پول</th><th>طلا</th><th>نقره (۹۹۹/۹۹۵)</th><th>عضویت از</th><th>سطح</th><th></th>
                                </tr></thead>
                                <tbody>
                                    {usersPager.pageItems.map(u => <UserRow key={u.id} u={u} isSelf={u.id === auth.user.id} />)}
                                </tbody>
                            </table>
                        </div>
                        <Pager page={usersPager.page} totalPages={usersPager.totalPages} onChange={usersPager.setPage} />
                    </>
                )}

                {/* معاملات */}
                {tab === 'txns' && (
                    <>
                        <div className="no-print" style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap', marginBottom: 14 }}>
                            <SearchBox value={txnsQ} onChange={setTxnsQ} placeholder="🔍 جستجو در کاربر، موبایل، کالا..." />
                            <DateRangeFilter from={txnsFrom} to={txnsTo} onFromChange={setTxnsFrom} onToChange={setTxnsTo} />
                            {(txnsFrom || txnsTo) && <button type="button" className="btn-sm" onClick={() => { setTxnsFrom(''); setTxnsTo(''); }}>حذف فیلتر تاریخ</button>}
                            <button type="button" className="btn-sm gold" onClick={() => window.print()}>🖨️ چاپ / خروجی PDF</button>
                        </div>
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>تاریخ</th><th>کاربر</th><th>موبایل</th><th>نوع</th><th>کالا</th><th>مقدار</th><th>مبلغ کل</th><th></th></tr></thead>
                                <tbody>
                                    {txnsPager.pageItems.map(t => <TxnRow key={t.id} t={t} />)}
                                </tbody>
                            </table>
                        </div>
                        <Pager page={txnsPager.page} totalPages={txnsPager.totalPages} onChange={txnsPager.setPage} />

                        <div className="table-wrap print-area print-only-block">
                            <div className="print-only" style={{ marginBottom: 14, fontWeight: 800, fontSize: 16 }}>معاملات فروشگاه</div>
                            <table>
                                <thead><tr><th>تاریخ</th><th>کاربر</th><th>موبایل</th><th>نوع</th><th>کالا</th><th>مقدار</th><th>مبلغ کل</th></tr></thead>
                                <tbody>
                                    {filteredTxns.map(t => <TxnRow key={t.id} t={t} printOnly />)}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {/* تاریخچه کلی معاملات (فروشگاه + اتاق معاملاتی) */}
                {tab === 'all_trades' && (
                    <>
                        <div className="no-print" style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap', marginBottom: 18 }}>
                            <SearchBox value={tradesQ} onChange={setTradesQ} placeholder="🔍 جستجو در کاربر، طرف معامله، کالا..." />
                            <DateRangeFilter from={tradeFrom} to={tradeTo} onFromChange={setTradeFrom} onToChange={setTradeTo} />
                            {(tradeFrom || tradeTo) && <button type="button" className="btn-sm" onClick={() => { setTradeFrom(''); setTradeTo(''); }}>حذف فیلتر</button>}
                            <button type="button" className="btn-sm" onClick={() => window.print()} style={{ borderColor: 'rgba(246,207,99,.4)', color: 'var(--gold-1)', background: 'rgba(246,207,99,.08)' }}>
                                🖨️ چاپ / خروجی PDF
                            </button>
                        </div>

                        {filteredAllTrades.length ? (
                            <>
                                <div className="table-wrap">
                                    <table>
                                        <thead><tr><th>تاریخ</th><th>منبع</th><th>کاربر</th><th>طرف معامله</th><th>نوع</th><th>کالا</th><th>مقدار</th><th>مبلغ کل</th><th></th></tr></thead>
                                        <tbody>
                                            {allTradesPager.pageItems.map(t => <AllTradeRow key={t.id} t={t} rejectingId={rejectingId} setRejectingId={setRejectingId} rejectReason={rejectReason} setRejectReason={setRejectReason} submitTradeReject={submitTradeReject} />)}
                                        </tbody>
                                    </table>
                                </div>
                                <Pager page={allTradesPager.page} totalPages={allTradesPager.totalPages} onChange={allTradesPager.setPage} />

                                <div className="table-wrap print-area print-only-block">
                                    <div className="print-only" style={{ marginBottom: 14, fontWeight: 800, fontSize: 16 }}>تاریخچه کلی معاملات (فروشگاه + اتاق معاملاتی)</div>
                                    <table>
                                        <thead><tr><th>تاریخ</th><th>منبع</th><th>کاربر</th><th>طرف معامله</th><th>نوع</th><th>کالا</th><th>مقدار</th><th>مبلغ کل</th></tr></thead>
                                        <tbody>
                                            {filteredAllTrades.map(t => <AllTradeRow key={t.id} t={t} printOnly />)}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        ) : (
                            <div className="empty"><div className="ico">📜</div>{(tradeFrom || tradeTo) ? 'معامله‌ای در این بازه ثبت نشده.' : 'هنوز معامله‌ای ثبت نشده.'}</div>
                        )}
                    </>
                )}

                {/* کیف پول */}
                {tab === 'wallet' && (
                    <>
                        <div className="fcard" style={{ maxWidth: 520, marginBottom: 20 }}>
                            <h2 style={{ fontSize: 16 }}>شارژ / برداشت کیف پول</h2>
                            <div style={{ height: 16 }} />
                            <div className="alert info" style={{ fontSize: 13 }}>واریز دستی فعلاً توسط ادمین ثبت می‌شود؛ بعداً به درگاه پرداخت آنلاین وصل خواهد شد.</div>
                            <form onSubmit={e => { e.preventDefault(); wallet.post('/admin/wallet-credit', { preserveScroll: true, onSuccess: () => wallet.reset() }); }}>
                                <div className="field"><label>کاربر</label>
                                    <SearchableSelect
                                        value={wallet.data.user_id}
                                        onChange={value => wallet.setData('user_id', value)}
                                        options={userOptions}
                                        placeholder="— انتخاب کاربر —"
                                        searchPlaceholder="جستجو با نام، موبایل، ایمیل یا کد ملی..."
                                        required
                                    /></div>
                                <div className="field"><label>مبلغ (منفی = برداشت)</label>
                                    <input type="number" value={wallet.data.amount} onChange={e => wallet.setData('amount', e.target.value)} placeholder="500000" required /></div>
                                <div className="field"><label>شرح</label>
                                    <input value={wallet.data.description} onChange={e => wallet.setData('description', e.target.value)} placeholder="شارژ دستی" /></div>
                                <button className="btn" type="submit" style={{ width: 'auto', padding: '11px 28px' }}>ثبت تراکنش</button>
                            </form>
                        </div>
                        <div className="no-print" style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap', marginBottom: 14 }}>
                            <SearchBox value={walletQ} onChange={setWalletQ} placeholder="🔍 جستجو در کاربر، شرح..." />
                            <DateRangeFilter from={walletFrom} to={walletTo} onFromChange={setWalletFrom} onToChange={setWalletTo} />
                            {(walletFrom || walletTo) && <button type="button" className="btn-sm" onClick={() => { setWalletFrom(''); setWalletTo(''); }}>حذف فیلتر تاریخ</button>}
                            <button type="button" className="btn-sm gold" onClick={() => window.print()}>🖨️ چاپ / خروجی PDF</button>
                        </div>
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>تاریخ</th><th>کاربر</th><th>نوع</th><th>مبلغ</th><th>شرح</th></tr></thead>
                                <tbody>
                                    {wTxnsPager.pageItems.map(w => <WTxnRow key={w.id} w={w} />)}
                                </tbody>
                            </table>
                        </div>
                        <Pager page={wTxnsPager.page} totalPages={wTxnsPager.totalPages} onChange={wTxnsPager.setPage} />

                        <div className="table-wrap print-area print-only-block">
                            <div className="print-only" style={{ marginBottom: 14, fontWeight: 800, fontSize: 16 }}>تراکنش‌های کیف پول</div>
                            <table>
                                <thead><tr><th>تاریخ</th><th>کاربر</th><th>نوع</th><th>مبلغ</th><th>شرح</th></tr></thead>
                                <tbody>
                                    {filteredWTxns.map(w => <WTxnRow key={w.id} w={w} />)}
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
                                        <SearchableSelect
                                            value={notify.data.target}
                                            onChange={value => notify.setData('target', value)}
                                            options={notificationTargetOptions}
                                            placeholder="— انتخاب گیرنده —"
                                            searchPlaceholder="جستجو در کاربران..."
                                        /></div>
                                </div>
                                <button className="btn" type="submit" style={{ width: 'auto', padding: '11px 28px' }}>ارسال</button>
                            </form>
                        </div>
                        <div className="no-print" style={{ marginBottom: 14 }}>
                            <SearchBox value={notifsQ} onChange={setNotifsQ} placeholder="🔍 جستجو در عنوان، متن..." />
                        </div>
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>تاریخ</th><th>عنوان</th><th>نوع</th><th>گیرنده</th><th>دیده‌شده</th><th></th></tr></thead>
                                <tbody>
                                    {notifsPager.pageItems.map(n => (
                                        editingNotifId === n.id ? (
                                            <tr key={n.id}>
                                                <td colSpan={5}>
                                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 140px', gap: 10, padding: '8px 0', alignItems: 'center' }}>
                                                        <input value={notifEdit.title} onChange={e => setNotifEdit(s => ({ ...s, title: e.target.value }))} placeholder="عنوان" />
                                                        <input value={notifEdit.body} onChange={e => setNotifEdit(s => ({ ...s, body: e.target.value }))} placeholder="متن" />
                                                        <select value={notifEdit.type} onChange={e => setNotifEdit(s => ({ ...s, type: e.target.value }))} style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 12, padding: '11px 14px', fontFamily: 'inherit', fontSize: 14, width: '100%' }}>
                                                            <option value="info">🔔 اطلاعیه</option>
                                                            <option value="trade">📊 معامله</option>
                                                            <option value="wallet">💰 کیف پول</option>
                                                            <option value="promo">🎁 تبلیغات</option>
                                                            <option value="system">⚙️ سیستمی</option>
                                                        </select>
                                                    </div>
                                                    <div style={{ display: 'flex', gap: 8, paddingBottom: 8 }}>
                                                        <button onClick={() => saveNotifEdit(n.id)} className="btn-sm ok">ذخیره</button>
                                                        <button onClick={() => setEditingNotifId(null)} className="btn-sm">انصراف</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ) : (
                                            <tr key={n.id}>
                                                <td style={{ fontSize: 12, color: 'var(--muted)' }}>{n.created_at}</td>
                                                <td><strong>{n.title}</strong>{n.body && <div style={{ fontSize: 12, color: 'var(--muted)' }}>{n.body}</div>}</td>
                                                <td>{TYPE_ICON[n.type] || '🔔'}</td>
                                                <td style={{ fontSize: 13, color: 'var(--muted)' }}>{n.user_id ? `کاربر #${n.user_id}` : <span className="badge silver">همه</span>}</td>
                                                <td style={{ fontSize: 13 }}>
                                                    {n.read_count > 0
                                                        ? <span className="badge buy-b">{faNum(n.read_count)}/{faNum(n.target_count)}</span>
                                                        : <span className="badge sell-b">دیده نشده</span>}
                                                </td>
                                                <td style={{ display: 'flex', gap: 6 }}>
                                                    <button onClick={() => startEditNotif(n)} className="btn-sm">ویرایش</button>
                                                    <button onClick={() => deleteNotif(n.id)} className="btn-sm danger">حذف</button>
                                                </td>
                                            </tr>
                                        )
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pager page={notifsPager.page} totalPages={notifsPager.totalPages} onChange={notifsPager.setPage} />
                    </>
                )}

                {/* درخواست‌های عضویت ویژه */}
                {tab === 'membership' && (
                    <>
                        <section className="fcard no-print" style={{ padding: 20, marginBottom: 18 }}>
                            <h3 style={{ margin: '0 0 6px', fontSize: 17 }}>فعال‌سازی مستقیم عضویت ویژه</h3>
                            <p style={{ margin: '0 0 16px', color: 'var(--muted)', fontSize: 13, lineHeight: 1.8 }}>
                                انتخاب کاربر کافی است؛ اطلاعات هویتی و مدارک در این فرم اختیاری‌اند و در صورت ثبت، همراه عضویت ویژه ذخیره می‌شوند.
                            </p>
                            <AdminMembershipForm userOptions={userOptions} />
                        </section>
                        <div className="no-print" style={{ marginBottom: 14 }}>
                            <SearchBox value={memberQ} onChange={setMemberQ} placeholder="🔍 جستجو در نام، موبایل، کد ملی..." />
                        </div>
                        {filteredMemberApps.length ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            {memberAppsPager.pageItems.map(m => (
                                <div key={m.id} className="fcard" style={{ padding: 20 }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 10, marginBottom: 14 }}>
                                        <div>
                                            <strong style={{ fontSize: 16 }}>{m.name}</strong>
                                            <span style={{ color: 'var(--muted)', fontSize: 13, marginInlineStart: 10 }} dir="ltr">{m.phone}</span>
                                            {m.national_id && <span style={{ color: 'var(--muted)', fontSize: 13, marginInlineStart: 10 }}>کد ملی: {m.national_id}</span>}
                                        </div>
                                        <span style={{ fontSize: 12, color: 'var(--muted)' }}>ارسال: {m.submitted_at}</span>
                                    </div>

                                    <div style={{ display: 'flex', gap: 18, flexWrap: 'wrap', marginBottom: 14, fontSize: 13, color: 'var(--muted)' }}>
                                        {m.birth_date && <span>تاریخ تولد: <strong style={{ color: 'var(--txt)' }}>{m.birth_date}</strong></span>}
                                        {m.residence_address && <span>آدرس: <strong style={{ color: 'var(--txt)' }}>{m.residence_address}</strong></span>}
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
                                            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>جواز صنفی</div>
                                            {m.identity_doc
                                                ? <a href={m.identity_doc} target="_blank" rel="noopener noreferrer">
                                                    <img src={m.identity_doc} alt="جواز صنفی" style={{ width: '100%', borderRadius: 10, border: '1px solid var(--line)' }} />
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

                                    <div className="field" style={{ marginBottom: 12 }}>
                                        <label>پیام برای کاربر (اختیاری — همراه تأیید یا رد ارسال می‌شود)</label>
                                        <input value={memberMsg[m.id] || ''}
                                            onChange={e => setMemberMsg(s => ({ ...s, [m.id]: e.target.value }))}
                                            placeholder="مثلاً: مدارک ناخوانا بود، لطفاً دوباره ارسال کنید" />
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
                        )}
                        <Pager page={memberAppsPager.page} totalPages={memberAppsPager.totalPages} onChange={memberAppsPager.setPage} />
                    </>
                )}

                {/* عضویت‌های ویژه */}
                {tab === 'vip' && (
                    <>
                        <div className="no-print" style={{ marginBottom: 14 }}>
                            <SearchBox value={vipQ} onChange={setVipQ} placeholder="🔍 جستجو در نام، موبایل، کد ملی، ایمیل..." />
                        </div>
                        {filteredVipMembers.length ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            {vipPager.pageItems.map(m => (
                                <div key={m.id} className="fcard" style={{ padding: 20 }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 10, marginBottom: 14 }}>
                                        <div>
                                            <strong style={{ fontSize: 16 }}>{m.name}</strong>
                                            <span style={{ color: 'var(--muted)', fontSize: 13, marginInlineStart: 10 }} dir="ltr">{m.phone}</span>
                                            {m.national_id && <span style={{ color: 'var(--muted)', fontSize: 13, marginInlineStart: 10 }}>کد ملی: {m.national_id}</span>}
                                        </div>
                                        <span className="badge gold">عضو ویژه</span>
                                    </div>

                                    <div style={{ display: 'flex', gap: 18, flexWrap: 'wrap', marginBottom: 14, fontSize: 13, color: 'var(--muted)' }}>
                                        {m.email && <span>ایمیل: <strong style={{ color: 'var(--txt)' }}>{m.email}</strong></span>}
                                        {m.birth_date && <span>تاریخ تولد: <strong style={{ color: 'var(--txt)' }}>{m.birth_date}</strong></span>}
                                        {m.residence_address && <span>آدرس: <strong style={{ color: 'var(--txt)' }}>{m.residence_address}</strong></span>}
                                        <span>تاریخ تأیید: <strong style={{ color: 'var(--txt)' }}>{m.approved_at}</strong></span>
                                    </div>

                                    {(m.national_id_doc || m.identity_doc || m.verification_video) && (
                                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 14 }}>
                                            <div>
                                                <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>تصویر کارت ملی</div>
                                                {m.national_id_doc
                                                    ? <a href={m.national_id_doc} target="_blank" rel="noopener noreferrer">
                                                        <img src={m.national_id_doc} alt="کارت ملی" style={{ width: '100%', borderRadius: 10, border: '1px solid var(--line)' }} />
                                                      </a>
                                                    : <div style={{ color: 'var(--muted)' }}>—</div>}
                                            </div>
                                            <div>
                                                <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>جواز صنفی</div>
                                                {m.identity_doc
                                                    ? <a href={m.identity_doc} target="_blank" rel="noopener noreferrer">
                                                        <img src={m.identity_doc} alt="جواز صنفی" style={{ width: '100%', borderRadius: 10, border: '1px solid var(--line)' }} />
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
                                    )}

                                    <div style={{ marginTop: 14 }}>
                                        <Link href={`/admin/users/${m.id}/trades`} className="btn-sm gold">ریز معاملات</Link>
                                        <AdminMembershipForm member={m} userOptions={userOptions} />
                                        {m.id !== auth.user.id && (
                                            <button
                                                type="button"
                                                onClick={() => makeRegular(m.id, m.name)}
                                                className="btn-sm danger"
                                                style={{ marginInlineStart: 8 }}
                                            >
                                                تبدیل به عادی
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        ) : (
                            <div className="empty"><div className="ico">👑</div>هیچ عضو ویژه‌ای ثبت نشده.</div>
                        )}
                        <Pager page={vipPager.page} totalPages={vipPager.totalPages} onChange={vipPager.setPage} />
                    </>
                )}

                {/* تحویل فیزیکی */}
                {tab === 'delivery' && (
                    <>
                        <div className="no-print" style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap', marginBottom: 14 }}>
                            <SearchBox value={deliveryQ} onChange={setDeliveryQ} placeholder="🔍 جستجو در کاربر، موبایل، گیرنده، آدرس..." />
                            <DateRangeFilter from={deliveryFrom} to={deliveryTo} onFromChange={setDeliveryFrom} onToChange={setDeliveryTo} />
                            {(deliveryFrom || deliveryTo) && <button type="button" className="btn-sm" onClick={() => { setDeliveryFrom(''); setDeliveryTo(''); }}>حذف فیلتر تاریخ</button>}
                            <button type="button" className="btn-sm gold" onClick={() => window.print()}>🖨️ چاپ / خروجی PDF</button>
                        </div>
                        {filteredDeliveryRequests.length ? (
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>کاربر</th><th>موبایل</th><th>مورد</th><th>مقدار</th><th>گیرنده</th><th>روش تحویل</th><th>آدرس</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
                                <tbody>
                                    {deliveryPager.pageItems.map(r => (
                                        <DeliveryRow key={r.id} r={r} deliveryNote={deliveryNote} setDeliveryNote={setDeliveryNote} updateDelivery={updateDelivery} />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        ) : (
                            <div className="empty"><div className="ico">🚚</div>درخواست تحویل فیزیکی‌ای ثبت نشده.</div>
                        )}
                        <Pager page={deliveryPager.page} totalPages={deliveryPager.totalPages} onChange={deliveryPager.setPage} />

                        <div className="table-wrap print-area print-only-block">
                            <div className="print-only" style={{ marginBottom: 14, fontWeight: 800, fontSize: 16 }}>درخواست‌های تحویل فیزیکی</div>
                            <table>
                                <thead><tr><th>کاربر</th><th>موبایل</th><th>مورد</th><th>مقدار</th><th>گیرنده</th><th>روش تحویل</th><th>آدرس</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
                                <tbody>
                                    {filteredDeliveryRequests.map(r => <DeliveryRow key={r.id} r={r} printOnly />)}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {/* تسویه حساب */}
                {tab === 'withdrawals' && (
                    <>
                        <div className="no-print" style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap', marginBottom: 14 }}>
                            <SearchBox value={withdrawalsQ} onChange={setWithdrawalsQ} placeholder="🔍 جستجو در کاربر، موبایل، شماره کارت/شبا..." />
                            <DateRangeFilter from={withdrawalsFrom} to={withdrawalsTo} onFromChange={setWithdrawalsFrom} onToChange={setWithdrawalsTo} />
                            {(withdrawalsFrom || withdrawalsTo) && <button type="button" className="btn-sm" onClick={() => { setWithdrawalsFrom(''); setWithdrawalsTo(''); }}>حذف فیلتر تاریخ</button>}
                            <button type="button" className="btn-sm gold" onClick={() => window.print()}>🖨️ چاپ / خروجی PDF</button>
                        </div>
                        {filteredWithdrawalRequests.length ? (
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>کاربر</th><th>موبایل</th><th>مبلغ</th><th>شماره کارت</th><th>شماره شبا</th><th>تاریخ</th><th></th></tr></thead>
                                <tbody>
                                    {withdrawalsPager.pageItems.map(w => (
                                        <WithdrawalRow key={w.id} w={w} withdrawalNote={withdrawalNote} setWithdrawalNote={setWithdrawalNote}
                                            withdrawalReason={withdrawalReason} setWithdrawalReason={setWithdrawalReason}
                                            approveWithdrawal={approveWithdrawal} rejectWithdrawal={rejectWithdrawal} />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        ) : (
                            <div className="empty"><div className="ico">🏦</div>درخواست تسویه حساب در انتظار بررسی نیست.</div>
                        )}
                        <Pager page={withdrawalsPager.page} totalPages={withdrawalsPager.totalPages} onChange={withdrawalsPager.setPage} />

                        <div className="table-wrap print-area print-only-block">
                            <div className="print-only" style={{ marginBottom: 14, fontWeight: 800, fontSize: 16 }}>درخواست‌های تسویه حساب</div>
                            <table>
                                <thead><tr><th>کاربر</th><th>موبایل</th><th>مبلغ</th><th>شماره کارت</th><th>شماره شبا</th><th>تاریخ</th></tr></thead>
                                <tbody>
                                    {filteredWithdrawalRequests.map(w => <WithdrawalRow key={w.id} w={w} printOnly />)}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {/* افزایش موجودی */}
                {tab === 'deposits' && (
                    <>
                        <div className="no-print" style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap', marginBottom: 14 }}>
                            <SearchBox value={depositsQ} onChange={setDepositsQ} placeholder="🔍 جستجو در کاربر، موبایل، توضیح..." />
                            <DateRangeFilter from={depositsFrom} to={depositsTo} onFromChange={setDepositsFrom} onToChange={setDepositsTo} />
                            {(depositsFrom || depositsTo) && <button type="button" className="btn-sm" onClick={() => { setDepositsFrom(''); setDepositsTo(''); }}>حذف فیلتر تاریخ</button>}
                            <button type="button" className="btn-sm gold" onClick={() => window.print()}>🖨️ چاپ / خروجی PDF</button>
                        </div>
                        {filteredDepositRequests.length ? (
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>کاربر</th><th>موبایل</th><th>مبلغ</th><th>توضیح</th><th>تاریخ</th><th></th></tr></thead>
                                <tbody>
                                    {depositsPager.pageItems.map(d => (
                                        <DepositRow key={d.id} d={d} depositNote={depositNote} setDepositNote={setDepositNote}
                                            depositReason={depositReason} setDepositReason={setDepositReason}
                                            approveDeposit={approveDeposit} rejectDeposit={rejectDeposit} />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        ) : (
                            <div className="empty"><div className="ico">➕</div>درخواست افزایش موجودی در انتظار بررسی نیست.</div>
                        )}
                        <Pager page={depositsPager.page} totalPages={depositsPager.totalPages} onChange={depositsPager.setPage} />

                        <div className="table-wrap print-area print-only-block">
                            <div className="print-only" style={{ marginBottom: 14, fontWeight: 800, fontSize: 16 }}>درخواست‌های افزایش موجودی</div>
                            <table>
                                <thead><tr><th>کاربر</th><th>موبایل</th><th>مبلغ</th><th>توضیح</th><th>تاریخ</th></tr></thead>
                                <tbody>
                                    {filteredDepositRequests.map(d => <DepositRow key={d.id} d={d} printOnly />)}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {tab === 'inventory_increases' && (
                    <>
                        {inventoryIncreaseRequests?.length ? (
                            <div className="table-wrap">
                                <table>
                                    <thead><tr><th>کاربر</th><th>موبایل</th><th>مورد</th><th>مقدار</th><th>توضیحات کاربر</th><th>تاریخ</th><th>اقدام</th></tr></thead>
                                    <tbody>{inventoryIncreaseRequests.map(r => (
                                        <tr key={r.id}>
                                            <td><strong>{r.user_name}</strong></td>
                                            <td className="num" dir="ltr" style={{ fontSize: 13 }}>{r.user_phone}</td>
                                            <td>{r.metal === 'gold' ? 'طلا' : `نقره ${r.purity}`}</td>
                                            <td className="num">{r.grams} گرم</td>
                                            <td>{r.note || '—'}</td>
                                            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{r.created_at}</td>
                                            <td style={{ minWidth: 220 }}>
                                                <input value={inventoryIncreaseNote[r.id] || ''} onChange={e => setInventoryIncreaseNote(s => ({ ...s, [r.id]: e.target.value }))} placeholder="یادداشت / دلیل رد"
                                                    style={{ width: '100%', marginBottom: 6, background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 8, padding: '5px 8px', fontFamily: 'inherit', fontSize: 12 }} />
                                                <div style={{ display: 'flex', gap: 6 }}>
                                                    <button onClick={() => approveInventoryIncrease(r.id)} className="btn-sm" style={{ borderColor: 'rgba(65,225,166,.4)', color: 'var(--up)', background: 'rgba(65,225,166,.08)' }}>تأیید</button>
                                                    <button onClick={() => rejectInventoryIncrease(r.id)} className="btn-sm danger">رد</button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}</tbody>
                                </table>
                            </div>
                        ) : <div className="empty"><div className="ico">+</div>درخواست افزایش موجودی انبار در انتظار بررسی نیست.</div>}
                    </>
                )}

                {/* تیکت‌ها */}
                {tab === 'tickets' && (
                    <>
                        <div className="no-print" style={{ marginBottom: 14 }}>
                            <SearchBox value={ticketsQ} onChange={setTicketsQ} placeholder="🔍 جستجو در موضوع، کاربر، موبایل..." />
                        </div>
                        {filteredTickets.length ? (
                            <div className="table-wrap">
                                <table>
                                    <thead><tr><th>موضوع</th><th>کاربر</th><th>موبایل</th><th>وضعیت</th><th>تعداد پیام</th><th>تاریخ</th><th></th></tr></thead>
                                    <tbody>
                                        {ticketsPager.pageItems.map(t => {
                                            const [label, badge] = TICKET_STATUS[t.status] || TICKET_STATUS.open;
                                            return (
                                                <tr key={t.id}>
                                                    <td><strong>{t.subject}</strong></td>
                                                    <td>{t.user_name}</td>
                                                    <td className="num" dir="ltr" style={{ fontSize: 13 }}>{t.user_phone}</td>
                                                    <td><span className={`badge ${badge}`}>{label}</span></td>
                                                    <td className="num">{t.msg_count}</td>
                                                    <td style={{ fontSize: 12, color: 'var(--muted)' }}>{t.created_at}</td>
                                                    <td><Link href={`/admin/tickets/${t.id}`} className="btn-sm gold">مشاهده / پاسخ</Link></td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="empty"><div className="ico">🎫</div>هیچ تیکتی ثبت نشده.</div>
                        )}
                        <Pager page={ticketsPager.page} totalPages={ticketsPager.totalPages} onChange={ticketsPager.setPage} />
                    </>
                )}

                {/* رخدادهای امنیتی */}
                {tab === 'security' && (
                    <>
                        <div className="no-print" style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap', marginBottom: 18 }}>
                            <SearchBox value={securityQ} onChange={setSecurityQ} placeholder="🔍 جستجو در کاربر، IP، مسیر، نوع رخداد..." />
                            <DateRangeFilter from={securityFrom} to={securityTo} onFromChange={setSecurityFrom} onToChange={setSecurityTo} />
                            {(securityFrom || securityTo || securityQ) && <button type="button" className="btn-sm" onClick={() => { setSecurityFrom(''); setSecurityTo(''); setSecurityQ(''); }}>حذف فیلتر</button>}
                            <button type="button" className="btn-sm gold" onClick={() => window.print()}>🖨️ چاپ / خروجی PDF</button>
                        </div>

                        {filteredSecurityEvents.length ? (
                            <>
                                <div className="table-wrap">
                                    <table>
                                        <thead><tr><th>تاریخ</th><th>نوع</th><th>کاربر</th><th>IP</th><th>مسیر</th><th>فیلد مشکوک</th><th>نمونه داده</th></tr></thead>
                                        <tbody>
                                            {securityPager.pageItems.map(e => <SecurityEventRow key={e.id} e={e} />)}
                                        </tbody>
                                    </table>
                                </div>
                                <Pager page={securityPager.page} totalPages={securityPager.totalPages} onChange={securityPager.setPage} />

                                <div className="table-wrap print-area print-only-block">
                                    <div className="print-only" style={{ marginBottom: 6, fontWeight: 800, fontSize: 16 }}>رخدادهای امنیتی</div>
                                    <table>
                                        <thead><tr><th>تاریخ</th><th>نوع</th><th>کاربر</th><th>IP</th><th>مسیر</th><th>فیلد مشکوک</th><th>نمونه داده</th></tr></thead>
                                        <tbody>
                                            {filteredSecurityEvents.map(e => <SecurityEventRow key={e.id} e={e} />)}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        ) : (
                            <div className="empty"><div className="ico">🛡️</div>رخداد امنیتی برای نمایش نیست.</div>
                        )}
                    </>
                )}

                {/* گزارش فعالیت (سیستم لاگ) */}
                {tab === 'logs' && (
                    <>
                        <div className="no-print" style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap', marginBottom: 18 }}>
                            <SearchBox value={logsQ} onChange={setLogsQ} placeholder="🔍 جستجو در شرح، کاربر..." />
                            <div>
                                <label style={{ display: 'block', fontSize: 13, color: 'var(--muted)', marginBottom: 6, fontWeight: 600 }}>دسته</label>
                                <select value={logCat} onChange={e => setLogCat(e.target.value)}
                                    style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 10, padding: '9px 12px', fontFamily: 'inherit', fontSize: 14 }}>
                                    <option value="all">همه</option>
                                    {Object.entries(LOG_CAT).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
                                </select>
                            </div>
                            <DateRangeFilter from={logFrom} to={logTo} onFromChange={setLogFrom} onToChange={setLogTo} />
                            {(logFrom || logTo || logCat !== 'all' || logsQ) && <button type="button" className="btn-sm" onClick={() => { setLogFrom(''); setLogTo(''); setLogCat('all'); setLogsQ(''); }}>حذف فیلتر</button>}
                            <button type="button" className="btn-sm gold" onClick={() => window.print()}>🖨️ چاپ / خروجی PDF</button>
                        </div>

                        {filteredLogs.length ? (
                            <>
                                <div className="table-wrap">
                                    <table>
                                        <thead><tr><th>تاریخ</th><th>دسته</th><th>کاربر</th><th>شرح</th><th>IP</th></tr></thead>
                                        <tbody>
                                            {logsPager.pageItems.map(l => <LogRow key={l.id} l={l} />)}
                                        </tbody>
                                    </table>
                                </div>
                                <Pager page={logsPager.page} totalPages={logsPager.totalPages} onChange={logsPager.setPage} />

                                <div className="table-wrap print-area print-only-block">
                                    <div className="print-only" style={{ marginBottom: 6, fontWeight: 800, fontSize: 16 }}>گزارش فعالیت سامانه</div>
                                    <table>
                                        <thead><tr><th>تاریخ</th><th>دسته</th><th>کاربر</th><th>شرح</th><th>IP</th></tr></thead>
                                        <tbody>
                                            {filteredLogs.map(l => <LogRow key={l.id} l={l} />)}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        ) : (
                            <div className="empty"><div className="ico">🗒️</div>رویدادی برای نمایش نیست.</div>
                        )}
                    </>
                )}

                {/* تنظیمات */}
                {tab === 'settings' && (
                    <div className="fcard" style={{ maxWidth: 760 }}>
                        <h2 style={{ fontSize: 18 }}>تنظیمات سایت</h2>
                        <div style={{ height: 16 }} />
                        <form onSubmit={e => { e.preventDefault(); settingsForm.post('/admin/settings', { preserveScroll: true }); }}>
                            <div style={{ display: 'grid', gap: 22 }}>
                                <section>
                                    <h3 style={{ fontSize: 15, marginBottom: 12, color: 'var(--gold-1)' }}>اتاق معاملاتی</h3>
                                    <div className="field">
                                        <label>کارمزد اتاق معاملاتی (درصد) — بین خریدار و فروشنده نصف‌نصف کسر می‌شود</label>
                                        <input type="number" step="0.01" min="0" max="10"
                                            value={settingsForm.data.trade_room_commission_percent}
                                            onChange={e => settingsForm.setData('trade_room_commission_percent', e.target.value)} required />
                                        {settingsForm.errors.trade_room_commission_percent && <div className="alert err" style={{ marginTop: 8 }}>{settingsForm.errors.trade_room_commission_percent}</div>}
                                    </div>
                                </section>

                                <section>
                                    <h3 style={{ fontSize: 15, marginBottom: 12, color: 'var(--gold-1)' }}>صفحه درباره ما</h3>
                                    <div className="field">
                                        <label>عنوان</label>
                                        <input value={settingsForm.data.about_title} maxLength="160"
                                            onChange={e => settingsForm.setData('about_title', e.target.value)} />
                                        {settingsForm.errors.about_title && <div className="alert err" style={{ marginTop: 8 }}>{settingsForm.errors.about_title}</div>}
                                    </div>
                                    <div className="field">
                                        <label>متن — برای پاراگراف جدید یک خط خالی بگذارید</label>
                                        <textarea rows="9" value={settingsForm.data.about_body} maxLength="10000"
                                            onChange={e => settingsForm.setData('about_body', e.target.value)} />
                                        {settingsForm.errors.about_body && <div className="alert err" style={{ marginTop: 8 }}>{settingsForm.errors.about_body}</div>}
                                    </div>
                                </section>

                                <section>
                                    <h3 style={{ fontSize: 15, marginBottom: 12, color: 'var(--gold-1)' }}>صفحه تماس با ما</h3>
                                    <div className="field">
                                        <label>عنوان</label>
                                        <input value={settingsForm.data.contact_title} maxLength="160"
                                            onChange={e => settingsForm.setData('contact_title', e.target.value)} />
                                        {settingsForm.errors.contact_title && <div className="alert err" style={{ marginTop: 8 }}>{settingsForm.errors.contact_title}</div>}
                                    </div>
                                    <div className="field">
                                        <label>متن معرفی بالای فرم</label>
                                        <textarea rows="4" value={settingsForm.data.contact_intro} maxLength="1000"
                                            onChange={e => settingsForm.setData('contact_intro', e.target.value)} />
                                        {settingsForm.errors.contact_intro && <div className="alert err" style={{ marginTop: 8 }}>{settingsForm.errors.contact_intro}</div>}
                                    </div>
                                </section>
                            </div>
                            <button className="btn" type="submit" disabled={settingsForm.processing} style={{ width: 'auto', padding: '11px 28px' }}>
                                {settingsForm.processing ? '...' : 'ذخیره تنظیمات'}
                            </button>
                        </form>
                    </div>
                )}

            </div>
        </AppLayout>
    );
}
