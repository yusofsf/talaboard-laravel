import { useMemo, useState } from 'react';
import AppLayout, { faNum } from '../Layouts/AppLayout';
import DateRangeFilter, { filterByDateRange } from '../Components/DateRangeFilter';
import Pager, { usePager } from '../Components/Pager';
import TradeSourceBadge from '../Components/TradeSourceBadge';

function TxnRow({ t, i }) {
    const rejected = t.status === 'rejected';
    return (
        <tr style={rejected ? { opacity: .6 } : undefined}>
            <td className="num" style={{ color: 'var(--muted)' }}>{i + 1}</td>
            <td>
                <span style={rejected ? { textDecoration: 'line-through' } : undefined}>{t.item_label}</span>
                {t.source === 'room' && <span className="badge" style={{ marginInlineStart: 6 }}>اتاق معاملاتی</span>}
                <TradeSourceBadge isFromBot={t.is_from_bot} />
                {rejected && (
                    <>
                        <span className="badge sell-b" style={{ marginInlineStart: 6 }}>رد شد</span>
                        {t.admin_note && <div style={{ fontSize: 11, color: 'var(--down)', marginTop: 4 }}>دلیل: {t.admin_note}</div>}
                    </>
                )}
            </td>
            <td><span className={`badge ${t.type === 'buy' ? 'buy-b' : 'sell-b'}`}>{t.type === 'buy' ? 'خرید' : 'فروش'}</span></td>
            <td className="num">{t.quantity}</td>
            <td className="num">{faNum(t.price_per_unit)}</td>
            <td className="num" style={{ color: 'var(--gold-1)', fontWeight: 700, ...(rejected ? { textDecoration: 'line-through' } : {}) }}>{faNum(t.total)}</td>
            <td style={{ color: 'var(--muted)', fontSize: 12 }}>{t.created_at}</td>
        </tr>
    );
}

export default function History({ transactions }) {
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');

    const filteredTxns = useMemo(
        () => filterByDateRange(transactions, from, to),
        [transactions, from, to]
    );
    const pager = usePager(filteredTxns, `${from}|${to}`);

    return (
        <AppLayout>
            <div className="page-wide">
                <div className="fcard">
                    <h2>سوابق</h2>
                    <div style={{ height: 20 }} />
                            <div className="no-print" style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap', marginBottom: 18 }}>
                                <DateRangeFilter from={from} to={to} onFromChange={setFrom} onToChange={setTo} />
                                {(from || to) && <button type="button" className="btn-sm" onClick={() => { setFrom(''); setTo(''); }}>حذف فیلتر</button>}
                                <button type="button" className="btn-sm" onClick={() => window.print()} style={{ borderColor: 'rgba(246,207,99,.4)', color: 'var(--gold-1)', background: 'rgba(246,207,99,.08)' }}>
                                    🖨️ چاپ / خروجی PDF
                                </button>
                            </div>

                            {filteredTxns.length ? (
                                <>
                                    {/* نسخه‌ی صفحه‌بندی‌شده — فقط روی صفحه دیده می‌شود */}
                                    <div className="table-wrap">
                                        <table>
                                            <thead><tr>
                                                <th>#</th><th>کالا</th><th>نوع</th><th>مقدار</th>
                                                <th>قیمت واحد</th><th>مبلغ کل</th><th>تاریخ</th>
                                            </tr></thead>
                                            <tbody>
                                                {pager.pageItems.map((t, i) => <TxnRow key={t.id} t={t} i={(pager.page - 1) * 10 + i} />)}
                                            </tbody>
                                        </table>
                                    </div>
                                    <Pager page={pager.page} totalPages={pager.totalPages} onChange={pager.setPage} />

                                    {/* نسخه‌ی کامل (همه‌ی ردیف‌های فیلترشده) — فقط هنگام چاپ نشان داده می‌شود */}
                                    <div className="table-wrap print-area print-only-block">
                                        <div className="print-only" style={{ marginBottom: 14, fontWeight: 800, fontSize: 16 }}>
                                            تاریخچه‌ی معاملات{(from || to) && ` — از ${from || '...'} تا ${to || '...'}`}
                                        </div>
                                        <table>
                                            <thead><tr>
                                                <th>#</th><th>کالا</th><th>نوع</th><th>مقدار</th>
                                                <th>قیمت واحد</th><th>مبلغ کل</th><th>تاریخ</th>
                                            </tr></thead>
                                            <tbody>
                                                {filteredTxns.map((t, i) => <TxnRow key={t.id} t={t} i={i} />)}
                                            </tbody>
                                        </table>
                                    </div>
                                </>
                            ) : (
                                <div className="empty"><div className="ico">—</div><div>{(from || to) ? 'معامله‌ای در این بازه ثبت نشده.' : 'هنوز معامله‌ای ثبت نشده.'}</div></div>
                            )}
                </div>
            </div>
        </AppLayout>
    );
}
