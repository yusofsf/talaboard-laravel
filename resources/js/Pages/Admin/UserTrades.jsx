import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import AppLayout, { faNum } from '../../Layouts/AppLayout';
import DateRangeFilter, { filterByDateRange } from '../../Components/DateRangeFilter';
import Pager, { usePager } from '../../Components/Pager';

function TradeRow({ t }) {
    const rejected = t.status === 'rejected';
    return (
        <tr style={rejected ? { opacity: .6 } : undefined}>
            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{t.created_at}</td>
            <td><span className={`badge ${t.source === 'فروشگاه' ? 'gold' : 'silver'}`}>{t.source}</span></td>
            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{t.role}</td>
            <td><span className={`badge ${t.side === 'buy' ? 'buy-b' : 'sell-b'}`}>{t.side === 'buy' ? 'خرید' : 'فروش'}</span></td>
            <td>
                <span style={rejected ? { textDecoration: 'line-through' } : undefined}>{t.item_label}</span>
                {rejected && <span className="badge sell-b" style={{ marginInlineStart: 6 }}>رد شد</span>}
                {rejected && t.admin_note && <div style={{ fontSize: 11, color: 'var(--down)', marginTop: 4 }}>دلیل: {t.admin_note}</div>}
            </td>
            <td className="num">{t.quantity}</td>
            <td className="num">{faNum(t.price)}</td>
            <td className="num" style={{ color: 'var(--gold-1)', fontWeight: 700 }}>{faNum(t.total)}</td>
        </tr>
    );
}

export default function UserTrades({ subject, trades }) {
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');

    const filtered = useMemo(
        () => filterByDateRange(trades, from, to),
        [trades, from, to]
    );
    const pager = usePager(filtered, `${from}|${to}`);

    const totals = useMemo(() => {
        let buy = 0, sell = 0;
        for (const t of filtered) {
            if (t.status === 'rejected') continue;
            if (t.side === 'buy') buy += t.total; else sell += t.total;
        }
        return { buy, sell };
    }, [filtered]);

    return (
        <AppLayout>
            <div className="page-wide">
                <div className="no-print" style={{ marginBottom: 16 }}>
                    <Link href="/admin" className="btn-sm">← بازگشت به پنل مدیریت</Link>
                </div>

                <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 6 }}>ریز معاملات کاربر</h2>
                <div style={{ color: 'var(--muted)', fontSize: 14, marginBottom: 20 }}>
                    <strong style={{ color: 'var(--txt)' }}>{subject.name}</strong>
                    <span dir="ltr" style={{ marginInlineStart: 10 }}>{subject.phone}</span>
                </div>

                {/* موجودی‌ها */}
                <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginBottom: 22 }}>
                    {[
                        ['کیف پول', faNum(subject.wallet_balance) + ' ت', 'var(--up)'],
                        ['طلا', faNum(subject.gold_balance) + ' گرم', 'var(--gold-1)'],
                        ['نقره ۹۹۹', faNum(subject.silver_balance['999']) + ' گرم', 'var(--silver-1)'],
                        ['نقره ۹۹۵', faNum(subject.silver_balance['995']) + ' گرم', 'var(--silver-1)'],
                    ].map(([label, val, color]) => (
                        <div key={label} style={{ flex: '1', minWidth: 150, background: 'linear-gradient(160deg,var(--card),var(--card-2))', border: '1px solid var(--line)', borderRadius: 14, padding: '14px 16px' }}>
                            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>{label}</div>
                            <div style={{ fontSize: 18, fontWeight: 800, color }}>{val}</div>
                        </div>
                    ))}
                </div>

                {/* فیلتر + چاپ */}
                <div className="no-print" style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap', marginBottom: 18 }}>
                    <DateRangeFilter from={from} to={to} onFromChange={setFrom} onToChange={setTo} />
                    {(from || to) && <button type="button" className="btn-sm" onClick={() => { setFrom(''); setTo(''); }}>حذف فیلتر</button>}
                    <button type="button" className="btn-sm gold" onClick={() => window.print()}>🖨️ چاپ / خروجی PDF</button>
                </div>

                {filtered.length ? (
                    <>
                        {/* نسخه‌ی صفحه‌بندی‌شده — فقط روی صفحه */}
                        <div className="table-wrap">
                            <table>
                                <thead><tr>
                                    <th>تاریخ</th><th>منبع</th><th>نقش</th><th>نوع</th><th>کالا</th><th>مقدار</th><th>قیمت واحد</th><th>مبلغ کل</th>
                                </tr></thead>
                                <tbody>
                                    {pager.pageItems.map(t => <TradeRow key={t.id} t={t} />)}
                                </tbody>
                            </table>
                        </div>
                        <Pager page={pager.page} totalPages={pager.totalPages} onChange={pager.setPage} />

                        {/* نسخه‌ی کامل — فقط هنگام چاپ */}
                        <div className="table-wrap print-area print-only-block">
                            <div className="print-only" style={{ marginBottom: 6, fontWeight: 800, fontSize: 16 }}>
                                ریز معاملات — {subject.name} ({subject.phone})
                            </div>
                            <table>
                                <thead><tr>
                                    <th>تاریخ</th><th>منبع</th><th>نقش</th><th>نوع</th><th>کالا</th><th>مقدار</th><th>قیمت واحد</th><th>مبلغ کل</th>
                                </tr></thead>
                                <tbody>
                                    {filtered.map(t => <TradeRow key={t.id} t={t} />)}
                                </tbody>
                                <tfoot>
                                    <tr style={{ borderTop: '2px solid var(--line)' }}>
                                        <td colSpan={7} style={{ fontWeight: 700 }}>جمع خرید (به‌جز رد‌شده‌ها)</td>
                                        <td className="num" style={{ color: 'var(--up)', fontWeight: 800 }}>{faNum(totals.buy)}</td>
                                    </tr>
                                    <tr>
                                        <td colSpan={7} style={{ fontWeight: 700 }}>جمع فروش (به‌جز رد‌شده‌ها)</td>
                                        <td className="num" style={{ color: 'var(--down)', fontWeight: 800 }}>{faNum(totals.sell)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {/* جمع‌ها روی صفحه هم نشان داده شود */}
                        <div className="no-print" style={{ display: 'flex', gap: 14, flexWrap: 'wrap', marginTop: 4 }}>
                            <div style={{ fontWeight: 700 }}>جمع خرید (به‌جز رد‌شده‌ها): <span style={{ color: 'var(--up)' }}>{faNum(totals.buy)}</span></div>
                            <div style={{ fontWeight: 700 }}>جمع فروش (به‌جز رد‌شده‌ها): <span style={{ color: 'var(--down)' }}>{faNum(totals.sell)}</span></div>
                        </div>
                    </>
                ) : (
                    <div className="empty"><div className="ico">📜</div>{(from || to) ? 'معامله‌ای در این بازه نیست.' : 'این کاربر هنوز معامله‌ای ندارد.'}</div>
                )}
            </div>
        </AppLayout>
    );
}
