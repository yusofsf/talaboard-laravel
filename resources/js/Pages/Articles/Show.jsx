import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const paragraphs = text => String(text || '').split(/\n{2,}/).map(p => p.trim()).filter(Boolean);

export default function Show({ article }) {
    return (
        <AppLayout>
            <main className="page" style={{ maxWidth: 860 }}>
                <Link href="/articles" className="btn-sm">بازگشت به مقالات</Link>
                <article style={{ marginTop: 18 }}>
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 12 }}>
                        {(article.topics || []).map(t => <span key={t} className="badge gold">{t}</span>)}
                    </div>
                    <h1 style={{ fontSize: 30, lineHeight: 1.5, margin: '0 0 10px' }}>{article.title}</h1>
                    <div style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 18 }}>{article.published_at || article.created_at}</div>
                    {article.summary && <p style={{ color: 'var(--muted)', fontSize: 16, lineHeight: 2, marginBottom: 20 }}>{article.summary}</p>}
                    {article.thumbnail_image && <img src={article.thumbnail_image} alt={article.title} style={{ width: '100%', aspectRatio: '16/9', objectFit: 'cover', borderRadius: 10, border: '1px solid var(--line)', marginBottom: 24 }} />}

                    <div style={{ fontSize: 16, lineHeight: 2.25, color: 'var(--txt)' }}>
                        {paragraphs(article.body).map((p, i) => (
                            <div key={i}>
                                {i === 1 && article.body_image && <img src={article.body_image} alt={`${article.title} - تصویر داخل متن`} style={{ width: '100%', borderRadius: 10, border: '1px solid var(--line)', margin: '10px 0 22px', objectFit: 'cover', maxHeight: 430 }} />}
                                <p style={{ margin: '0 0 18px', textAlign: 'justify' }}>{p}</p>
                            </div>
                        ))}
                    </div>

                    {(article.tags || []).length > 0 && (
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 28, borderTop: '1px solid var(--line)', paddingTop: 18 }}>
                            {article.tags.map(t => <span key={t} style={{ color: 'var(--muted)', fontSize: 13 }}>#{t}</span>)}
                        </div>
                    )}
                </article>
            </main>
        </AppLayout>
    );
}
