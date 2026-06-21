import AppLayout, { faNum } from '../Layouts/AppLayout';

const TYPE_LABEL = {
    purchase: 'خرید از فروشگاه', sale: 'فروش به فروشگاه',
    p2p_buy: 'خرید (اتاق معاملاتی)', p2p_sell: 'فروش (اتاق معاملاتی)',
    offer_escrow: 'رزرو پیشنهاد', offer_refund: 'بازگشت رزرو',
    delivery: 'تحویل فیزیکی', delivery_refund: 'بازگشت از تحویل',
    admin_adjust: 'اصلاح توسط ادمین',
};

function HistoryTable({ rows, showPurity }) {
    if (!rows.length) {
        return <div className="empty" style={{ padding: '24px 0' }}><div className="ico">📦</div>هنوز تراکنشی ثبت نشده.</div>;
    }
    return (
        <div className="table-wrap">
            <table>
                <thead><tr>
                    <th>تاریخ</th>{showPurity && <th>عیار</th>}<th>نوع</th><th>گرم</th><th>توضیحات</th>
                </tr></thead>
                <tbody>
                    {rows.map(r => (
                        <tr key={r.id}>
                            <td style={{ fontSize: 12, color: 'var(--muted)' }}>{r.created_at}</td>
                            {showPurity && <td>{r.purity}</td>}
                            <td>{TYPE_LABEL[r.type] || r.type}</td>
                            <td className="num" style={{ color: r.grams > 0 ? 'var(--up)' : 'var(--down)', fontWeight: 700 }}>
                                {r.grams > 0 ? '+' : ''}{r.grams}
                            </td>
                            <td style={{ color: 'var(--muted)', fontSize: 13 }}>{r.description || '—'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Inventory({ goldBalance, silverBalance, goldHistory, silverHistory }) {
    return (
        <AppLayout>
            <div className="page-wide">
                <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 20 }}>📦 موجودی انبار</h2>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 14, marginBottom: 28 }}>
                    <div style={{ background: 'linear-gradient(135deg,rgba(246,207,99,.16),rgba(199,154,46,.08))', border: '1px solid rgba(246,207,99,.3)', borderRadius: 16, padding: '18px 20px' }}>
                        <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>موجودی طلا</div>
                        <div style={{ fontSize: 24, fontWeight: 800, color: 'var(--gold-1)' }}>{faNum(goldBalance)} <span style={{ fontSize: 13, fontWeight: 400 }}>گرم</span></div>
                    </div>
                    <div style={{ background: 'rgba(255,255,255,.04)', border: '1px solid var(--line)', borderRadius: 16, padding: '18px 20px' }}>
                        <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>موجودی نقره ۹۹۹/۹</div>
                        <div style={{ fontSize: 24, fontWeight: 800, color: 'var(--silver-1)' }}>{faNum(silverBalance['999'])} <span style={{ fontSize: 13, fontWeight: 400 }}>گرم</span></div>
                    </div>
                    <div style={{ background: 'rgba(255,255,255,.04)', border: '1px solid var(--line)', borderRadius: 16, padding: '18px 20px' }}>
                        <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>موجودی نقره ۹۹۵</div>
                        <div style={{ fontSize: 24, fontWeight: 800, color: 'var(--silver-1)' }}>{faNum(silverBalance['995'])} <span style={{ fontSize: 13, fontWeight: 400 }}>گرم</span></div>
                    </div>
                </div>

                <div className="section-title">تاریخچه‌ی طلا</div>
                <HistoryTable rows={goldHistory} showPurity={false} />

                <div className="section-title" style={{ marginTop: 28 }}>تاریخچه‌ی نقره</div>
                <HistoryTable rows={silverHistory} showPurity />
            </div>
        </AppLayout>
    );
}
