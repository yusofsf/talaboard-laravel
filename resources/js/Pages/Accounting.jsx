import { useMemo, useState } from 'react';
import AppLayout, { faNum } from '../Layouts/AppLayout';
import DateRangeFilter, { filterByDateRange } from '../Components/DateRangeFilter';
import Pager, { usePager } from '../Components/Pager';

const ledgerTypeLabel = type => ({
    deposit: 'واریز',
    withdraw: 'برداشت',
    purchase: 'خرید از فروشگاه',
    sale: 'فروش به فروشگاه',
    p2p_buy: 'خرید از اتاق معاملاتی',
    p2p_sell: 'فروش در اتاق معاملاتی',
    offer_escrow: 'رزرو سفارش اتاق معاملاتی',
    offer_refund: 'بازگشت رزرو سفارش',
    delivery: 'درخواست تحویل فیزیکی',
    delivery_refund: 'بازگشت درخواست تحویل',
    inventory_increase: 'افزایش موجودی انبار',
    admin_adjust: 'اصلاح توسط مدیریت',
    trade_reject: 'برگشت معامله',
})[type] || type;

function LedgerTable({ title, rows, kind }) {
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const filtered = useMemo(() => filterByDateRange(rows, from, to), [rows, from, to]);
    const pager = usePager(filtered, `${from}|${to}`);

    return (
        <section style={{ marginTop: 30 }}>
            <h3 className="section-title">{title}</h3>
            <div className="no-print" style={{ display: 'flex', justifyContent: 'center', gap: 12, flexWrap: 'wrap', marginBottom: 14 }}>
                <DateRangeFilter from={from} to={to} onFromChange={setFrom} onToChange={setTo} />
                {(from || to) && <button type="button" className="btn-sm" onClick={() => { setFrom(''); setTo(''); }}>حذف فیلتر</button>}
            </div>
            {filtered.length ? <>
                <div className="table-wrap">
                    <table>
                        <thead><tr>{kind === 'cash'
                            ? <><th>تاریخ</th><th>نوع</th><th>مبلغ</th><th>شرح</th></>
                            : <><th>تاریخ</th><th>دارایی</th><th>نوع</th><th>تغییر وزن</th><th>شرح</th></>
                        }</tr></thead>
                        <tbody>{pager.pageItems.map(row => <LedgerRow key={row.id} row={row} kind={kind} />)}</tbody>
                    </table>
                </div>
                <Pager page={pager.page} totalPages={pager.totalPages} onChange={pager.setPage} />
                <div className="table-wrap print-area print-only-block">
                    <div className="print-only" style={{ fontWeight: 800, marginBottom: 8 }}>{title}</div>
                    <table>
                        <thead><tr>{kind === 'cash'
                            ? <><th>تاریخ</th><th>نوع</th><th>مبلغ</th><th>شرح</th></>
                            : <><th>تاریخ</th><th>دارایی</th><th>نوع</th><th>تغییر وزن</th><th>شرح</th></>
                        }</tr></thead>
                        <tbody>{filtered.map(row => <LedgerRow key={row.id} row={row} kind={kind} />)}</tbody>
                    </table>
                </div>
            </> : <div className="empty">گردشی در این بازه ثبت نشده است.</div>}
        </section>
    );
}

function LedgerRow({ row, kind }) {
    const amount = kind === 'cash' ? row.amount : row.grams;
    const positive = amount >= 0;
    return <tr>
        <td style={{ color: 'var(--muted)', fontSize: 12 }}>{row.created_at}</td>
        {kind !== 'cash' && <td>{row.asset}</td>}
        <td>{ledgerTypeLabel(row.type)}</td>
        <td className="num" style={{ color: positive ? 'var(--up)' : 'var(--down)', fontWeight: 800 }}>{positive ? '+' : ''}{faNum(amount)}{kind === 'cash' ? ' تومان' : ' گرم'}</td>
        <td style={{ whiteSpace: 'normal', minWidth: 180 }}>{row.description || '—'}</td>
    </tr>;
}

export default function Accounting({ balances, cashTransactions, assetTransactions, tradeSummary }) {
    return <AppLayout>
        <main className="page-wide accounting-page">
            <h2 style={{ fontSize: 24, fontWeight: 800 }}>حسابداری من</h2>
            <p style={{ color: 'var(--muted)', marginTop: 6 }}>مانده‌ها و گردش‌های ثبت‌شده در دفترکل حساب شما</p>
            <div className="accounting-summary">
                <div><small>مانده کیف پول</small><strong style={{ color: 'var(--up)' }}>{faNum(balances.cash)} تومان</strong></div>
                <div><small>مانده طلا</small><strong style={{ color: 'var(--gold-1)' }}>{faNum(balances.gold)} گرم</strong></div>
                <div><small>مانده نقره ۹۹۹</small><strong>{faNum(balances.silver_999)} گرم</strong></div>
                <div><small>مانده نقره ۹۹۵</small><strong>{faNum(balances.silver_995)} گرم</strong></div>
            </div>
            <section style={{ marginTop: 30 }}>
                <h3 className="section-title">خلاصه معاملات من</h3>
                {tradeSummary.length ? <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14 }}>
                    {tradeSummary.map(summary => <div key={summary.label} style={{ flex: '1', minWidth: 200, background: 'rgba(255,255,255,.03)', border: '1px solid var(--line)', borderRadius: 14, padding: '16px 18px' }}>
                        <div style={{ fontWeight: 700, marginBottom: 10, paddingBottom: 8, borderBottom: '1px solid var(--line)' }}>{summary.label}</div>
                        {[
                            ['وزن خرید', summary.buy_qty, 'var(--up)'],
                            ['وزن فروش', summary.sell_qty, 'var(--down)'],
                            ['مانده وزن', summary.weight_balance, summary.weight_balance > 0 ? 'var(--up)' : summary.weight_balance < 0 ? 'var(--down)' : 'var(--muted)'],
                        ].map(([label, value, color]) => <div key={label} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 5 }}><span style={{ color: 'var(--muted)' }}>{label}</span><span style={{ fontWeight: 700, color }}>{faNum(value)}</span></div>)}
                        <hr style={{ border: 'none', borderTop: '1px solid var(--line)', margin: '8px 0' }} />
                        {[
                            ['پرداخت خرید', summary.buy_total, 'var(--up)'],
                            ['دریافت فروش', summary.sell_total, 'var(--down)'],
                            ['مانده پول', summary.money_balance, summary.money_balance > 0 ? 'var(--down)' : summary.money_balance < 0 ? 'var(--up)' : 'var(--muted)'],
                        ].map(([label, value, color]) => <div key={label} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 5 }}><span style={{ color: 'var(--muted)' }}>{label}</span><span style={{ fontWeight: 700, color }}>{faNum(value)} <span style={{ fontSize: 10 }}>ت</span></span></div>)}
                    </div>)}
                </div> : <div className="empty">هنوز داده‌ای برای محاسبه وجود ندارد.</div>}
            </section>
            <div className="no-print"><button type="button" className="btn-sm gold" onClick={() => window.print()}>چاپ / خروجی PDF</button></div>
            <LedgerTable title="گردش کیف پول" rows={cashTransactions} kind="cash" />
            <LedgerTable title="گردش طلا و نقره" rows={assetTransactions} kind="asset" />
        </main>
    </AppLayout>;
}
