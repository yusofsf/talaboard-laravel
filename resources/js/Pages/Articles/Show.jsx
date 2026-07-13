import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const paragraphs = text => String(text || '').split(/\n{2,}/).map(p => p.trim()).filter(Boolean);
const hasHtml = text => /<\/?[a-z][\s\S]*>/i.test(String(text || ''));
const taxonomySlug = value => String(value || '')
    .replace(/[يك]/g, char => char === 'ي' ? 'ی' : 'ک')
    .toLocaleLowerCase('fa')
    .trim()
    .replace(/[^\p{L}\p{N}]+/gu, '-')
    .replace(/^-+|-+$/g, '');
const taxonomyHref = (type, value) => `/articles/${type}/${encodeURIComponent(taxonomySlug(value))}`;
const topicHref = topic => taxonomyHref('topic', topic);
const tagHref = tag => taxonomyHref('tag', tag);

export default function Show({ article, relatedArticles = [] }) {
    return (
        <AppLayout>
            <main className="page" style={{ maxWidth: 860 }}>
                <Link href="/articles" className="btn-sm">بازگشت به مقالات</Link>
                <article style={{ marginTop: 18 }}>
                    <nav aria-label="مسیر صفحه" style={{ display: 'flex', gap: 8, color: 'var(--muted)', fontSize: 13, marginBottom: 14 }}>
                        <Link href="/">خانه</Link><span>/</span><Link href="/articles">مقالات</Link><span>/</span><span>{article.title}</span>
                    </nav>
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 12 }}>
                        {(article.topics || []).map(topic => (
                            <Link key={topic} href={topicHref(topic)} className="badge gold" style={{ textDecoration: 'none' }}>
                                {topic}
                            </Link>
                        ))}
                    </div>
                    <h1 style={{ fontSize: 30, lineHeight: 1.5, margin: '0 0 10px' }}>{article.title}</h1>
                    <div style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 18 }}>{article.published_at || article.created_at}</div>
                    {article.summary && <p style={{ color: 'var(--muted)', fontSize: 16, lineHeight: 2, marginBottom: 20 }}>{article.summary}</p>}
                    {article.thumbnail_image && <img src={article.thumbnail_image} alt={article.title} style={{ width: '100%', aspectRatio: '16/9', objectFit: 'cover', borderRadius: 10, border: '1px solid var(--line)', marginBottom: 24 }} />}

                    {hasHtml(article.body) ? (
                        <div>
                            {article.body_image && <img src={article.body_image} alt={`${article.title} - تصویر داخل متن`} style={{ width: '100%', borderRadius: 10, border: '1px solid var(--line)', margin: '10px 0 22px', objectFit: 'cover', maxHeight: 430 }} />}
                            <div className="article-body" dangerouslySetInnerHTML={{ __html: article.body }} />
                        </div>
                    ) : (
                        <div style={{ fontSize: 16, lineHeight: 2.25, color: 'var(--txt)' }}>
                            {paragraphs(article.body).map((p, i) => (
                                <div key={i}>
                                    {i === 1 && article.body_image && <img src={article.body_image} alt={`${article.title} - تصویر داخل متن`} style={{ width: '100%', borderRadius: 10, border: '1px solid var(--line)', margin: '10px 0 22px', objectFit: 'cover', maxHeight: 430 }} />}
                                    <p style={{ margin: '0 0 18px', textAlign: 'justify' }}>{p}</p>
                                </div>
                            ))}
                        </div>
                    )}

                    {(article.tags || []).length > 0 && (
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 28, borderTop: '1px solid var(--line)', paddingTop: 18 }}>
                            {article.tags.map(tag => (
                                <Link key={tag} href={tagHref(tag)} className="article-tag">
                                    #{tag}
                                </Link>
                            ))}
                        </div>
                    )}
                </article>

                {relatedArticles.length > 0 && (
                    <section aria-labelledby="related-articles-heading" style={{ marginTop: 42 }}>
                        <h2 id="related-articles-heading" style={{ fontSize: 22, marginBottom: 16 }}>مقالات مرتبط</h2>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 14 }}>
                            {relatedArticles.map(related => (
                                <article key={related.id} className="fcard">
                                    <h3 style={{ fontSize: 17, margin: '0 0 8px' }}>
                                        <Link href={`/articles/${related.slug}`} style={{ color: 'var(--txt)', textDecoration: 'none' }}>{related.title}</Link>
                                    </h3>
                                    {related.summary && <p style={{ color: 'var(--muted)', lineHeight: 1.8, fontSize: 13 }}>{related.summary}</p>}
                                    <Link href={`/articles/${related.slug}`} className="btn-sm gold">مطالعه مقاله</Link>
                                </article>
                            ))}
                        </div>
                    </section>
                )}
            </main>
        </AppLayout>
    );
}
