import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function ForgotPassword() {
    const { data, setData, post, processing, errors } = useForm({ phone: '' });

    function submit(e) {
        e.preventDefault();
        post('/forgot-password');
    }

    return (
        <AppLayout>
            <div className="page">
                <div className="fcard">
                    <h2>فراموشی رمز عبور</h2>
                    <div style={{ height: 20 }} />
                    <div className="alert info">شماره موبایل خود را وارد کنید. کد بازیابی برایتان ارسال می‌شود.</div>
                    {errors.phone && <div className="alert err">{errors.phone}</div>}
                    <form onSubmit={submit}>
                        <div className="field">
                            <label>شماره موبایل</label>
                            <input type="tel" inputMode="numeric" value={data.phone}
                                onChange={e => setData('phone', e.target.value)} required />
                        </div>
                        <button className="btn" type="submit" disabled={processing}>ارسال کد</button>
                    </form>
                    <div className="form-foot"><a href="/login">بازگشت به ورود</a></div>
                </div>
            </div>
        </AppLayout>
    );
}
