import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';
import VideoRecorder from '../Components/VideoRecorder';

const DECLARATION = `اینجانب [نام و نام خانوادگی] فرزند [نام پدر] با کد ملی [کد ملی]، در تاریخ [تاریخ روز]، درخواست احراز هویت در سایت metalsp.ir (http://metalsp.ir/) را ثبت می‌کنم و تأیید می‌نمایم که این حساب کاربری متعلق به شخص اینجانب بوده و مسئولیت تمامی فعالیت‌های انجام‌شده با آن را می‌پذیرم.`;

export default function Membership({ user }) {
    const { errors } = usePage().props;
    const apply = useForm({
        national_id_doc: null,
        identity_doc: null,
        verification_video: null,
        birth_date: '',
        residence_address: '',
    });

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
                        <h2 style={{ justifyContent: 'center' }}>درخواست شما در حال بررسی می‌باشد</h2>
                        <p style={{ color: 'var(--muted)', marginTop: 12 }}>
                            کارشناسان ما مستندات شما را بررسی می‌کنند. نتیجه از طریق اعلانات و پیامک به شما اطلاع‌رسانی می‌شود.
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

                <div className="fcard">
                    <h2>درخواست عضویت ویژه با احراز هویت 👑</h2>
                    <div style={{ height: 20 }} />
                    <div className="alert info">
                        برای عضویت ویژه، تصویر کارت ملی، جواز صنفی، آدرس محل سکونت، تاریخ تولد و یک فیلم کوتاه اعتبارسنجی ارسال کنید.
                        پس از بررسی توسط ادمین، سطح حساب شما به‌روزرسانی می‌شود.
                    </div>
                    {Object.values(errors).map((e, i) => <div key={i} className="alert err">{e}</div>)}

                    <form onSubmit={submitApply}>
                        <div className="field">
                            <label>تاریخ تولد</label>
                            <input type="date" value={apply.data.birth_date}
                                onChange={e => apply.setData('birth_date', e.target.value)} required />
                        </div>
                        <div className="field">
                            <label>آدرس محل سکونت</label>
                            <input value={apply.data.residence_address}
                                onChange={e => apply.setData('residence_address', e.target.value)}
                                placeholder="استان، شهر، خیابان، پلاک..." required />
                        </div>
                        <div className="field">
                            <label>تصویر کارت ملی (jpg یا png — حداکثر ۲۰۰ کیلوبایت)</label>
                            <input type="file" accept=".jpg,.jpeg,.png" disabled={apply.processing}
                                onChange={e => apply.setData('national_id_doc', e.target.files[0])} required />
                        </div>
                        <div className="field">
                            <label>تصویر جواز صنفی (jpg یا png — حداکثر ۲۰۰ کیلوبایت)</label>
                            <input type="file" accept=".jpg,.jpeg,.png" disabled={apply.processing}
                                onChange={e => apply.setData('identity_doc', e.target.files[0])} required />
                        </div>

                        <div className="field">
                            <label>فیلم اعتبارسنجی (حداکثر ۵ مگابایت)</label>
                            <div className="alert info" style={{ fontSize: 13, lineHeight: 2 }}>
                                یک فیلم کوتاه از خودتان بگیرید (با رعایت موارد شرعی) و دقیقاً متن زیر را با صدای خودتان بخوانید:
                                <div style={{ marginTop: 8, padding: 12, background: 'rgba(255,255,255,.05)', borderRadius: 10, color: 'var(--txt)' }}>
                                    {DECLARATION}
                                </div>
                            </div>
                            <VideoRecorder maxSeconds={30} onRecorded={file => apply.setData('verification_video', file)} />
                        </div>

                        {apply.progress && (
                            <div style={{ marginBottom: 18 }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, color: 'var(--muted)', marginBottom: 6 }}>
                                    <span>در حال آپلود فایل‌ها...</span>
                                    <span>{Math.round(apply.progress.percentage).toLocaleString('fa-IR')}٪</span>
                                </div>
                                <div style={{ height: 10, background: 'rgba(255,255,255,.08)', borderRadius: 999, overflow: 'hidden' }}>
                                    <div style={{
                                        height: '100%', width: `${apply.progress.percentage}%`,
                                        background: 'linear-gradient(90deg,var(--gold-1),var(--gold-2))',
                                        transition: 'width .15s', borderRadius: 999,
                                    }} />
                                </div>
                            </div>
                        )}

                        <button className="btn" type="submit" disabled={apply.processing || !apply.data.verification_video}>
                            {apply.processing
                                ? (apply.progress ? `در حال آپلود... ${Math.round(apply.progress.percentage).toLocaleString('fa-IR')}٪` : 'در حال ارسال...')
                                : 'ارسال درخواست'}
                        </button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
