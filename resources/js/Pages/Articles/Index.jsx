import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const taxonomySlug = value => String(value || '')
    .replace(/[يك]/g, char => char === 'ي' ? 'ی' : 'ک')
    .toLocaleLowerCase('fa')
    .trim()
    .replace(/[^\p{L}\p{N}]+/gu, '-')
    .replace(/^-+|-+$/g, '');
const taxonomyHref = (type, value) => `/articles/${type}/${encodeURIComponent(taxonomySlug(value))}`;
const topicHref = topic => taxonomyHref('topic', topic);
const tagHref = tag => taxonomyHref('tag', tag);

export default function Index({ articles, filters = {}, topics = [], tags = [] }) {
    const activeTopic = filters.topic || '';
    const activeTag = filters.tag || '';
    const isFiltered = activeTopic || activeTag;

    return (
        <AppLayout>
            <main className="page-wide" style={{ maxWidth: 1120 }}>
                <h1 style={{ fontSize: 26, marginBottom: 8 }}>
                    {activeTopic ? `مقالات موضوع ${activeTopic}` : activeTag ? `مقالات تگ ${activeTag}` : 'مقالات'}
                </h1>
                <p style={{ color: 'var(--muted)', marginBottom: 18 }}>
                    آموزش‌ها و تحلیل‌های کاربردی درباره طلا، نقره، سکه و بازار فلزات گران‌بها.
                </p>

                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 24 }}>
                    <Link href="/articles" className={`tag-chip ${!isFiltered ? 'active' : ''}`}>همه مقالات</Link>
                    {topics.map(topic => (
                        <Link key={topic} href={topicHref(topic)} className={`tag-chip ${activeTopic === topic ? 'active' : ''}`}>
                            {topic}
                        </Link>
                    ))}
                </div>

                {tags.length > 0 && (
                    <section aria-labelledby="article-tags-heading" style={{ marginBottom: 24 }}>
                        <h2 id="article-tags-heading" style={{ fontSize: 15, margin: '0 0 10px', color: 'var(--muted)' }}>برچسب‌های مقالات</h2>
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            {tags.map(tag => (
                                <Link key={tag} href={tagHref(tag)} className={`article-tag ${activeTag === tag ? 'active' : ''}`}>
                                    #{tag}
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {articles.length ? (
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(260px,1fr))', gap: 18 }}>
                        {articles.map(a => (
                            <article key={a.id} className="fcard" style={{ overflow: 'hidden', padding: 0 }}>
                                {a.thumbnail_image && (
                                    <Link href={`/articles/${a.slug}`}>
                                        <img src={a.thumbnail_image} alt={a.title} style={{ width: '100%', aspectRatio: '16/9', objectFit: 'cover', display: 'block' }} />
                                    </Link>
                                )}
                                <div style={{ padding: 18 }}>
                                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 10 }}>
                                        {(a.topics || []).map(topic => (
                                            <Link key={topic} href={topicHref(topic)} className="badge gold" style={{ textDecoration: 'none' }}>
                                                {topic}
                                            </Link>
                                        ))}
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
                                            {a.tags.map(tag => (
                                                <Link key={tag} href={tagHref(tag)} className="article-tag">
                                                    #{tag}
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                ) : (
                    <div className="empty"><div className="ico">📝</div>مقاله‌ای برای این فیلتر پیدا نشد.</div>
                )}
            </main>
        </AppLayout>
    );
}
