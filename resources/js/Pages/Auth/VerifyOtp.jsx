import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function VerifyOtp({ smsOk }) {
    const { data, setData, post, processing, errors } = useForm({ otp: '' });

    function submit(e) {
        e.preventDefault();
        post('/verify-otp');
    }

    return (
        <AppLayout>
            <div className="page">
                <div className="fcard">
                    <h2>تأیید دو مرحله‌ای</h2>
                    <div style={{ height: 20 }} />
                    {!smsOk
                        ? <div className="alert err">ارسال پیامک ناموفق بود. در صورت دریافت کد OTP آن را وارد کنید.</div>
                        : <div className="alert info">کد تأیید به شماره شما ارسال شد. مدت اعتبار: ۲ دقیقه.</div>
                    }
                    {errors.otp && <div className="alert err">{errors.otp}</div>}
                    <form onSubmit={submit}>
                        <div className="field">
                            <label>کد ۶ رقمی</label>
                            <input inputMode="numeric" maxLength={6} autoComplete="one-time-code"
                                value={data.otp} onChange={e => setData('otp', e.target.value)}
                                style={{ letterSpacing: 6, fontSize: 24, textAlign: 'center' }} required />
                        </div>
                        <button className="btn" type="submit" disabled={processing}>تأیید</button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
