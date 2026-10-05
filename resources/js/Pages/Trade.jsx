import { useForm, usePage } from '@inertiajs/react';
import AppLayout, { faNum } from '../Layouts/AppLayout';

export default function Trade({ item, meta, sellPrice, buyPrice, mithqalGrams, catalog }) {
    const MITHQAL = mithqalGrams || 4.3318;

    // پارامترهای URL فقط برای حفظ حالت هنگام جابه‌جایی بین محصولات (طلا/سکه/نقره) خوانده می‌شوند
    const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
    const urlAmount = parseFloat(params.get('amount'));

    const { data, setData, post, processing, errors } = useForm({
        trade_type: params.get('type') === 'sell' ? 'sell' : 'buy',
        mode: params.get('mode') === 'money' ? 'money' : 'quantity',
        quantity: '',
        amount: Number.isFinite(urlAmount) && urlAmount > 0 ? String(urlAmount) : '',
    });

    const page = usePage();
    const walletBalance = page.props.auth?.user?.wallet_balance || 0;

    // مشتری «خرید» بزند → قیمت فروش ما؛ «فروش» بزند → قیمت خرید ما
    const price = data.trade_type === 'buy' ? sellPrice : buyPrice;

    // حداقل معامله ۱۰ گرم — فقط برای آیتم‌های وزنی (گرم/مثقال طلا و نقره)، نه سکه‌ها
    const isMesghal = item.startsWith('mithqal');
    const isCoinItem = meta.group === 'gold' && !isMesghal && item !== 'geram';
    const isWeightItem = !isCoinItem;
    const minQty = isMesghal ? +(10 / MITHQAL).toFixed(4) : (isWeightItem ? 10 : 0.001);

    const qty = parseFloat(data.quantity) || 0;
    const amount = parseFloat(data.amount) || 0;

    // «تبدیل کل پول به مقدار»: مقدار = مبلغ ÷ قیمت — وزنی‌ها رو به پایین با ۴ رقم اعشار، سکه‌ها عدد صحیح
    let convertedQty = null;
    if (data.mode === 'money' && amount > 0 && price) {
        convertedQty = isCoinItem
            ? Math.floor(amount / price + 1e-9)
            : Math.floor((amount / price) * 10000 + 1e-9) / 10000;
    }
    const convertedGrams = convertedQty != null && isWeightItem
        ? Math.round((item === 'geram' || item.startsWith('gram_') ? convertedQty : convertedQty * MITHQAL) * 10000) / 10000
        : null;
    const conversionOk = data.mode !== 'money' || (convertedQty != null && convertedQty > 0 && (isCoinItem || convertedGrams >= 10));
    const convertedTotal = convertedQty != null && convertedQty > 0 ? Math.round(convertedQty * price) : null;

    const total = data.mode === 'quantity' && qty && price ? Math.round(qty * price) : null;
    const unitLabel = isCoinItem ? 'عدد' : (isMesghal ? 'مثقال' : 'گرم');
    const errorMsg = errors.amount || errors.quantity;

    function submit(e) {
        e.preventDefault();
        post(`/trade/${item}`);
    }

    // جابه‌جایی بین محصولات با حفظ نوع عملیات و (در حالت مبلغ) مبلغ واردشده
    function switchItem(nextItem) {
        if (nextItem === item) return;
        const q = new URLSearchParams();
        q.set('type', data.trade_type);
        if (data.mode === 'money') {
            q.set('mode', 'money');
            if (data.amount) q.set('amount', data.amount);
        }
        window.location.href = `/trade/${nextItem}?${q.toString()}`;
    }

    const groups = [];
    (catalog || []).forEach(it => {
        let g = groups.find(x => x.section === it.section);
        if (!g) { g = { label: it.section_label, items: [] }; groups.push(g); }
        g.items.push(it);
    });

    return (
        <AppLayout>
            <div className="page">
                <div className="fcard">
                    <h2>{meta.label}</h2>

                    {groups.length > 0 && (
                        <div className="field" style={{ marginTop: 16 }}>
                            <label>انتخاب محصول (طلا، سکه یا نقره — گرم/مثقال)</label>
                            <select value={item} onChange={e => switchItem(e.target.value)}
                                style={{ background: 'rgba(255,255,255,.06)', border: '1px solid var(--line)', color: 'var(--txt)', borderRadius: 12, padding: '11px 14px', fontFamily: 'inherit', fontSize: 15, width: '100%' }}>
                                {groups.map(g => (
                                    <optgroup key={g.label} label={g.label}>
                                        {g.items.map(it => <option key={it.key} value={it.key}>{it.label}</option>)}
                                    </optgroup>
                                ))}
                            </select>
                        </div>
                    )}

                    <div style={{ height: 20 }} />

                    {price ? (
                        <div style={{
                            background: data.trade_type === 'buy'
                                ? 'linear-gradient(135deg,rgba(65,225,166,.14),rgba(31,157,114,.06))'
                                : 'linear-gradient(135deg,rgba(255,107,120,.14),rgba(199,60,70,.06))',
                            border: `1px solid ${data.trade_type === 'buy' ? 'rgba(65,225,166,.3)' : 'rgba(255,107,120,.3)'}`,
                            borderRadius: 14, padding: '16px 20px', marginBottom: 20,
                        }}>
                            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 4 }}>
                                قیمت {data.trade_type === 'buy' ? 'فروش (شما می‌خرید)' : 'خرید (شما می‌فروشید)'}
                            </div>
                            <div style={{ fontSize: 28, fontWeight: 900, color: data.trade_type === 'buy' ? 'var(--up)' : 'var(--down)' }}>
                                {faNum(price)} <span style={{ fontSize: 14, fontWeight: 400, color: 'var(--muted)' }}>تومان</span>
                            </div>
                        </div>
                    ) : (
                        <div className="alert err">قیمت در حال حاضر در دسترس نیست.</div>
                    )}

                    <div style={{
                        background: 'rgba(246,207,99,.07)',
                        border: '1px solid rgba(246,207,99,.28)',
                        borderRadius: 12, padding: '10px 14px', marginBottom: 18,
                        fontSize: 12.5, lineHeight: 1.9, color: 'var(--txt)',
                    }}>
                        ⏰ نرخ‌های خرید و فروش بین ساعت ۱۰ صبح تا ۸ شب می‌باشد و خرید و فروش با نرخ این ساعات مورد تأیید است؛ خارج از این ساعت به نرخ فردا منتقل می‌شود.
                    </div>

                    {errorMsg && <div className="alert err">{errorMsg}</div>}

                    <form onSubmit={submit}>
                        <div className="btn-row" style={{ marginBottom: 18 }}>
                            {['buy', 'sell'].map(t => (
                                <button key={t} type="button"
                                    onClick={() => setData('trade_type', t)}
                                    style={{
                                        padding: '12px', borderRadius: 12, fontFamily: 'inherit',
                                        fontSize: 15, fontWeight: 700, cursor: 'pointer', border: 'none',
                                        background: data.trade_type === t
                                            ? (t === 'buy' ? 'rgba(65,225,166,.2)' : 'rgba(255,107,120,.2)')
                                            : 'rgba(255,255,255,.06)',
                                        color: data.trade_type === t
                                            ? (t === 'buy' ? 'var(--up)' : 'var(--down)')
                                            : 'var(--muted)',
                                        outline: data.trade_type === t
                                            ? `2px solid ${t === 'buy' ? 'var(--up)' : 'var(--down)'}`
                                            : '2px solid transparent',
                                    }}>
                                    {t === 'buy' ? '🟢 خرید' : '🔴 فروش'}
                                </button>
                            ))}
                        </div>
                        <div className="btn-row" style={{ marginBottom: 12 }}>
                            {[['quantity', 'بر اساس مقدار'], ['money', 'بر اساس مبلغ (تومان)']].map(([m, label]) => (
                                <button key={m} type="button"
                                    onClick={() => setData('mode', m)}
                                    style={{
                                        padding: '10px', borderRadius: 12, fontFamily: 'inherit',
                                        fontSize: 14, fontWeight: 700, cursor: 'pointer', border: 'none',
                                        background: data.mode === m ? 'rgba(246,207,99,.16)' : 'rgba(255,255,255,.06)',
                                        color: data.mode === m ? 'var(--gold-1)' : 'var(--muted)',
                                        outline: data.mode === m ? '2px solid rgba(246,207,99,.45)' : '2px solid transparent',
                                    }}>
                                    {label}
                                </button>
                            ))}
                        </div>
                        {data.mode === 'quantity' ? (
                            <>
                                <div className="field">
                                    <label>مقدار{isWeightItem && <span style={{ color: 'var(--muted)', fontWeight: 400 }}> — حداقل {faNum(minQty)}</span>}</label>
                                    <input type="number" step="any" min={minQty}
                                        value={data.quantity} onChange={e => setData('quantity', e.target.value)}
                                        placeholder="مثال: ۱ یا ۰.۵" required />
                                </div>
                                {total && (
                                    <div style={{ background: 'rgba(255,255,255,.04)', border: '1px solid var(--line)', borderRadius: 12, padding: '12px 16px', marginBottom: 18 }}>
                                        <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 4 }}>مبلغ کل (تخمینی)</div>
                                        <div style={{ fontSize: 22, fontWeight: 800, color: 'var(--gold-1)' }}>{faNum(total)} تومان</div>
                                    </div>
                                )}
                            </>
                        ) : (
                            <>
                                <div className="field">
                                    <label>مبلغ (تومان){isWeightItem && <span style={{ color: 'var(--muted)', fontWeight: 400 }}> — حداقل معادل ۱۰ گرم</span>}</label>
                                    <input type="number" step="any" min="1"
                                        value={data.amount} onChange={e => setData('amount', e.target.value)}
                                        placeholder="مثال: ۱۰۰۰۰۰۰۰" required />
                                </div>
                                {walletBalance > 0 && (
                                    <button type="button" onClick={() => setData('amount', String(walletBalance))}
                                        style={{
                                            width: '100%', marginBottom: 18, padding: '10px 12px', borderRadius: 12,
                                            background: 'rgba(246,207,99,.08)', border: '1px dashed rgba(246,207,99,.4)',
                                            color: 'var(--gold-1)', fontFamily: 'inherit', fontSize: 13.5, fontWeight: 700, cursor: 'pointer',
                                        }}>
                                        تبدیل کل موجودی کیف پول ({faNum(walletBalance)} تومان)
                                    </button>
                                )}
                                {amount > 0 && price && (
                                    <div style={{ background: 'rgba(255,255,255,.04)', border: '1px solid var(--line)', borderRadius: 12, padding: '12px 16px', marginBottom: 18 }}>
                                        {conversionOk ? (
                                            <>
                                                <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 4 }}>مقدار خرید (با کل این مبلغ)</div>
                                                <div style={{ fontSize: 22, fontWeight: 800, color: 'var(--gold-1)' }}>{faNum(convertedQty)} {unitLabel}</div>
                                                <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 6 }}>مبلغ نهایی: {faNum(convertedTotal)} تومان</div>
                                            </>
                                        ) : (
                                            <div style={{ fontSize: 12.5, color: 'var(--down)' }}>
                                                {isCoinItem
                                                    ? `مبلغ واردشده برای خرید حداقل یک «${meta.label}» کافی نیست.`
                                                    : 'مبلغ واردشده کمتر از حداقل معامله (۱۰ گرم) است.'}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </>
                        )}
                        <button className="btn" type="submit" disabled={processing || !price || (data.mode === 'money' && !conversionOk)}>
                            {processing ? '...' : `افزودن ${data.trade_type === 'buy' ? 'خرید' : 'فروش'} به سبد خرید`}
                        </button>
                    </form>
                    <div className="form-foot"><a href="/cart">سبد خرید</a> · <a href="/history">سوابق معاملات</a> · <a href="/chart">📈 مشاهده چارت</a></div>
                </div>
            </div>
        </AppLayout>
    );
}
