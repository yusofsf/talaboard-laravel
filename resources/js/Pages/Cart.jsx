import { Link, router } from '@inertiajs/react';
import AppLayout, { faNum } from '../Layouts/AppLayout';

export default function Cart({ items, totalBuy, totalSell, walletBalance }) {
    function checkout() {
        if (!confirm('سفارش‌های داخل سبد خرید ثبت نهایی شوند؟')) return;
        router.post('/cart/checkout', {}, { preserveScroll: true });
    }

    function remove(id) {
        router.delete(`/cart/${id}`, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <main className="page-wide">
                <div className="fcard">
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap', marginBottom: 18 }}>
                        <div>
                            <h1 style={{ fontSize: 24, margin: 0 }}>سبد خرید</h1>
                            <div style={{ color: 'var(--muted)', fontSize: 13, marginTop: 6 }}>سفارش‌های فروشگاه اینجا جمع می‌شوند؛ سفارش‌های اتاق معاملاتی جدا ثبت می‌شوند.</div>
                        </div>
                        <a href="/" className="btn-sm">ادامه خرید</a>
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 12, marginBottom: 18 }}>
                        <Summary label="موجودی کیف پول" value={walletBalance} />
                        <Summary label="جمع خریدها" value={totalBuy} tone="up" />
                        <Summary label="جمع فروش‌ها" value={totalSell} tone="down" />
                        <Summary label="خالص پرداخت" value={Math.max(0, totalBuy - totalSell)} tone="gold" />
                    </div>

                    {items.length ? (
                        <>
                            <div className="table-wrap">
                                <table>
                                    <thead><tr><th>کالا</th><th>نوع</th><th>مقدار</th><th>قیمت واحد</th><th>مبلغ</th><th>تاریخ</th><th></th></tr></thead>
                                    <tbody>
                                        {items.map(i => (
                                            <tr key={i.id}>
                                                <td><strong>{i.item_label}</strong></td>
                                                <td><span className={`badge ${i.trade_type === 'buy' ? 'buy-b' : 'sell-b'}`}>{i.trade_type === 'buy' ? 'خرید' : 'فروش'}</span></td>
                                                <td className="num">{i.quantity}</td>
                                                <td className="num">{faNum(i.price_per_unit)}</td>
                                                <td className="num" style={{ color: 'var(--gold-1)', fontWeight: 800 }}>{faNum(i.total)}</td>
                                                <td style={{ color: 'var(--muted)', fontSize: 12 }}>{i.created_at}</td>
                                                <td><button className="btn-sm danger" type="button" onClick={() => remove(i.id)}>حذف</button></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {totalBuy > walletBalance && (
                                <div className="alert err" style={{ marginTop: 14 }}>
                                    موجودی کیف پول شما از جمع خریدها کمتر است. قبل از ثبت نهایی، کیف پول را شارژ کنید یا بخشی از خریدها را حذف کنید.
                                </div>
                            )}

                            <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: 18 }}>
                                {totalBuy > walletBalance && (
                                    <Link href="/wallet" className="btn-outline btn" style={{ width: 'auto', padding: '11px 22px' }}>
                                        شارژ کیف پول
                                    </Link>
                                )}
                                <button className="btn" type="button" onClick={checkout} style={{ width: 'auto', padding: '11px 28px' }}>
                                    ثبت نهایی سبد خرید
                                </button>
                            </div>
                        </>
                    ) : (
                        <div className="empty"><div className="ico">🛒</div>سبد خرید شما خالی است.</div>
                    )}
                </div>
            </main>
        </AppLayout>
    );
}

function Summary({ label, value, tone }) {
    const color = tone === 'up' ? 'var(--up)' : tone === 'down' ? 'var(--down)' : tone === 'gold' ? 'var(--gold-1)' : 'var(--txt)';
    return (
        <div style={{ background: 'rgba(255,255,255,.035)', border: '1px solid var(--line)', borderRadius: 12, padding: '14px 16px' }}>
            <div style={{ color: 'var(--muted)', fontSize: 12, marginBottom: 6 }}>{label}</div>
            <div style={{ color, fontSize: 20, fontWeight: 900 }}>{faNum(value)} <span style={{ fontSize: 12, fontWeight: 500 }}>تومان</span></div>
        </div>
    );
}
