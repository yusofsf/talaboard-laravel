import { useEffect, useState } from 'react';
import { faNum } from '../Layouts/AppLayout';

export const PAGE_SIZE = 10;

/** صفحه‌بندی سمت کلاینت — هر صفحه ۱۰ ردیف. resetKey هر بار تغییر کند، به صفحه‌ی ۱ برمی‌گردد (مثلاً وقتی فیلتر عوض می‌شود). */
export function usePager(items, resetKey) {
    const [page, setPage] = useState(1);
    useEffect(() => { setPage(1); }, [resetKey]);

    const totalPages = Math.max(1, Math.ceil(items.length / PAGE_SIZE));
    const safePage = Math.min(page, totalPages);
    const pageItems = items.slice((safePage - 1) * PAGE_SIZE, safePage * PAGE_SIZE);

    return { page: safePage, setPage, totalPages, pageItems, totalCount: items.length };
}

export default function Pager({ page, totalPages, onChange }) {
    if (totalPages <= 1) return null;
    return (
        <div className="no-print" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 10, margin: '16px 0' }}>
            <button type="button" className="btn-sm" disabled={page <= 1} onClick={() => onChange(page - 1)}>قبلی</button>
            <span style={{ fontSize: 13, color: 'var(--muted)' }}>صفحه {faNum(page)} از {faNum(totalPages)}</span>
            <button type="button" className="btn-sm" disabled={page >= totalPages} onClick={() => onChange(page + 1)}>بعدی</button>
        </div>
    );
}
