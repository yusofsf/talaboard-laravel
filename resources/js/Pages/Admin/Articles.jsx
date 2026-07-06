import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const empty = { title: '', slug: '', summary: '', thumbnail_image: '', body_image: '', body: '', tags: '', topics: '', is_published: true };

export default function Articles({ articles }) {
    const form = useForm(empty);
    const [editing, setEditing] = useState(null);

    function submit(e) {
        e.preventDefault();
        if (editing) form.put(`/admin/articles/${editing}`, { preserveScroll: true, onSuccess: reset });
        else form.post('/admin/articles', { preserveScroll: true, onSuccess: reset });
    }

    function reset() {
        setEditing(null);
        form.setData(empty);
        form.clearErrors();
    }

    function edit(a) {
        setEditing(a.id);
        form.setData({
            title: a.title || '',
            slug: a.slug || '',
            summary: a.summary || '',
            thumbnail_image: a.thumbnail_image || '',
            body_image: a.body_image || '',
            body: a.body || '',
            tags: a.tags || '',
            topics: a.topics || '',
            is_published: !!a.is_published,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function destroy(id) {
        if (!confirm('این مقاله حذف شود؟')) return;
        router.delete(`/admin/articles/${id}`, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <main className="page-wide">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, marginBottom: 18 }}>
                    <h1 style={{ fontSize: 24 }}>مدیریت مقالات</h1>
                    <Link href="/admin" className="btn-sm">بازگشت به پنل</Link>
                </div>

                <form onSubmit={submit} className="fcard" style={{ marginBottom: 24 }}>
                    <h2 style={{ fontSize: 16 }}>{editing ? 'ویرایش مقاله' : 'مقاله جدید'}</h2>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 12, marginTop: 14 }}>
                        <Field label="عنوان" error={form.errors.title}><input value={form.data.title} onChange={e => form.setData('title', e.target.value)} required /></Field>
                        <Field label="Slug انگلیسی" error={form.errors.slug}><input dir="ltr" value={form.data.slug} onChange={e => form.setData('slug', e.target.value)} placeholder="gold-buying-guide" /></Field>
                        <Field label="تصویر بندانگشتی" error={form.errors.thumbnail_image}><input dir="ltr" value={form.data.thumbnail_image} onChange={e => form.setData('thumbnail_image', e.target.value)} placeholder="/images/article.jpg" /></Field>
                        <Field label="تصویر داخل متن" error={form.errors.body_image}><input dir="ltr" value={form.data.body_image} onChange={e => form.setData('body_image', e.target.value)} /></Field>
                        <Field label="موضوعات" error={form.errors.topics}><input value={form.data.topics} onChange={e => form.setData('topics', e.target.value)} placeholder="طلا، آموزش خرید" /></Field>
                        <Field label="تگ‌ها" error={form.errors.tags}><input value={form.data.tags} onChange={e => form.setData('tags', e.target.value)} placeholder="سکه، نقره، عیار" /></Field>
                    </div>
                    <Field label="خلاصه" error={form.errors.summary}><textarea value={form.data.summary} onChange={e => form.setData('summary', e.target.value)} rows={2} /></Field>
                    <Field label="متن مقاله" error={form.errors.body}><textarea value={form.data.body} onChange={e => form.setData('body', e.target.value)} rows={10} required /></Field>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '12px 0' }}>
                        <input type="checkbox" checked={form.data.is_published} onChange={e => form.setData('is_published', e.target.checked)} />
                        منتشر شود
                    </label>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button className="btn" type="submit" disabled={form.processing} style={{ width: 'auto', padding: '10px 28px' }}>{editing ? 'ذخیره ویرایش' : 'ثبت مقاله'}</button>
                        {editing && <button type="button" className="btn-sm" onClick={reset}>لغو</button>}
                    </div>
                </form>

                <div className="table-wrap">
                    <table>
                        <thead><tr><th>عنوان</th><th>Slug</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
                        <tbody>
                            {articles.map(a => (
                                <tr key={a.id}>
                                    <td><strong>{a.title}</strong><div style={{ color: 'var(--muted)', fontSize: 12 }}>{a.summary}</div></td>
                                    <td dir="ltr" style={{ fontSize: 12 }}>{a.slug}</td>
                                    <td><span className={`badge ${a.is_published ? 'buy-b' : 'silver'}`}>{a.is_published ? 'منتشر شده' : 'پیش‌نویس'}</span></td>
                                    <td style={{ color: 'var(--muted)', fontSize: 12 }}>{a.published_at || a.created_at}</td>
                                    <td><div style={{ display: 'flex', gap: 6 }}><button className="btn-sm" onClick={() => edit(a)}>ویرایش</button><button className="btn-sm danger" onClick={() => destroy(a.id)}>حذف</button></div></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </main>
        </AppLayout>
    );
}

function Field({ label, error, children }) {
    return <div className="field"><label>{label}</label>{children}{error && <div className="alert err" style={{ marginTop: 6 }}>{error}</div>}</div>;
}
