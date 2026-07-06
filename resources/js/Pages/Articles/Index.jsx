import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ articles }) {
    return (
        <AppLayout>
            <main className="page">
                <h1 style={{ fontSize: 26, marginBottom: 8 }}>مقالات</h1>
                <p style={{ color: 'var(--muted)', marginBottom: 24 }}>آموزش‌ها و تحلیل‌های کاربردی درباره طلا، نقره، سکه و بازار فلزات گران‌بها.</p>

                {articles.length ? (
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(260px,1fr))', gap: 18 }}>
                        {articles.map(a => (
                            <article key={a.id} className="fcard" style={{ overflow: 'hidden', padding: 0 }}>
                                {a.thumbnail_image && <Link href={`/articles/${a.slug}`}><img src={a.thumbnail_image} alt={a.title} style={{ width: '100%', aspectRatio: '16/9', objectFit: 'cover', display: 'block' }} /></Link>}
                                <div style={{ padding: 18 }}>
                                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 10 }}>
                                        {(a.topics || []).map(t => <span key={t} className="badge gold">{t}</span>)}
                                    </div>
                                    <h2 style={{ fontSize: 18, margin: '0 0 10px' }}>
                                        <Link href={`/articles/${a.slug}`} style={{ color: 'var(--txt)', textDecoration: 'none' }}>{a.title}</Link>
                                    </h2>
                                    {a.summary && <p style={{ color: 'var(--muted)', lineHeight: 1.9, fontSize: 14 }}>{a.summary}</p>}
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10, marginTop: 14 }}>
                                        <span style={{ color: 'var(--muted)', fontSize: 12 }}>{a.published_at || a.created_at}</span>
                                        <Link href={`/articles/${a.slug}`} className="btn-sm gold">مطالعه</Link>
                                    </div>
                                    {(a.tags || []).length > 0 && (
                                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 14 }}>
                                            {a.tags.map(t => <span key={t} style={{ color: 'var(--muted)', fontSize: 12 }}>#{t}</span>)}
                                        </div>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                ) : (
                    <div className="empty"><div className="ico">📝</div>هنوز مقاله‌ای منتشر نشده است.</div>
                )}
            </main>
        </AppLayout>
    );
}
