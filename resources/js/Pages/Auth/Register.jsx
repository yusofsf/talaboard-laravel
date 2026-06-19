import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '', phone: '', password: '', password_confirmation: '',
    });

    function submit(e) {
        e.preventDefault();
        post('/register');
    }

    return (
        <AppLayout>
            <div className="page">
                <div className="fcard">
                    <h2>ثبت‌نام</h2>
                    <div style={{ height: 20 }} />
                    {Object.values(errors).map((e, i) => <div key={i} className="alert err">{e}</div>)}
                    <form onSubmit={submit}>
                        <div className="field">
                            <label>نام و نام خانوادگی</label>
                            <input value={data.name} onChange={e => setData('name', e.target.value)} required />
                        </div>
                        <div className="field">
                            <label>شماره موبایل</label>
                            <input type="tel" inputMode="numeric" value={data.phone}
                                onChange={e => setData('phone', e.target.value)} required />
                        </div>
                        <div className="field">
                            <label>رمز عبور</label>
                            <input type="password" value={data.password}
                                onChange={e => setData('password', e.target.value)} required />
                        </div>
                        <div className="field">
                            <label>تکرار رمز عبور</label>
                            <input type="password" value={data.password_confirmation}
                                onChange={e => setData('password_confirmation', e.target.value)} required />
                        </div>
                        <button className="btn" type="submit" disabled={processing}>
                            {processing ? '...' : 'ثبت‌نام'}
                        </button>
                    </form>
                    <div className="form-foot"><a href="/login">قبلاً ثبت‌نام کرده‌اید؟ ورود</a></div>
                </div>
            </div>
        </AppLayout>
    );
}
