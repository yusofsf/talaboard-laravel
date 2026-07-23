import { useEffect, useMemo, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppLayout, { faNum } from '../Layouts/AppLayout';
import DateRangeFilter, { filterByDateRange } from '../Components/DateRangeFilter';
import Pager, { usePager } from '../Components/Pager';

function StatusBadge({ o }) {
    return (
        <>
            {o.status === 'completed' && <span className="badge buy-b">تکمیل‌شده</span>}
            {o.status === 'cancelled' && (o.admin_note
                ? <span className="badge sell-b">برگشت داده شد</span>
                : <span className="badge silver">لغوشده</span>)}
        </>
    );
}

/** ردیف تاریخچه‌ی معاملات من — اطلاعات اصلی نمایش داده می‌شود، جزئیات (قیمت/تاریخ/کارمزد) در دراپ‌دون. */
function MyOfferRow({ o, printOnly }) {
    const [open, setOpen] = useState(false);
    const vs = o.view_side || o.side; // نوع از دید کاربر (پذیرنده برعکس پیشنهاددهنده)
    const priceUnit = o.unit === 'عدد' ? 'عدد' : 'گرم';

    if (printOnly) {
        return (
            <tr>
                <td><span className={`badge ${vs === 'sell' ? 'sell-b' : 'buy-b'}`}>{vs === 'sell' ? 'فروش' : 'خرید'}</span></td>
                <td>{o.item_label}</td>
                <td className="num">{o.grams}</td>
                <td className="num">{faNum(o.price_per_gram)}</td>
                <td className="num">{faNum(o.total)}</td>
                <td className="num">{o.commission ? faNum(o.commission) : '—'}</td>
                <td>{o.role || '—'}</td>
                <td><StatusBadge o={o} />{o.admin_note && <div style={{ fontSize: 11, color: 'var(--down)' }}>دلیل: {o.admin_note}</div>}</td>
                <td style={{ fontSize: 12 }}>{o.completed_at || o.created_at}</td>
            </tr>
        );
    }

    return (
        <>
            <tr>
                <td><span className={`badge ${vs === 'sell' ? 'sell-b' : 'buy-b'}`}>{vs === 'sell' ? 'فروش' : 'خرید'}</span></td>
                <td>{o.item_label}</td>
                <td className="num">{o.grams}</td>
                <td className="num">{faNum(o.total)}</td>
                <td><StatusBadge o={o} /></td>
                <td><button type="button" className="btn-sm" onClick={() => setOpen(v => !v)}>جزئیات {open ? '▲' : '▾'}</button></td>
            </tr>
            {open && (
                <tr>
                    <td colSpan={6} style={{ background: 'rgba(255,255,255,.02)' }}>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 18, fontSize: 13, color: 'var(--muted)' }}>
                            {o.role && <span>نقش شما: <strong style={{ color: 'var(--txt)' }}>{o.role}</strong></span>}
                            <span>قیمت هر {priceUnit}: <strong style={{ color: 'var(--txt)' }} className="num">{faNum(o.price_per_gram)}</strong></span>
                            <span>تاریخ: <strong style={{ color: 'var(--txt)' }}>{o.completed_at || o.created_at}</strong></span>
                            {o.commission > 0 && <span>کارمزد: <strong style={{ color: 'var(--txt)' }} className="num">{faNum(o.commission)}</strong> تومان</span>}
                            {o.admin_note && <span style={{ color: 'var(--down)' }}>دلیل: {o.admin_note}</span>}
                        </div>
                    </td>
                </tr>
            )}
        </>
    );
}

/** ردیف سفارش باز — اطلاعات اصلی + دکمه‌ی عمل؛ تاریخ ثبت در دراپ‌دون. */
function OfferRow({ o, accept, cancel }) {
    const [open, setOpen] = useState(false);
    return (
        <>
            <tr>
                <td>{o.item_label}{o.is_mine && <span className="badge gold" style={{ marginInlineStart: 6 }}>شما</span>}</td>
                <td className="num">{o.grams}</td>
                <td className="num">{faNum(o.price_per_gram)}</td>
                <td className="num" style={{ color: 'var(--gold-1)', fontWeight: 700 }}>{faNum(o.total)}</td>
                <td>
                    <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                        {o.is_mine
                            ? <button onClick={() => cancel(o.id)} className="btn-sm danger">لغو</button>
                            : <button onClick={() => accept(o)} className="btn-sm" style={{ borderColor: 'rgba(65,225,166,.4)', color: 'var(--up)', background: 'rgba(65,225,166,.08)' }}>پذیرفتن</button>}
                        <button type="button" className="btn-sm" onClick={() => setOpen(v => !v)} title="جزئیات">{open ? '▲' : '▾'}</button>
                    </div>
                </td>
            </tr>
            {open && (
                <tr>
                    <td colSpan={5} style={{ background: 'rgba(0,0,0,.12)' }}>
                        <span style={{ fontSize: 13, color: 'var(--muted)' }}>تاریخ ثبت: <strong style={{ color: 'var(--txt)' }}>{o.created_at}</strong></span>
                    </td>
                </tr>
            )}
        </>
    );
}

const ITEMS = [
    { key: 'gold',      label: 'طلا',         metal: 'gold',   purity: '' },
    { key: 'silver999', label: 'نقره ۹۹۹/۹',  metal: 'silver', purity: '999' },
    { key: 'silver995', label: 'نقره ۹۹۵',    metal: 'silver', purity: '995' },
    { key: 'bahar',     label: 'سکه تمام',    metal: 'coin',   coin: 'bahar' },
    { key: 'nim',       label: 'نیم سکه',      metal: 'coin',   coin: 'nim' },
    { key: 'rob',       label: 'ربع سکه',      metal: 'coin',   coin: 'rob' },
];

export default function TradeRoom({ sellOffers, buyOffers, myOffers, walletBalance, goldBalance, silverBalance, commissionPercent, mithqalGrams }) {
    const { errors } = usePage().props;
    const M = mithqalGrams || 4.3318;
    const [myFrom, setMyFrom] = useState('');
    const [myTo, setMyTo] = useState('');
    const [item, setItem] = useState('gold');
    const [unit, setUnit] = useState('gram'); // gram | mithqal — فقط واحد ورودی فرم
    const [acceptance, setAcceptance] = useState(null);
    const form = useForm({ metal: 'silver', side: 'sell', purity: '999', item: '', grams: '', price_per_gram: '' });
    const isCoinForm = form.data.metal === 'coin';

    // اگر از تابلوی قیمت با پارامتر آمده باشد، فلز/عیار/واحد/سکه و آیتم را پیش‌انتخاب کن
    useEffect(() => {
        const q = new URLSearchParams(window.location.search);
        const metal = q.get('metal');
        if (metal === 'coin') {
            const ci = q.get('item');
            if (['bahar', 'nim', 'rob'].includes(ci)) {
                form.setData(d => ({ ...d, metal: 'coin', item: ci }));
                setItem(ci);
            }
            return;
        }
        if (metal !== 'gold' && metal !== 'silver') return;
        const purity = q.get('purity') === '995' ? '995' : '999';
        if (q.get('unit') === 'mithqal') setUnit('mithqal');
        form.setData(d => ({ ...d, metal, purity: metal === 'silver' ? purity : d.purity }));
        setItem(metal === 'gold' ? 'gold' : (purity === '995' ? 'silver995' : 'silver999'));
    }, []);

    // قیمت لحظه‌ای سایت برای پیش‌فرض فیلد قیمت در فرم ثبت پیشنهاد
    const [prices, setPrices] = useState(null);
    useEffect(() => {
        fetch('/api/prices').then(r => r.json()).then(setPrices).catch(() => {});
    }, []);

    // با تغییر فلز / عیار / خرید‌وفروش / واحد، قیمت را با قیمت زنده‌ی سایت پر کن
    // (فروش = قیمت فروش، خرید = قیمت خرید؛ برای مثقال، قیمت گرم × ضریب مثقال)
    useEffect(() => {
        if (!prices) return;
        const { metal, side, purity, item: coin } = form.data;
        let group, key;
        if (metal === 'coin') {
            group = side === 'buy' ? 'gold_buy' : 'gold';
            key = coin;
        } else {
            key = metal === 'gold' ? 'geram' : `gram_${purity}`;
            group = metal === 'gold'
                ? (side === 'buy' ? 'gold_buy' : 'gold')
                : (side === 'buy' ? 'silver_buy' : 'silver');
        }
        const v = prices?.[group]?.[key];
        if (typeof v === 'number' && v > 0) {
            const perUnit = (metal !== 'coin' && unit === 'mithqal') ? v * M : v;
            form.setData('price_per_gram', String(Math.round(perUnit)));
        }
    }, [prices, form.data.metal, form.data.side, form.data.purity, form.data.item, unit]);

    const activeItem = ITEMS.find(i => i.key === item) || ITEMS[0];
    const matchesItem = o => activeItem.metal === 'coin'
        ? (o.metal === 'coin' && o.item === activeItem.coin)
        : (o.metal === activeItem.metal && (activeItem.metal === 'gold' || o.purity === activeItem.purity));
    const itemSellOffers = useMemo(() => sellOffers.filter(matchesItem), [sellOffers, item]);
    const itemBuyOffers = useMemo(() => buyOffers.filter(matchesItem), [buyOffers, item]);

    const filteredMyOffers = useMemo(
        () => filterByDateRange(myOffers, myFrom, myTo),
        [myOffers, myFrom, myTo]
    );
    const myOffersPager = usePager(filteredMyOffers, `${myFrom}|${myTo}`);

    const total = form.data.grams && form.data.price_per_gram
        ? Math.round(parseFloat(form.data.grams) * parseInt(form.data.price_per_gram, 10))
        : null;

    function submit(e) {
        e.preventDefault();
        // سکه: واحد عددی، بدون تبدیل. طلا/نقره با واحد مثقال → تبدیل به گرم (در سرور همه‌چیز گرمی ذخیره می‌شود)
        const qty = parseFloat(form.data.grams) || 0;
        const perUnit = parseInt(form.data.price_per_gram, 10) || 0;
        const payload = (!isCoinForm && unit === 'mithqal')
            ? { ...form.data, grams: +(qty * M).toFixed(4), price_per_gram: Math.round(perUnit / M) }
            : form.data;
        form.transform(() => payload);
        form.post('/trade-room', {
            onSuccess: () => { form.reset('grams', 'price_per_gram'); },
            onFinish: () => form.transform(data => data),
        });
    }

    function accept(offer) {
        if (offer.is_coin) {
            if (!confirm('این پیشنهاد پذیرفته شود؟ معامله فوراً نهایی می‌شود.')) return;
            router.post(`/trade-room/${offer.id}/accept`, {}, { preserveScroll: true });
            return;
        }
        setAcceptance({ offer, mode: 'full', unit: 'gram', quantity: String(offer.grams) });
    }

    function submitAcceptance(e) {
        e.preventDefault();
        const { offer, mode: acceptMode, unit: acceptUnit, quantity } = acceptance;
        const grams = acceptMode === 'full'
            ? offer.grams
            : +(parseFloat(quantity || 0) * (acceptUnit === 'mithqal' ? M : 1)).toFixed(4);
        router.post(`/trade-room/${offer.id}/accept`, { grams }, {
            preserveScroll: true,
            onSuccess: () => setAcceptance(null),
        });
    }

    function cancel(id) {
        if (!confirm('این پیشنهاد لغو شود؟')) return;
        router.post(`/trade-room/${id}/cancel`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <div className="page-wide">
                <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 8 }}>🤝 اتاق معاملاتی</h2>
                <p style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 20 }}>
                    خرید و فروش طلا، نقره و سکه بین اعضای ویژه — مستقیماً با یکدیگر، بدون واسطه‌ی فروشگاه. هر دو طرف معامله باید عضو ویژه باشند.
                </p>

                {acceptance && (() => {
                    const { offer, mode: acceptMode, unit: acceptUnit, quantity } = acceptance;
                    const maxInUnit = acceptUnit === 'mithqal' ? offer.grams / M : offer.grams;
                    const minInUnit = acceptUnit === 'mithqal' ? 100 / M : 100;
                    const acceptedGrams = acceptMode === 'full'
                        ? offer.grams
                        : (parseFloat(quantity || 0) * (acceptUnit === 'mithqal' ? M : 1));
                    const acceptedTotal = Number.isFinite(acceptedGrams) ? Math.round(acceptedGrams * offer.price_per_gram) : 0;
                    return (
                        <div role="dialog" aria-modal="true" aria-labelledby="accept-offer-title" style={{ position: 'fixed', zIndex: 50, inset: 0, background: 'rgba(0,0,0,.64)', display: 'grid', placeItems: 'center', padding: 16 }}>
                            <form onSubmit={submitAcceptance} style={{ width: 'min(100%, 440px)', background: 'var(--card)', border: '1px solid var(--line)', borderRadius: 18, padding: 20, boxShadow: '0 18px 48px rgba(0,0,0,.35)' }}>
                                <h3 id="accept-offer-title" style={{ margin: '0 0 8px', fontSize: 18 }}>پذیرش سفارش {offer.item_label}</h3>
                                <p style={{ color: 'var(--muted)', fontSize: 13, lineHeight: 1.8, margin: '0 0 16px' }}>
                                    ماندهٔ این سفارش {faNum(offer.grams)} گرم است. کل سفارش را می‌خواهید یا بخشی از آن را؟
                                </p>
                                <div className="btn-row" style={{ marginBottom: 16 }}>
                                    <button type="button" onClick={() => setAcceptance(a => ({ ...a, mode: 'full', quantity: String(a.offer.grams) }))} style={{ padding: 11, borderRadius: 12, fontFamily: 'inherit', fontWeight: 700, cursor: 'pointer', border: acceptMode === 'full' ? '2px solid var(--gold-1)' : '1px solid var(--line)', background: acceptMode === 'full' ? 'rgba(246,207,99,.15)' : 'transparent', color: acceptMode === 'full' ? 'var(--gold-1)' : 'var(--txt)' }}>کل سفارش</button>
                                    <button type="button" onClick={() => setAcceptance(a => ({ ...a, mode: 'partial', quantity: a.quantity === String(a.offer.grams) ? '' : a.quantity }))} style={{ padding: 11, borderRadius: 12, fontFamily: 'inherit', fontWeight: 700, cursor: 'pointer', border: acceptMode === 'partial' ? '2px solid var(--gold-1)' : '1px solid var(--line)', background: acceptMode === 'partial' ? 'rgba(246,207,99,.15)' : 'transparent', color: acceptMode === 'partial' ? 'var(--gold-1)' : 'var(--txt)' }}>بخشی از سفارش</button>
                                </div>
                                {acceptMode === 'partial' && <>
                                    <div className="btn-row" style={{ marginBottom: 12 }}>
                                        {[['gram', 'گرم'], ['mithqal', 'مثقال']].map(([u, label]) => <button key={u} type="button" onClick={() => setAcceptance(a => ({ ...a, unit: u, quantity: '' }))} style={{ padding: 9, borderRadius: 10, fontFamily: 'inherit', cursor: 'pointer', border: acceptUnit === u ? '2px solid var(--gold-1)' : '1px solid var(--line)', background: acceptUnit === u ? 'rgba(246,207,99,.15)' : 'transparent', color: acceptUnit === u ? 'var(--gold-1)' : 'var(--txt)' }}>واحد: {label}</button>)}
                                    </div>
                                    <div className="field">
                                        <label>مقدار پذیرش ({acceptUnit === 'mithqal' ? 'مثقال' : 'گرم'}) — حداقل معادل ۱۰۰ گرم</label>
                                        <input type="number" autoFocus required step="any" min={minInUnit} max={maxInUnit} value={quantity} onChange={e => setAcceptance(a => ({ ...a, quantity: e.target.value }))} />
                                    </div>
                                </>}
                                <div style={{ background: 'rgba(255,255,255,.04)', border: '1px solid var(--line)', borderRadius: 12, padding: '10px 12px', margin: '14px 0', fontSize: 13 }}>
                                    <div style={{ color: 'var(--muted)' }}>مقدار این معامله: <strong style={{ color: 'var(--txt)' }}>{faNum(Number(acceptedGrams.toFixed(4)) || 0)} گرم</strong></div>
                                    <div style={{ color: 'var(--muted)', marginTop: 5 }}>مبلغ تقریبی: <strong className="num" style={{ color: 'var(--gold-1)' }}>{faNum(acceptedTotal)} تومان</strong></div>
                                    {acceptMode === 'partial' && <div style={{ color: 'var(--muted)', marginTop: 5 }}>مانده پس از پذیرش: {faNum(Math.max(0, offer.grams - acceptedGrams).toFixed(4))} گرم</div>}
                                </div>
                                <p style={{ fontSize: 12, color: 'var(--muted)', lineHeight: 1.7, margin: '0 0 14px' }}>در پذیرش جزئی، باید پس از معامله حداقل ۱۰۰ گرم در سفارش باقی بماند؛ مگر اینکه کل سفارش را بپذیرید.</p>
                                <div style={{ display: 'flex', gap: 10 }}>
                                    <button type="submit" className="btn" style={{ flex: 1 }}>تأیید پذیرش</button>
                                    <button type="button" className="btn-sm" onClick={() => setAcceptance(null)}>انصراف</button>
                                </div>
                            </form>
                        </div>
                    );
                })()}

                {/* موجودی */}
                <div style={{ display: 'flex', gap: 14, marginBottom: 24, flexWrap: 'wrap' }}>
                    <div style={{ flex: '1', minWidth: 180, background: 'linear-gradient(160deg,var(--card),var(--card-2))', border: '1px solid var(--line)', borderRadius: 16, padding: '16px 18px' }}>
                        <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>موجودی کیف پول</div>
                        <div style={{ fontSize: 22, fontWeight: 800, color: 'var(--up)' }}>
                            {faNum(walletBalance)} <span style={{ fontSize: 13, fontWeight: 400, color: 'var(--muted)' }}>تومان</span>
                        </div>
                    </div>
                    <div style={{ flex: '1', minWidth: 180, background: 'linear-gradient(160deg,var(--card),var(--card-2))', border: '1px solid var(--line)', borderRadius: 16, padding: '16px 18px' }}>
                        <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>موجودی طلا</div>
                        <div style={{ fontSize: 22, fontWeight: 800, color: 'var(--gold-1)' }}>
                            {faNum(goldBalance)} <span style={{ fontSize: 13, fontWeight: 400, color: 'var(--muted)' }}>گرم</span>
                        </div>
                    </div>
                    {['999', '995'].map(p => (
                        <div key={p} style={{
                            flex: '1', minWidth: 180, background: 'linear-gradient(160deg,var(--card),var(--card-2))',
                            border: '1px solid var(--line)', borderRadius: 16, padding: '16px 18px',
                        }}>
                            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>موجودی نقره {p}</div>
                            <div style={{ fontSize: 22, fontWeight: 800, color: 'var(--silver-1)' }}>
                                {faNum(silverBalance[p])} <span style={{ fontSize: 13, fontWeight: 400, color: 'var(--muted)' }}>گرم</span>
                            </div>
                        </div>
                    ))}
                    <a href="/inventory" className="fcard" style={{
                        flex: '1', minWidth: 180, display: 'flex', flexDirection: 'column',
                        justifyContent: 'center', alignItems: 'center', textAlign: 'center',
                        padding: '16px 18px', textDecoration: 'none',
                    }}>
                        <div style={{ fontSize: 24, marginBottom: 4 }}>🚚</div>
                        <div style={{ fontSize: 13, fontWeight: 700 }}>درخواست تحویل فیزیکی (در موجودی انبار)</div>
                    </a>
                </div>

                {/* ردیف دو‌ستونه: سفارش‌های باز (راست) + ثبت پیشنهاد جدید (چپ) */}
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 18, alignItems: 'flex-start', marginBottom: 24 }}>
                    {/* سمت راست (در RTL فرزند اول): سفارش‌های باز */}
                    <div className="no-print" style={{ flex: 1, minWidth: 320 }}>
                        <div className="btn-row" style={{ gridTemplateColumns: 'repeat(3, 1fr)', marginBottom: 18 }}>
                            {ITEMS.map(i => (
                                <button key={i.key} type="button" onClick={() => {
                                    setItem(i.key);
                                    form.setData(d => ({ ...d, metal: i.metal, purity: i.metal === 'silver' ? (i.purity || d.purity) : '', item: i.metal === 'coin' ? i.coin : '' }));
                                }}
                                    style={{
                                        padding: '10px', borderRadius: 12, fontFamily: 'inherit', fontSize: 14, fontWeight: 700,
                                        cursor: 'pointer', border: 'none',
                                        background: item === i.key ? 'rgba(246,207,99,.2)' : 'rgba(255,255,255,.06)',
                                        color: item === i.key ? 'var(--gold-1)' : 'var(--muted)',
                                        outline: item === i.key ? '2px solid var(--gold-1)' : '2px solid transparent',
                                    }}>
                                    {i.label}
                                </button>
                            ))}
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                            <OfferSection variant="sell" qtyHeader={activeItem.metal === 'coin' ? 'تعداد' : 'مقدار (گرم)'} offers={itemSellOffers} accept={accept} cancel={cancel} />
                            <OfferSection variant="buy" qtyHeader={activeItem.metal === 'coin' ? 'تعداد' : 'مقدار (گرم)'} offers={itemBuyOffers} accept={accept} cancel={cancel} />
                        </div>
                    </div>

                    {/* سمت چپ (در RTL فرزند دوم): ثبت پیشنهاد جدید */}
                    <div className="no-print fcard" style={{ flex: 1, minWidth: 320 }}>
                        <h2 style={{ fontSize: 16 }}>ثبت پیشنهاد جدید</h2>
                        <div style={{ height: 16 }} />
                        {errors.grams && <div className="alert err">{errors.grams}</div>}
                        {errors.offer && <div className="alert err">{errors.offer}</div>}
                        <form onSubmit={submit}>
                        <div className="btn-row" style={{ gridTemplateColumns: 'repeat(3, 1fr)', marginBottom: 12 }}>
                            {[['gold', 'طلا'], ['silver', 'نقره'], ['coin', 'سکه']].map(([m, label]) => (
                                <button key={m} type="button" onClick={() => form.setData(d => ({
                                    ...d, metal: m,
                                    purity: m === 'silver' ? (d.purity || '999') : '',
                                    item: m === 'coin' ? (d.item || 'bahar') : '',
                                }))}
                                    style={{
                                        padding: '10px', borderRadius: 12, fontFamily: 'inherit', fontSize: 14, fontWeight: 700,
                                        cursor: 'pointer', border: 'none',
                                        background: form.data.metal === m ? 'rgba(246,207,99,.2)' : 'rgba(255,255,255,.06)',
                                        color: form.data.metal === m ? 'var(--gold-1)' : 'var(--muted)',
                                        outline: form.data.metal === m ? '2px solid var(--gold-1)' : '2px solid transparent',
                                    }}>
                                    {label}
                                </button>
                            ))}
                        </div>
                        <div className="btn-row" style={{ marginBottom: 16 }}>
                            {['sell', 'buy'].map(s => (
                                <button key={s} type="button" onClick={() => form.setData('side', s)}
                                    style={{
                                        padding: '11px', borderRadius: 12, fontFamily: 'inherit', fontSize: 14, fontWeight: 700,
                                        cursor: 'pointer', border: 'none',
                                        background: form.data.side === s ? (s === 'sell' ? 'rgba(255,107,120,.2)' : 'rgba(65,225,166,.2)') : 'rgba(255,255,255,.06)',
                                        color: form.data.side === s ? (s === 'sell' ? 'var(--down)' : 'var(--up)') : 'var(--muted)',
                                        outline: form.data.side === s ? `2px solid ${s === 'sell' ? 'var(--down)' : 'var(--up)'}` : '2px solid transparent',
                                    }}>
                                    {s === 'sell' ? '🔴 می‌فروشم' : '🟢 می‌خرم'}
                                </button>
                            ))}
                        </div>
                        {!isCoinForm && (
                            <div className="btn-row" style={{ marginBottom: 16 }}>
                                {[['gram', 'گرم'], ['mithqal', 'مثقال']].map(([u, label]) => (
                                    <button key={u} type="button" onClick={() => setUnit(u)}
                                        style={{
                                            padding: '10px', borderRadius: 12, fontFamily: 'inherit', fontSize: 14, fontWeight: 700,
                                            cursor: 'pointer', border: 'none',
                                            background: unit === u ? 'rgba(246,207,99,.2)' : 'rgba(255,255,255,.06)',
                                            color: unit === u ? 'var(--gold-1)' : 'var(--muted)',
                                            outline: unit === u ? '2px solid var(--gold-1)' : '2px solid transparent',
                                        }}>
                                        واحد: {label}
                                    </button>
                                ))}
                            </div>
                        )}
                        <div style={{ display: 'grid', gridTemplateColumns: (form.data.metal === 'silver' || isCoinForm) ? '1fr 1fr' : '1fr', gap: 12 }}>
                            {form.data.metal === 'silver' && (
                                <div className="field">
                                    <label>عیار</label>
                                    <select value={form.data.purity} onChange={e => form.setData('purity', e.target.value)}
                                        style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 12, padding: '11px 14px', fontFamily: 'inherit', fontSize: 15, width: '100%' }}>
                                        <option value="999">نقره ۹۹۹/۹</option>
                                        <option value="995">نقره ۹۹۵</option>
                                    </select>
                                </div>
                            )}
                            {isCoinForm && (
                                <div className="field">
                                    <label>نوع سکه</label>
                                    <select value={form.data.item} onChange={e => form.setData('item', e.target.value)}
                                        style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 12, padding: '11px 14px', fontFamily: 'inherit', fontSize: 15, width: '100%' }}>
                                        <option value="bahar">سکه تمام</option>
                                        <option value="nim">نیم سکه</option>
                                        <option value="rob">ربع سکه</option>
                                    </select>
                                </div>
                            )}
                            <div className="field">
                                <label>{isCoinForm ? 'تعداد (عدد)' : `مقدار (${unit === 'mithqal' ? 'مثقال' : 'گرم'}) — حداقل معادل ۱۰۰ گرم`}</label>
                                <input type="number"
                                    step={isCoinForm ? 1 : 'any'}
                                    min={isCoinForm ? 1 : (unit === 'mithqal' ? (Math.ceil((100 / M) * 100) / 100) : 100)}
                                    value={form.data.grams}
                                    onChange={e => form.setData('grams', e.target.value)} required />
                            </div>
                        </div>
                        <div className="field">
                            <label>قیمت هر {isCoinForm ? 'عدد' : (unit === 'mithqal' ? 'مثقال' : 'گرم')} (تومان) — پیش‌فرض از قیمت لحظه‌ای سایت، قابل ویرایش</label>
                            <input type="number" min="1" value={form.data.price_per_gram}
                                onChange={e => form.setData('price_per_gram', e.target.value)} required />
                        </div>
                        {total != null && (
                            <div style={{ background: 'rgba(255,255,255,.04)', border: '1px solid var(--line)', borderRadius: 12, padding: '12px 16px', marginBottom: 16 }}>
                                <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 4 }}>مبلغ کل</div>
                                <div style={{ fontSize: 20, fontWeight: 800, color: 'var(--gold-1)' }}>{faNum(total)} تومان</div>
                                {commissionPercent > 0 && (
                                    <div style={{ fontSize: 11, color: 'var(--muted)', marginTop: 6 }}>
                                        کارمزد اتاق معاملاتی: {commissionPercent}٪ (هنگام انجام معامله بین خریدار و فروشنده نصف‌نصف کسر می‌شود)
                                    </div>
                                )}
                            </div>
                        )}
                        <button className="btn" type="submit" disabled={form.processing}>
                            {form.processing ? '...' : 'ثبت پیشنهاد'}
                        </button>
                        </form>
                    </div>
                </div>

                {/* پایین، تمام‌عرض: تاریخچه‌ی معاملات من */}
                <div className="section-title">📋 تاریخچه‌ی معاملات من</div>
                <div className="no-print" style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap', marginBottom: 18 }}>
                    <DateRangeFilter from={myFrom} to={myTo} onFromChange={setMyFrom} onToChange={setMyTo} />
                    {(myFrom || myTo) && <button type="button" className="btn-sm" onClick={() => { setMyFrom(''); setMyTo(''); }}>حذف فیلتر</button>}
                    <button type="button" className="btn-sm" onClick={() => window.print()} style={{ borderColor: 'rgba(246,207,99,.4)', color: 'var(--gold-1)', background: 'rgba(246,207,99,.08)' }}>
                        🖨️ چاپ / خروجی PDF
                    </button>
                </div>

                {filteredMyOffers.length ? (
                    <>
                        <div className="table-wrap">
                            <table>
                                <thead><tr><th>نوع</th><th>مورد</th><th>مقدار</th><th>مبلغ کل</th><th>وضعیت</th><th></th></tr></thead>
                                <tbody>
                                    {myOffersPager.pageItems.map(o => <MyOfferRow key={o.id} o={o} />)}
                                </tbody>
                            </table>
                        </div>
                        <Pager page={myOffersPager.page} totalPages={myOffersPager.totalPages} onChange={myOffersPager.setPage} />

                        <div className="table-wrap print-area print-only-block">
                            <div className="print-only" style={{ marginBottom: 14, fontWeight: 800, fontSize: 16 }}>تاریخچه‌ی اتاق معاملاتی</div>
                            <table>
                                <thead><tr><th>نوع</th><th>مورد</th><th>مقدار</th><th>قیمت هر گرم</th><th>مبلغ کل</th><th>کارمزد</th><th>نقش</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
                                <tbody>
                                    {filteredMyOffers.map(o => <MyOfferRow key={o.id} o={o} printOnly />)}
                                </tbody>
                            </table>
                        </div>
                    </>
                ) : (
                    <div className="empty"><div className="ico">📋</div>هنوز معامله‌ای انجام نداده‌اید.</div>
                )}
            </div>
        </AppLayout>
    );
}

function OfferSection({ variant, qtyHeader = 'مقدار (گرم)', offers, accept, cancel }) {
    const isSell = variant === 'sell';
    const bg = isSell ? 'rgba(255,107,120,.08)' : 'rgba(65,225,166,.08)';
    const border = isSell ? 'rgba(255,107,120,.3)' : 'rgba(65,225,166,.3)';
    const priceHeader = qtyHeader === 'تعداد' ? 'قیمت هر عدد' : 'قیمت هر گرم';
    return (
        <div style={{ flex: '1', minWidth: 320 }}>
            {offers.length ? (
                <div className="table-wrap" style={{ background: bg, borderColor: border }}>
                    <table>
                        <thead><tr><th>مورد</th><th>{qtyHeader}</th><th>{priceHeader}</th><th>مبلغ کل</th><th></th></tr></thead>
                        <tbody>
                            {offers.map(o => <OfferRow key={o.id} o={o} accept={accept} cancel={cancel} />)}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="empty" style={{ background: bg, border: `1px solid ${border}`, borderRadius: 14, padding: '40px 0' }}><div className="ico">🤝</div>سفارش بازی نیست.</div>
            )}
        </div>
    );
}
