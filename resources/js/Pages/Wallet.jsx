import AppLayout, { faNum } from '../Layouts/AppLayout';

export default function Wallet({ balance, txns }) {
    return (
        <AppLayout>
            <div className="page-wide">
                <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 20 }}>کیف پول</h2>

                <div style={{
                    background: 'linear-gradient(135deg,var(--gold-1),var(--gold-2))',
                    borderRadius: 22, padding: '32px 28px', marginBottom: 28,
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16,
                }}>
                    <div>
                        <div style={{ fontSize: 13, fontWeight: 700, color: '#5a3a00', opacity: .8 }}>موجودی فعلی</div>
                        <div style={{ fontSize: 38, fontWeight: 900, color: '#1a1200', lineHeight: 1 }}>{faNum(balance)}</div>
                        <div style={{ fontSize: 15, fontWeight: 700, color: '#5a3a00', marginTop: 6 }}>تومان</div>
                    </div>
                    <div style={{ fontSize: 52, opacity: .35 }}>💰</div>
                </div>

                <div style={{ fontSize: 16, fontWeight: 800, marginBottom: 14, paddingBottom: 10, borderBottom: '1px solid var(--line)' }}>
                    تاریخچه تراکنش‌ها
                </div>

                {txns.length ? (
                    <div className="table-wrap">
                        <table>
                            <thead><tr><th>تاریخ</th><th>شرح</th><th>نوع</th><th>مبلغ (تومان)</th></tr></thead>
                            <tbody>
                                {txns.map(t => (
                                    <tr key={t.id}>
                                        <td style={{ fontSize: 12, color: 'var(--muted)' }}>{t.created_at}</td>
                                        <td>{t.description || '—'}</td>
                                        <td><span className={`badge ${t.amount > 0 ? 'buy-b' : 'sell-b'}`}>{t.amount > 0 ? 'واریز' : 'برداشت'}</span></td>
                                        <td className="num" style={{ color: t.amount > 0 ? 'var(--up)' : 'var(--down)', fontWeight: 700 }}>
                                            {t.amount > 0 ? '+' : ''}{faNum(t.amount)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="empty"><div className="ico">💸</div><div>هنوز تراکنشی ثبت نشده.</div></div>
                )}
            </div>
        </AppLayout>
    );
}
