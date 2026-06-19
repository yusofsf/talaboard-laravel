import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

export default function Membership({ user }) {
    const { errors } = usePage().props;
    const { data, setData, post, processing } = useForm({ code: '' });

    function submit(e) {
        e.preventDefault();
        post('/membership');
    }

    if (user.is_vip) {
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

    return (
        <AppLayout>
            <div className="page">
                <div className="fcard">
                    <h2>عضویت ویژه 👑</h2>
                    <div style={{ height: 20 }} />
                    <div className="alert info">برای فعال‌سازی عضویت ویژه، کد دعوت خود را وارد کنید.</div>
                    {errors.code && <div className="alert err">{errors.code}</div>}
                    <form onSubmit={submit}>
                        <div className="field">
                            <label>کد دعوت</label>
                            <input value={data.code} onChange={e => setData('code', e.target.value.toUpperCase())}
                                placeholder="مثال: ABCD1234" style={{ letterSpacing: 4, textAlign: 'center' }} required />
                        </div>
                        <button className="btn" type="submit" disabled={processing}>فعال‌سازی</button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
