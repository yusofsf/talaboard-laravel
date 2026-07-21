import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import AppLayout, { faNum } from '../../Layouts/AppLayout';
import DateRangeFilter, { filterByDateRange } from '../../Components/DateRangeFilter';
import Pager, { usePager } from '../../Components/Pager';
import SearchBox, { filterBySearch } from '../../Components/SearchBox';

const ledgerTypeLabel = type => ({ deposit: 'واریز', withdraw: 'برداشت' })[type] || type;

export default function Accounting({ summary, walletTransactions, userBalances }) {
    const [transactionQuery, setTransactionQuery] = useState('');
    const [userQuery, setUserQuery] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const filteredTransactions = useMemo(() => filterBySearch(filterByDateRange(walletTransactions, from, to), transactionQuery, ['user_name', 'user_phone', 'type', 'description']), [walletTransactions, from, to, transactionQuery]);
    const filteredUsers = useMemo(() => filterBySearch(userBalances, userQuery, ['name', 'phone']), [userBalances, userQuery]);
    const transactionPager = usePager(filteredTransactions, `${from}|${to}|${transactionQuery}`);
    const userPager = usePager(filteredUsers, userQuery);

    return <AppLayout>
        <main className="page-wide accounting-page">
            <div className="no-print" style={{ marginBottom: 16 }}><Link href="/admin" className="btn-sm">بازگشت به پنل مدیریت</Link></div>
            <h2 style={{ fontSize: 24, fontWeight: 800 }}>حسابداری مدیریت</h2>
            <p style={{ color: 'var(--muted)', marginTop: 6 }}>نمای تجمیعی دفترکل‌ها، معاملات فعال و درخواست‌های در انتظار</p>

            <div className="accounting-summary">
                <div><small>مانده کیف پول کاربران</small><strong style={{ color: 'var(--up)' }}>{faNum(summary.cash_balance)} تومان</strong></div>
                <div><small>مانده طلای کاربران</small><strong style={{ color: 'var(--gold-1)' }}>{faNum(summary.gold_balance)} گرم</strong></div>
                <div><small>مانده نقره ۹۹۹ کاربران</small><strong>{faNum(summary.silver_999_balance)} گرم</strong></div>
                <div><small>مانده نقره ۹۹۵ کاربران</small><strong>{faNum(summary.silver_995_balance)} گرم</strong></div>
                <div><small>خرید فعال فروشگاه</small><strong>{faNum(summary.active_buy_total)} تومان</strong></div>
                <div><small>فروش فعال فروشگاه</small><strong>{faNum(summary.active_sell_total)} تومان</strong></div>
                <div><small>واریزهای در انتظار</small><strong>{faNum(summary.pending_deposits)} تومان</strong></div>
                <div><small>برداشت‌های در انتظار</small><strong>{faNum(summary.pending_withdrawals)} تومان</strong></div>
            </div>

            <div className="no-print" style={{ marginBottom: 20 }}><button type="button" className="btn-sm gold" onClick={() => window.print()}>چاپ / خروجی PDF</button></div>

            <section>
                <h3 className="section-title">گردش‌های کیف پول</h3>
                <div className="no-print" style={{ display: 'flex', justifyContent: 'center', gap: 12, flexWrap: 'wrap', marginBottom: 14 }}>
                    <SearchBox value={transactionQuery} onChange={setTransactionQuery} placeholder="جستجو در کاربر، موبایل یا شرح..." />
                    <DateRangeFilter from={from} to={to} onFromChange={setFrom} onToChange={setTo} />
                </div>
                <AccountingTable rows={transactionPager.pageItems} />
                <Pager page={transactionPager.page} totalPages={transactionPager.totalPages} onChange={transactionPager.setPage} />
                <div className="table-wrap print-area print-only-block"><div className="print-only" style={{ fontWeight: 800, marginBottom: 8 }}>گردش‌های کیف پول</div><AccountingTable rows={filteredTransactions} /></div>
            </section>

            <section style={{ marginTop: 30 }}>
                <h3 className="section-title">مانده کاربران</h3>
                <div className="no-print" style={{ display: 'flex', justifyContent: 'center', marginBottom: 14 }}><SearchBox value={userQuery} onChange={setUserQuery} placeholder="جستجو در نام یا موبایل..." /></div>
                <UserBalanceTable rows={userPager.pageItems} />
                <Pager page={userPager.page} totalPages={userPager.totalPages} onChange={userPager.setPage} />
                <div className="table-wrap print-area print-only-block"><div className="print-only" style={{ fontWeight: 800, marginBottom: 8 }}>مانده کاربران</div><UserBalanceTable rows={filteredUsers} /></div>
            </section>
        </main>
    </AppLayout>;
}

function AccountingTable({ rows }) {
    return <div className="table-wrap"><table><thead><tr><th>تاریخ</th><th>کاربر</th><th>موبایل</th><th>نوع</th><th>مبلغ</th><th>شرح</th></tr></thead><tbody>{rows.length ? rows.map(row => <tr key={row.id}><td style={{ color: 'var(--muted)', fontSize: 12 }}>{row.created_at}</td><td>{row.user_name || '—'}</td><td dir="ltr">{row.user_phone || '—'}</td><td>{ledgerTypeLabel(row.type)}</td><td className="num" style={{ color: row.amount >= 0 ? 'var(--up)' : 'var(--down)', fontWeight: 800 }}>{row.amount >= 0 ? '+' : ''}{faNum(row.amount)}</td><td style={{ whiteSpace: 'normal', minWidth: 180 }}>{row.description || '—'}</td></tr>) : <tr><td colSpan={6}>رکوردی یافت نشد.</td></tr>}</tbody></table></div>;
}

function UserBalanceTable({ rows }) {
    return <div className="table-wrap"><table><thead><tr><th>کاربر</th><th>موبایل</th><th>کیف پول</th><th>طلا</th><th>نقره ۹۹۹</th><th>نقره ۹۹۵</th></tr></thead><tbody>{rows.length ? rows.map(row => <tr key={row.id}><td>{row.name}</td><td dir="ltr">{row.phone}</td><td className="num">{faNum(row.cash)} تومان</td><td className="num">{faNum(row.gold)} گرم</td><td className="num">{faNum(row.silver_999)} گرم</td><td className="num">{faNum(row.silver_995)} گرم</td></tr>) : <tr><td colSpan={6}>کاربری یافت نشد.</td></tr>}</tbody></table></div>;
}
