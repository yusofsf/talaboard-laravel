import { useMemo, useState } from 'react';
import AppLayout, { faNum } from '../Layouts/AppLayout';
import DateRangeFilter, { filterByDateRange } from '../Components/DateRangeFilter';
import Pager, { usePager } from '../Components/Pager';

function TxnRow({ t, i }) {
    const rejected = t.status === 'rejected';
    return (
        <tr style={rejected ? { opacity: .6 } : undefined}>
            <td className="num" style={{ color: 'var(--muted)' }}>{i + 1}</td>
            <td>
                <span style={rejected ? { textDecoration: 'line-through' } : undefined}>{t.item_label}</span>
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

export default function History({ transactions, summary, wallet_balance }) {
    const [tab, setTab] = useState('txn');
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

                    <div className="tabs no-print">
                        <button className={`tab-btn${tab === 'txn' ? ' active' : ''}`} onClick={() => setTab('txn')}>معاملات</button>
                        <button className={`tab-btn${tab === 'acc' ? ' active' : ''}`} onClick={() => setTab('acc')}>حسابداری</button>
                    </div>

                    {tab === 'txn' && (
                        <>
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
                        </>
                    )}

                    {tab === 'acc' && (
                        <>
                            <div style={{
                                background: 'linear-gradient(135deg,var(--gold-1),var(--gold-2))',
                                borderRadius: 18, padding: '20px 24px', marginBottom: 18,
                                display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 14,
                            }}>
                                <div>
                                    <div style={{ fontSize: 12, fontWeight: 700, color: '#5a3a00', opacity: .8 }}>موجودی کیف پول</div>
                                    <div style={{ fontSize: 26, fontWeight: 900, color: '#1a1200' }}>{faNum(wallet_balance)} <span style={{ fontSize: 13, fontWeight: 700 }}>تومان</span></div>
                                </div>
                                <div style={{ fontSize: 32, opacity: .35 }}>💰</div>
                            </div>
                            {summary.length ? (
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14 }}>
                                {summary.map(s => (
                                    <div key={s.label} style={{
                                        flex: '1', minWidth: 200,
                                        background: 'rgba(255,255,255,.03)', border: '1px solid var(--line)',
                                        borderRadius: 14, padding: '16px 18px',
                                    }}>
                                        <div style={{ fontWeight: 700, marginBottom: 10, paddingBottom: 8, borderBottom: '1px solid var(--line)' }}>{s.label}</div>
                                        {[
                                            ['وزن خرید', s.buy_qty, 'var(--up)'],
                                            ['وزن فروش', s.sell_qty, 'var(--down)'],
                                            ['مانده وزن', s.weight_balance, s.weight_balance > 0 ? 'var(--up)' : s.weight_balance < 0 ? 'var(--down)' : 'var(--muted)'],
                                        ].map(([k, v, c]) => (
                                            <div key={k} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 5 }}>
                                                <span style={{ color: 'var(--muted)' }}>{k}</span>
                                                <span style={{ fontWeight: 700, color: c }}>{v}</span>
                                            </div>
                                        ))}
                                        <hr style={{ border: 'none', borderTop: '1px solid var(--line)', margin: '8px 0' }} />
                                        {[
                                            ['پرداخت خرید', s.buy_total, 'var(--up)'],
                                            ['دریافت فروش', s.sell_total, 'var(--down)'],
                                            ['مانده پول', s.money_balance, s.money_balance > 0 ? 'var(--down)' : s.money_balance < 0 ? 'var(--up)' : 'var(--muted)'],
                                        ].map(([k, v, c]) => (
                                            <div key={k} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 5 }}>
                                                <span style={{ color: 'var(--muted)' }}>{k}</span>
                                                <span style={{ fontWeight: 700, color: c }}>{faNum(v)} <span style={{ fontSize: 10 }}>ت</span></span>
                                            </div>
                                        ))}
                                    </div>
                                ))}
                            </div>
                            ) : (
                                <div className="empty"><div className="ico">📊</div><div>هنوز داده‌ای برای محاسبه وجود ندارد.</div></div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
