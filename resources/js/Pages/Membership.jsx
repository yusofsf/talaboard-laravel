import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

export default function Membership({ user }) {
    const { errors, flash } = usePage().props;
    const code = useForm({ code: '' });
    const apply = useForm({
        national_id_doc: null,
        identity_doc: null,
        verification_video: null,
    });

    function submitCode(e) {
        e.preventDefault();
        code.post('/membership');
    }

    function submitApply(e) {
        e.preventDefault();
        apply.post('/membership/apply', {
            forceFormData: true,
            onSuccess: () => apply.reset(),
        });
    }

    // عضو ویژه — سطح ۲
    if (user.is_vip || user.membership_level === 2) {
        return (
            <AppLayout>
                <div className="page">
                    <div className="fcard" style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: 56, marginBottom: 16 }}>👑</div>
                        <h2 style={{ justifyContent: 'center' }}>عضویت ویژه فعال است</h2>
                        <p style={{ color: 'var(--muted)', marginTop: 12 }}>شما به تمام امکانات ویژه دسترسی دارید.</p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    // درخواست در حال بررسی
    if (user.membership_status === 'pending') {
        return (
            <AppLayout>
                <div className="page">
                    <div className="fcard" style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: 56, marginBottom: 16 }}>⏳</div>
                        <h2 style={{ justifyContent: 'center' }}>درخواست شما در حال بررسی است</h2>
                        <p style={{ color: 'var(--muted)', marginTop: 12 }}>
                            کارشناسان ما مستندات شما را بررسی می‌کنند. نتیجه از طریق اعلانات به شما اطلاع‌رسانی می‌شود.
                        </p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <div className="page">

                {user.membership_status === 'rejected' && (
                    <div className="alert err" style={{ marginBottom: 20 }}>
                        درخواست قبلی شما رد شد. می‌توانید مستندات را دوباره ارسال کنید.
                    </div>
                )}

                {/* کد دعوت — فعال‌سازی فوری */}
                <div className="fcard" style={{ marginBottom: 24 }}>
                    <h2>عضویت ویژه با کد دعوت 👑</h2>
                    <div style={{ height: 20 }} />
                    <div className="alert info">اگر کد دعوت دارید، همین‌جا وارد کنید — فعال‌سازی آن.</div>
                    {errors.code && <div className="alert err">{errors.code}</div>}
                    <form onSubmit={submitCode}>
                        <div className="field">
                            <label>کد دعوت</label>
                            <input value={code.data.code} onChange={e => code.setData('code', e.target.value.toUpperCase())}
                                placeholder="مثال: ABCD1234" style={{ letterSpacing: 4, textAlign: 'center' }} required />
                        </div>
                        <button className="btn" type="submit" disabled={code.processing}>فعال‌سازی</button>
                    </form>
                </div>

                {/* درخواست احراز هویت */}
                <div className="fcard">
                    <h2>درخواست عضویت ویژه با احراز هویت</h2>
                    <div style={{ height: 20 }} />
                    <div className="alert info">
                        برای عضویت ویژه از این روش، تصویر کارت ملی، تصویر مدرک شناسایی و یک فیلم کوتاه اعتبارسنجی ارسال کنید.
                        پس از بررسی توسط ادمین، سطح حساب شما به‌روزرسانی می‌شود.
                    </div>
                    {errors.national_id_doc && <div className="alert err">{errors.national_id_doc}</div>}
                    {errors.identity_doc && <div className="alert err">{errors.identity_doc}</div>}
                    {errors.verification_video && <div className="alert err">{errors.verification_video}</div>}

                    <form onSubmit={submitApply}>
                        <div className="field">
                            <label>تصویر کارت ملی (jpg، png یا pdf — حداکثر ۵ مگابایت)</label>
                            <input type="file" accept=".jpg,.jpeg,.png,.pdf"
                                onChange={e => apply.setData('national_id_doc', e.target.files[0])} required />
                        </div>
                        <div className="field">
                            <label>تصویر مدرک شناسایی (jpg، png یا pdf — حداکثر ۵ مگابایت)</label>
                            <input type="file" accept=".jpg,.jpeg,.png,.pdf"
                                onChange={e => apply.setData('identity_doc', e.target.files[0])} required />
                        </div>
                        <div className="field">
                            <label>فیلم اعتبارسنجی (mp4، mov، avi یا webm — حداکثر ۵۰ مگابایت)</label>
                            <input type="file" accept=".mp4,.mov,.avi,.webm"
                                onChange={e => apply.setData('verification_video', e.target.files[0])} required />
                        </div>
                        <button className="btn" type="submit" disabled={apply.processing}>
                            {apply.processing ? 'در حال ارسال...' : 'ارسال درخواست'}
                        </button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
