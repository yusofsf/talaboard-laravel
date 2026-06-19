import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function ResetPassword() {
    const { data, setData, post, processing, errors } = useForm({
        otp: '', password: '', password_confirmation: '',
    });

    function submit(e) {
        e.preventDefault();
        post('/reset-password');
    }

    return (
        <AppLayout>
            <div className="page">
                <div className="fcard">
                    <h2>تغییر رمز عبور</h2>
                    <div style={{ height: 20 }} />
                    {Object.values(errors).map((e, i) => <div key={i} className="alert err">{e}</div>)}
                    <form onSubmit={submit}>
                        <div className="field">
                            <label>کد تأیید (۶ رقم)</label>
                            <input inputMode="numeric" maxLength={6} autoComplete="one-time-code"
                                value={data.otp} onChange={e => setData('otp', e.target.value)}
                                style={{ letterSpacing: 6, fontSize: 24, textAlign: 'center' }} required />
                        </div>
                        <div className="field">
                            <label>رمز عبور جدید</label>
                            <input type="password" value={data.password}
                                onChange={e => setData('password', e.target.value)} required />
                        </div>
                        <div className="field">
                            <label>تکرار رمز جدید</label>
                            <input type="password" value={data.password_confirmation}
                                onChange={e => setData('password_confirmation', e.target.value)} required />
                        </div>
                        <button className="btn" type="submit" disabled={processing}>تغییر رمز</button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
