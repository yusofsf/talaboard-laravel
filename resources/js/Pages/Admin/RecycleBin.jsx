import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const Icon = ({ children, size = 20 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor"
        strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        {children}
    </svg>
);

const TrashIcon = () => <Icon><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13" /></Icon>;
const RestoreIcon = () => <Icon size={18}><path d="M4 10a8 8 0 1 1 2 7M4 4v6h6" /></Icon>;
const SearchIcon = () => <Icon size={18}><circle cx="11" cy="11" r="7" /><path d="m16 16 4 4" /></Icon>;

export default function RecycleBin({ items, types }) {
    const [query, setQuery] = useState('');
    const [type, setType] = useState('all');
    const [restoring, setRestoring] = useState(null);

    const filteredItems = useMemo(() => {
        const normalized = query.trim().toLocaleLowerCase('fa');
        return items.filter(item => {
            const matchesType = type === 'all' || item.type === type;
            const matchesQuery = !normalized || `${item.label} ${item.type_label} ${item.id}`
                .toLocaleLowerCase('fa').includes(normalized);
            return matchesType && matchesQuery;
        });
    }, [items, query, type]);

    function restore(item) {
        if (!confirm(`«${item.label}» بازگردانی شود؟`)) return;
        const key = `${item.type}-${item.id}`;
        setRestoring(key);
        router.post(`/admin/recycle-bin/${item.type}/${item.id}/restore`, {}, {
            preserveScroll: true,
            onFinish: () => setRestoring(null),
        });
    }

    return (
        <AppLayout title="حذف‌شده‌ها">
            <main className="admin-tool-page">
                <header className="admin-tool-hero recycle-hero">
                    <div className="admin-tool-hero-icon"><TrashIcon /></div>
                    <div>
                        <span className="admin-tool-eyebrow">بازیابی اطلاعات</span>
                        <h1>حذف‌شده‌ها</h1>
                        <p>رکوردهای حذف نرم هنوز در پایگاه داده هستند و می‌توانید آن‌ها را به بخش اصلی برگردانید.</p>
                    </div>
                    <div className="admin-tool-stat">
                        <strong>{items.length.toLocaleString('fa-IR')}</strong>
                        <span>مورد حذف‌شده</span>
                    </div>
                </header>

                <section className="admin-panel recycle-panel">
                    <div className="recycle-toolbar">
                        <label className="admin-search-field">
                            <SearchIcon />
                            <input type="search" value={query} onChange={event => setQuery(event.target.value)}
                                placeholder="جستجو با نام یا شماره رکورد…" aria-label="جستجو در حذف‌شده‌ها" />
                        </label>
                        <div className="type-filter" role="group" aria-label="فیلتر نوع رکورد">
                            <button type="button" className={type === 'all' ? 'active' : ''} onClick={() => setType('all')}>
                                همه <span>{items.length.toLocaleString('fa-IR')}</span>
                            </button>
                            {types.map(option => {
                                const count = items.filter(item => item.type === option.value).length;
                                if (!count) return null;
                                return (
                                    <button type="button" key={option.value} className={type === option.value ? 'active' : ''}
                                        onClick={() => setType(option.value)}>
                                        {option.label} <span>{count.toLocaleString('fa-IR')}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="recycle-list">
                        {filteredItems.map(item => {
                            const key = `${item.type}-${item.id}`;
                            return (
                                <article className="recycle-record-card" key={key}>
                                    <div className="recycle-record-icon"><TrashIcon /></div>
                                    <div className="recycle-record-main">
                                        <span className="record-type-badge">{item.type_label}</span>
                                        <h2>{item.label}</h2>
                                        <div className="record-meta">
                                            <span>شناسه <bdi>#{item.id}</bdi></span>
                                            <span>حذف در <bdi>{item.deleted_at}</bdi></span>
                                        </div>
                                    </div>
                                    <button type="button" className="admin-restore-button" disabled={restoring === key}
                                        onClick={() => restore(item)}>
                                        <RestoreIcon /> {restoring === key ? 'در حال بازگردانی…' : 'بازگردانی'}
                                    </button>
                                </article>
                            );
                        })}
                        {!filteredItems.length && (
                            <div className="admin-empty-state">
                                <TrashIcon />
                                <strong>{items.length ? 'موردی با این فیلتر پیدا نشد' : 'سطل حذف‌شده‌ها خالی است'}</strong>
                                <span>{items.length ? 'فیلتر یا عبارت جستجو را تغییر دهید.' : 'هیچ رکورد قابل‌بازگردانی وجود ندارد.'}</span>
                            </div>
                        )}
                    </div>
                </section>
            </main>
        </AppLayout>
    );
}
