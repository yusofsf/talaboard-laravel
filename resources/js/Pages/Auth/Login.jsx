import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({ phone: '', password: '', remember: false });

    function submit(e) {
        e.preventDefault();
        post('/login');
    }

    return (
        <AppLayout>
            <div className="page">
                <div className="fcard">
                    <h2>ورود به حساب</h2>
                    <div style={{ height: 20 }} />
                    {errors.phone && <div className="alert err">{errors.phone}</div>}
                    <form onSubmit={submit}>
                        <div className="field">
                            <label>شماره موبایل</label>
                            <input type="tel" inputMode="numeric" value={data.phone}
                                onChange={e => setData('phone', e.target.value)}
                                placeholder="۰۹۱۲۳۴۵۶۷۸۹" required />
                        </div>
                        <div className="field">
                            <label>رمز عبور</label>
                            <input type="password" value={data.password}
                                onChange={e => setData('password', e.target.value)} required />
                        </div>
                        <button className="btn" type="submit" disabled={processing}>
                            {processing ? 'در حال ورود...' : 'ورود'}
                        </button>
                    </form>
                    <div className="form-foot">
                        <a href="/forgot-password">فراموشی رمز عبور</a>
                        {' · '}
                        <a href="/register">ثبت‌نام</a>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
