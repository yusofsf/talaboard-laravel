import { useEffect, useRef, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const empty = {
    title: '',
    slug: '',
    summary: '',
    thumbnail_image: '',
    thumbnail_upload: null,
    body_image: '',
    body_upload: null,
    body: '',
    tags: '',
    topics: '',
    is_published: true,
};

const splitList = value => String(value || '').split(/[،,\n]+/).map(v => v.trim()).filter(Boolean);
const joinList = items => [...new Set(items)].join('، ');

export default function Articles({ articles, tagOptions = [], topicOptions = [] }) {
    const form = useForm(empty);
    const [editing, setEditing] = useState(null);

    function submit(e) {
        e.preventDefault();
        const options = { preserveScroll: true, forceFormData: true, onSuccess: reset };

        if (editing) {
            form.transform(data => ({ ...data, _method: 'put' }));
            form.post(`/admin/articles/${editing}`, options);
            form.transform(data => data);
            return;
        }

        form.transform(data => data);
        form.post('/admin/articles', options);
    }

    function reset() {
        setEditing(null);
        form.setData(empty);
        form.clearErrors();
    }

    function edit(article) {
        setEditing(article.id);
        form.setData({
            title: article.title || '',
            slug: article.slug || '',
            summary: article.summary || '',
            thumbnail_image: article.thumbnail_image || '',
            thumbnail_upload: null,
            body_image: article.body_image || '',
            body_upload: null,
            body: article.body || '',
            tags: article.tags || '',
            topics: article.topics || '',
            is_published: !!article.is_published,
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
                        <Field label="عنوان" error={form.errors.title}>
                            <input value={form.data.title} onChange={e => form.setData('title', e.target.value)} required />
                        </Field>
                        <Field label="Slug انگلیسی" error={form.errors.slug}>
                            <input dir="ltr" value={form.data.slug} onChange={e => form.setData('slug', e.target.value)} placeholder="gold-buying-guide" />
                        </Field>
                        <UploadField
                            label="تصویر بندانگشتی"
                            urlValue={form.data.thumbnail_image}
                            uploadError={form.errors.thumbnail_upload}
                            urlError={form.errors.thumbnail_image}
                            onUrlChange={value => form.setData('thumbnail_image', value)}
                            onFileChange={file => form.setData('thumbnail_upload', file)}
                        />
                        <UploadField
                            label="تصویر داخل متن"
                            urlValue={form.data.body_image}
                            uploadError={form.errors.body_upload}
                            urlError={form.errors.body_image}
                            onUrlChange={value => form.setData('body_image', value)}
                            onFileChange={file => form.setData('body_upload', file)}
                        />
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(260px,1fr))', gap: 12, marginTop: 12 }}>
                        <PickListField
                            label="موضوعات"
                            value={form.data.topics}
                            options={topicOptions}
                            error={form.errors.topics}
                            placeholder="طلا، آموزش خرید"
                            onChange={value => form.setData('topics', value)}
                        />
                        <PickListField
                            label="تگ‌ها"
                            value={form.data.tags}
                            options={tagOptions}
                            error={form.errors.tags}
                            placeholder="سکه، نقره، عیار"
                            onChange={value => form.setData('tags', value)}
                        />
                    </div>

                    <Field label="خلاصه" error={form.errors.summary}>
                        <textarea value={form.data.summary} onChange={e => form.setData('summary', e.target.value)} rows={2} />
                    </Field>
                    <Field label="متن مقاله" error={form.errors.body}>
                        <RichTextEditor value={form.data.body} onChange={value => form.setData('body', value)} uploadUrl="/admin/articles/embedded-image" />
                    </Field>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '12px 0' }}>
                        <input type="checkbox" checked={form.data.is_published} onChange={e => form.setData('is_published', e.target.checked)} />
                        منتشر شود
                    </label>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button className="btn" type="submit" disabled={form.processing} style={{ width: 'auto', padding: '10px 28px' }}>
                            {editing ? 'ذخیره ویرایش' : 'ثبت مقاله'}
                        </button>
                        {editing && <button type="button" className="btn-sm" onClick={reset}>لغو</button>}
                    </div>
                </form>

                <div className="table-wrap">
                    <table>
                        <thead><tr><th>عنوان</th><th>Slug</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
                        <tbody>
                            {articles.map(article => (
                                <tr key={article.id}>
                                    <td><strong>{article.title}</strong><div style={{ color: 'var(--muted)', fontSize: 12 }}>{article.summary}</div></td>
                                    <td dir="ltr" style={{ fontSize: 12 }}>{article.slug}</td>
                                    <td><span className={`badge ${article.is_published ? 'buy-b' : 'silver'}`}>{article.is_published ? 'منتشر شده' : 'پیش‌نویس'}</span></td>
                                    <td style={{ color: 'var(--muted)', fontSize: 12 }}>{article.published_at || article.created_at}</td>
                                    <td>
                                        <div style={{ display: 'flex', gap: 6 }}>
                                            <button className="btn-sm" onClick={() => edit(article)}>ویرایش</button>
                                            <button className="btn-sm danger" onClick={() => destroy(article.id)}>حذف</button>
                                        </div>
                                    </td>
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

function UploadField({ label, urlValue, urlError, uploadError, onUrlChange, onFileChange }) {
    return (
        <Field label={label} error={urlError || uploadError}>
            <div style={{ display: 'grid', gap: 8 }}>
                <input dir="ltr" value={urlValue} onChange={e => onUrlChange(e.target.value)} placeholder="/storage/articles/image.jpg" />
                <input type="file" accept="image/png,image/jpeg,image/webp" onChange={e => onFileChange(e.target.files?.[0] || null)} />
                {urlValue && <img src={urlValue} alt="" style={{ width: '100%', maxHeight: 110, objectFit: 'cover', borderRadius: 8, border: '1px solid var(--line)' }} />}
            </div>
        </Field>
    );
}

function PickListField({ label, value, options, error, placeholder, onChange }) {
    const [draft, setDraft] = useState('');
    const selected = splitList(value);

    function add(item) {
        const clean = item.trim();
        if (!clean) return;
        onChange(joinList([...selected, clean]));
        setDraft('');
    }

    function remove(item) {
        onChange(joinList(selected.filter(x => x !== item)));
    }

    return (
        <Field label={label} error={error}>
            <div style={{ display: 'grid', gap: 8 }}>
                <input value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder} />
                <div style={{ display: 'flex', gap: 6 }}>
                    <input value={draft} onChange={e => setDraft(e.target.value)} placeholder="مورد جدید" />
                    <button type="button" className="btn-sm gold" onClick={() => add(draft)}>افزودن</button>
                </div>
                {selected.length > 0 && (
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                        {selected.map(item => (
                            <button key={item} type="button" className="tag-chip active" onClick={() => remove(item)}>{item} ×</button>
                        ))}
                    </div>
                )}
                {options.length > 0 && (
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                        {options.map(item => (
                            <button key={item} type="button" className={`tag-chip ${selected.includes(item) ? 'active' : ''}`} onClick={() => add(item)}>
                                {item}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </Field>
    );
}

function RichTextEditor({ value, onChange, uploadUrl }) {
    const editorRef = useRef(null);
    const fileInputRef = useRef(null);
    const selectionRef = useRef(null);
    const [uploadingImage, setUploadingImage] = useState(false);
    const [uploadError, setUploadError] = useState('');

    useEffect(() => {
        if (editorRef.current && editorRef.current.innerHTML !== value) {
            editorRef.current.innerHTML = value || '';
        }
    }, [value]);

    function rememberSelection() {
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0) return;

        selectionRef.current = selection.getRangeAt(0).cloneRange();
    }

    function restoreSelection() {
        const selection = window.getSelection();

        if (!selection || !selectionRef.current) return;

        selection.removeAllRanges();
        selection.addRange(selectionRef.current);
    }

    function run(command, argument = null) {
        editorRef.current?.focus();
        restoreSelection();
        document.execCommand(command, false, argument);
        rememberSelection();
        onChange(editorRef.current?.innerHTML || '');
    }

    function formatBlock(tag) {
        run('formatBlock', tag);
    }

    function addLink() {
        const url = prompt('آدرس لینک را وارد کنید');
        if (!url) return;
        run('createLink', url);
    }

    function openImagePicker() {
        rememberSelection();
        fileInputRef.current?.click();
    }

    async function uploadInlineImage(event) {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) return;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!csrf) {
            setUploadError('توکن امنیتی برای آپلود تصویر پیدا نشد.');
            return;
        }

        setUploadingImage(true);
        setUploadError('');

        const formData = new FormData();
        formData.append('image', file);

        try {
            const response = await fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                credentials: 'same-origin',
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok || !payload?.url) {
                const message = payload?.errors?.image?.[0] || payload?.message || 'آپلود تصویر ناموفق بود.';
                throw new Error(message);
            }

            const alt = String(payload.alt || '').replace(/"/g, '&quot;');
            run('insertHTML', `<img src="${payload.url}" alt="${alt}">`);
        } catch (error) {
            setUploadError(error.message || 'آپلود تصویر ناموفق بود.');
        } finally {
            setUploadingImage(false);
        }
    }

    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 12, overflow: 'hidden', background: 'rgba(255,255,255,.04)' }}>
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', padding: 8, borderBottom: '1px solid var(--line)', background: 'rgba(255,255,255,.03)' }}>
                <button type="button" className="btn-sm" onClick={() => run('bold')}>B</button>
                <button type="button" className="btn-sm" onClick={() => run('italic')}>I</button>
                <button type="button" className="btn-sm" onClick={() => run('underline')}>U</button>
                <button type="button" className="btn-sm" onClick={() => formatBlock('h2')}>تیتر ۲</button>
                <button type="button" className="btn-sm" onClick={() => formatBlock('h3')}>تیتر ۳</button>
                <button type="button" className="btn-sm" onClick={() => formatBlock('p')}>متن</button>
                <button type="button" className="btn-sm" onClick={() => run('insertUnorderedList')}>لیست</button>
                <button type="button" className="btn-sm" onClick={() => run('insertOrderedList')}>شماره‌دار</button>
                <button type="button" className="btn-sm" onClick={() => formatBlock('blockquote')}>نقل‌قول</button>
                <button type="button" className="btn-sm" onClick={addLink}>لینک</button>
                <button type="button" className="btn-sm gold" onClick={openImagePicker} disabled={uploadingImage}>
                    {uploadingImage ? 'در حال آپلود...' : 'عکس داخل متن'}
                </button>
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    onChange={uploadInlineImage}
                    hidden
                />
            </div>
            <div
                ref={editorRef}
                contentEditable
                suppressContentEditableWarning
                onInput={e => onChange(e.currentTarget.innerHTML)}
                onMouseUp={rememberSelection}
                onKeyUp={rememberSelection}
                onBlur={rememberSelection}
                style={{ minHeight: 280, padding: 14, outline: 'none', lineHeight: 2.1, color: 'var(--txt)' }}
            />
            {uploadError && <div className="alert err" style={{ margin: 10 }}>{uploadError}</div>}
        </div>
    );
}
