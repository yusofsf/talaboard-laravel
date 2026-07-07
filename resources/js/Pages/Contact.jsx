import { useForm } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

export default function Contact() {
    const form = useForm({ name: '', email: '', subject: '', message: '' });

    function submit(e) {
        e.preventDefault();
        form.post('/contact', { preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <AppLayout>
            <main className="page">
                <section className="fcard">
                    <h1 style={{ fontSize: 26, margin: '0 0 8px' }}>تماس با ما</h1>
                    <p style={{ color: 'var(--muted)', lineHeight: 2, marginBottom: 22 }}>
                        پیام خود را برای آبشده صفرپور بفرستید. پاسخ از طریق ایمیل ثبت‌شده پیگیری می‌شود.
                    </p>

                    <form onSubmit={submit}>
                        <Field label="نام" error={form.errors.name}>
                            <input value={form.data.name} onChange={e => form.setData('name', e.target.value)} maxLength="100" required />
                        </Field>
                        <Field label="ایمیل" error={form.errors.email}>
                            <input dir="ltr" type="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)} maxLength="150" required />
                        </Field>
                        <Field label="موضوع" error={form.errors.subject}>
                            <input value={form.data.subject} onChange={e => form.setData('subject', e.target.value)} maxLength="150" required />
                        </Field>
                        <Field label="متن پیام" error={form.errors.message}>
                            <textarea value={form.data.message} onChange={e => form.setData('message', e.target.value)} rows="7" maxLength="3000" required />
                        </Field>
                        <button className="btn" type="submit" disabled={form.processing}>
                            {form.processing ? 'در حال ارسال...' : 'ارسال پیام'}
                        </button>
                    </form>
                </section>
            </main>
        </AppLayout>
    );
}

function Field({ label, error, children }) {
    return (
        <div className="field">
            <label>{label}</label>
            {children}
            {error && <div style={{ color: 'var(--down)', fontSize: 12, marginTop: 6 }}>{error}</div>}
        </div>
    );
}
