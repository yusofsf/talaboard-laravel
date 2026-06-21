import { useMemo, useState } from 'react';
import AppLayout, { faNum } from '../Layouts/AppLayout';
import JalaliDatePicker from '../Components/JalaliDatePicker';

export default function History({ transactions, summary }) {
    const [tab, setTab] = useState('txn');
    const [filterDate, setFilterDate] = useState('');

    const filteredTxns = useMemo(
        () => filterDate ? transactions.filter(t => t.date_raw === filterDate) : transactions,
        [transactions, filterDate]
    );

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
                                <div style={{ minWidth: 280 }}>
                                    <label style={{ display: 'block', fontSize: 13, color: 'var(--muted)', marginBottom: 6, fontWeight: 600 }}>فیلتر بر اساس تاریخ (برای چاپ)</label>
                                    <JalaliDatePicker value={filterDate} onChange={setFilterDate} yearsBack={5} allowCurrentYear />
                                </div>
                                {filterDate && <button type="button" className="btn-sm" onClick={() => setFilterDate('')}>حذف فیلتر</button>}
                                <button type="button" className="btn-sm" onClick={() => window.print()} style={{ borderColor: 'rgba(246,207,99,.4)', color: 'var(--gold-1)', background: 'rgba(246,207,99,.08)' }}>
                                    🖨️ چاپ / خروجی PDF
                                </button>
                            </div>

                            {filteredTxns.length ? (
                                <div className="table-wrap print-area">
                                    <div className="print-only" style={{ marginBottom: 14, fontWeight: 800, fontSize: 16 }}>
                                        تاریخچه‌ی معاملات{filterDate && ` — ${filteredTxns[0]?.created_at?.split(' ')[0] || ''}`}
                                    </div>
                                    <table>
                                        <thead><tr>
                                            <th>#</th><th>کالا</th><th>نوع</th><th>مقدار</th>
                                            <th>قیمت واحد</th><th>مبلغ کل</th><th>تاریخ</th>
                                        </tr></thead>
                                        <tbody>
                                            {filteredTxns.map((t, i) => (
                                                <tr key={t.id}>
                                                    <td className="num" style={{ color: 'var(--muted)' }}>{i + 1}</td>
                                                    <td>{t.item_label}</td>
                                                    <td><span className={`badge ${t.type === 'buy' ? 'buy-b' : 'sell-b'}`}>{t.type === 'buy' ? 'خرید' : 'فروش'}</span></td>
                                                    <td className="num">{t.quantity}</td>
                                                    <td className="num">{faNum(t.price_per_unit)}</td>
                                                    <td className="num" style={{ color: 'var(--gold-1)', fontWeight: 700 }}>{faNum(t.total)}</td>
                                                    <td style={{ color: 'var(--muted)', fontSize: 12 }}>{t.created_at}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="empty"><div className="ico">—</div><div>{filterDate ? 'معامله‌ای در این تاریخ ثبت نشده.' : 'هنوز معامله‌ای ثبت نشده.'}</div></div>
                            )}
                        </>
                    )}

                    {tab === 'acc' && (
                        summary.length ? (
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
                        )
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
