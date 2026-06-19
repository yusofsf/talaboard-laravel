import { useForm } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

export default function Profile({ user }) {
    const info = useForm({
        name: user.name || '', phone: user.phone || '',
        email: user.email || '', national_id: user.national_id || '',
    });
    const pw = useForm({ old_password: '', new_password: '', new_password_confirmation: '' });

    return (
        <AppLayout>
            <div className="page">
                <div className="fcard" style={{ marginBottom: 24 }}>
                    <h2>اطلاعات حساب</h2>
                    <div style={{ height: 20 }} />
                    {Object.values(info.errors).map((e, i) => <div key={i} className="alert err">{e}</div>)}
                    <form onSubmit={e => { e.preventDefault(); info.post('/profile/info'); }}>
                        <div className="field"><label>نام</label>
                            <input value={info.data.name} onChange={e => info.setData('name', e.target.value)} required /></div>
                        <div className="field"><label>موبایل</label>
                            <input type="tel" inputMode="numeric" value={info.data.phone} onChange={e => info.setData('phone', e.target.value)} required /></div>
                        <div className="field"><label>ایمیل</label>
                            <input type="email" value={info.data.email} onChange={e => info.setData('email', e.target.value)} /></div>
                        <div className="field"><label>کد ملی</label>
                            <input inputMode="numeric" value={info.data.national_id} onChange={e => info.setData('national_id', e.target.value)} /></div>
                        <button className="btn" type="submit" disabled={info.processing}>ذخیره</button>
                    </form>
                </div>

                <div className="fcard">
                    <h2>تغییر رمز عبور</h2>
                    <div style={{ height: 20 }} />
                    {Object.values(pw.errors).map((e, i) => <div key={i} className="alert err">{e}</div>)}
                    <form onSubmit={e => { e.preventDefault(); pw.post('/profile/password'); }}>
                        <div className="field"><label>رمز فعلی</label>
                            <input type="password" value={pw.data.old_password} onChange={e => pw.setData('old_password', e.target.value)} required /></div>
                        <div className="field"><label>رمز جدید</label>
                            <input type="password" value={pw.data.new_password} onChange={e => pw.setData('new_password', e.target.value)} required /></div>
                        <div className="field"><label>تکرار رمز جدید</label>
                            <input type="password" value={pw.data.new_password_confirmation} onChange={e => pw.setData('new_password_confirmation', e.target.value)} required /></div>
                        <button className="btn" type="submit" disabled={pw.processing}>تغییر رمز</button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
