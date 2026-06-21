import { useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';

function faNum(n) {
    if (n === null || n === undefined || isNaN(n)) return '—';
    return Math.round(n).toLocaleString('fa-IR');
}
function faPct(p) {
    return p.toLocaleString('fa-IR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '٪';
}

function CoinIcon() {
    return (
        <svg viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9.2" fill="none" stroke="currentColor" strokeWidth="1.4" />
            <circle cx="12" cy="12" r="6.4" fill="none" stroke="currentColor" strokeWidth="0.9" opacity=".7" />
            <text x="12" y="15.4" textAnchor="middle" fontFamily="Tahoma,sans-serif" fontWeight="700" fontSize="8.2" fill="currentColor">ت</text>
        </svg>
    );
}
function IngotIcon() {
    return (
        <svg viewBox="0 0 24 24">
            <path d="M3.5 17l2.4-5.2h12.2L20.5 17H3.5z" fill="currentColor" opacity=".55" />
            <path d="M5.9 11.8L7.7 8h8.6l1.8 3.8H5.9z" fill="currentColor" opacity=".85" />
            <path d="M9 8l1-1.6h4L15 8H9z" fill="currentColor" />
        </svg>
    );
}
function UsdIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 4v16M15.5 7.5C15.5 6 14 5 12 5S8.5 6 8.5 8s1.7 2.6 3.5 2.9 3.5 1 3.5 3-1.7 3-3.5 3-3.5-1-3.5-2.5"
                fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

function PriceCard({ id, name, sub, icon, cls, price, open, buyable, href }) {
    const [flash, setFlash] = useState(false);
    const prevRef = useRef(undefined);

    useEffect(() => {
        if (price != null && prevRef.current !== undefined && prevRef.current !== price) {
            setFlash(true);
            const t = setTimeout(() => setFlash(false), 1000);
            return () => clearTimeout(t);
        }
        prevRef.current = price;
    }, [price]);

    let chg = null;
    if (price != null && open != null && open > 0 && price !== open) {
        chg = { up: price > open, pct: Math.abs((price - open) / open * 100) };
    }

    return (
        <div
            id={id}
            className={`tv-card ${cls}${buyable ? ' buyable' : ''}${flash ? ' flash' : ''}`}
            onClick={buyable ? () => router.visit(href) : undefined}
        >
            <div className="tv-glow" />
            <div className="tv-top">
                <div className="tv-icon">{icon}</div>
                <div>
                    <div className="tv-name">{name}</div>
                    {sub && <div className="tv-sub">{sub}</div>}
                </div>
            </div>
            <div className="tv-value">
                <span className="tv-price">{price != null ? faNum(price) : '—'}</span>
                <span className="tv-unit">تومان</span>
                {chg
                    ? <span className={`tv-chg ${chg.up ? 'up' : 'down'}`}>{chg.up ? '▲' : '▼'} {faPct(chg.pct)}</span>
                    : <span className="tv-chg zero">۰٪</span>}
            </div>
        </div>
    );
}

function OunceDisplay({ value, label, cls }) {
    const [dir, setDir] = useState(null);
    const prevRef = useRef(null);
    useEffect(() => {
        if (value != null) {
            if (prevRef.current != null && value !== prevRef.current) {
                setDir(value > prevRef.current ? 'up' : 'down');
            }
            prevRef.current = value;
        }
    }, [value]);
    if (value == null) return <span className={`tv-ounce ${cls}`} />;
    const num = value.toLocaleString('fa-IR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (
        <span className={`tv-ounce ${cls} ${dir || ''}`}>
            {label}: {num} $ {dir === 'up' ? ' ▲' : dir === 'down' ? ' ▼' : ''}
        </span>
    );
}

const SILVER = [
    { key: 'mithqal_999', name: 'مثقال نقره ۹۹۹/۹', sub: 'هر مثقال' },
    { key: 'gram_999',    name: 'گرم نقره ۹۹۹/۹',   sub: 'هر گرم' },
    { key: 'mithqal_995', name: 'مثقال نقره ۹۹۵',   sub: 'هر مثقال' },
    { key: 'gram_995',    name: 'گرم نقره ۹۹۵',     sub: 'هر گرم' },
];
const GOLD = [
    { key: 'bahar', name: 'سکه تمام', sub: 'طرح جدید' },
    { key: 'nim',   name: 'نیم‌سکه',   sub: '' },
    { key: 'rob',   name: 'ربع‌سکه',   sub: '' },
    { key: 'geram', name: 'گرم طلا',   sub: '' },
];

export default function Home({ prices: initial, refreshSeconds }) {
    const { auth } = usePage().props;
    const user = auth?.user;

    const [data, setData] = useState(initial);
    const [online, setOnline] = useState(true);
    const [now, setNow] = useState(new Date());

    useEffect(() => {
        const id = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(id);
    }, []);

    useEffect(() => {
        let left = refreshSeconds || 30;
        const id = setInterval(async () => {
            left -= 1;
            if (left <= 0) {
                left = refreshSeconds || 30;
                try {
                    const res = await fetch('/api/prices?_=' + Date.now(), { cache: 'no-store' });
                    const json = await res.json();
                    setData(json);
                    setOnline(true);
                } catch {
                    setOnline(false);
                }
            }
        }, 1000);
        return () => clearInterval(id);
    }, [refreshSeconds]);

    const open = data.open || {};
    const ratio = data.gold?.geram && data.silver?.gram_999 > 0
        ? (data.gold.geram / data.silver.gram_999).toLocaleString('fa-IR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : '—';

    return (
        <>
            <style>{`
                .tv-wrap{min-height:100vh; display:flex; flex-direction:column; padding:clamp(12px,1.6vw,28px);}
                .tv-wrap header{
                  display:flex; align-items:center; justify-content:space-between; flex-direction:row;
                  gap:18px; padding:clamp(8px,1vw,16px) clamp(14px,1.6vw,26px);
                  background:linear-gradient(90deg,rgba(255,255,255,.04),rgba(255,255,255,.01));
                  border:1px solid var(--line); border-radius:22px; backdrop-filter:blur(6px);
                }
                .tv-headright{display:flex; flex-direction:column; align-items:flex-start; gap:4px}
                .tv-htitle{font-size:clamp(15px,1.5vw,26px); font-weight:800}
                .tv-hsub{font-size:clamp(11px,.9vw,15px); color:var(--muted)}
                .tv-brandc{display:flex; flex-direction:column; align-items:center; text-align:center; flex:1}
                .tv-shopname{
                  font-family:"Noto Nastaliq Urdu",serif; direction:rtl;
                  font-size:clamp(26px,3.6vw,66px); line-height:2; font-weight:400;
                  padding-block:.18em .28em;
                  background:linear-gradient(180deg,var(--gold-1),var(--gold-2));
                  -webkit-background-clip:text; background-clip:text; color:transparent;
                  filter:drop-shadow(0 2px 8px var(--gold-glow));
                }
                .tv-status{display:flex; align-items:center; gap:14px}
                .tv-clock{text-align:left; font-variant-numeric:tabular-nums}
                .tv-clock .t{font-size:clamp(26px,3.1vw,54px); font-weight:800; line-height:1}
                .tv-clock .d{font-size:clamp(12px,1vw,17px); color:var(--txt); margin-top:4px}
                .tv-live{
                  display:flex; align-items:center; gap:8px; font-size:clamp(12px,1vw,16px);
                  padding:8px 14px; border-radius:999px; border:1px solid var(--line);
                  background:rgba(255,255,255,.03);
                }
                .tv-dot{width:11px; height:11px; border-radius:50%; background:var(--up);
                  box-shadow:0 0 0 0 var(--up); animation:tvpulse 2s infinite}
                .tv-dot.off{background:var(--down); animation:none}
                @keyframes tvpulse{0%{box-shadow:0 0 0 0 rgba(65,225,166,.5)}70%{box-shadow:0 0 0 12px rgba(65,225,166,0)}100%{box-shadow:0 0 0 0 rgba(65,225,166,0)}}
                .tv-nav-pill{
                  display:inline-flex;align-items:center; padding:6px 14px;border-radius:999px;
                  font-size:clamp(11px,.85vw,14px); border:1px solid var(--line);background:rgba(255,255,255,.04);
                  color:var(--txt);text-decoration:none; white-space:nowrap;
                }
                .tv-nav-pill.gold{background:linear-gradient(135deg,var(--gold-1),var(--gold-2));color:#1a1200;border:none;font-weight:700}
                .tv-nav-pill.user{color:var(--gold-1);border-color:rgba(246,207,99,.35)}

                .tv-banner{margin-top:10px; padding:8px 16px; border-radius:12px;
                  background:rgba(255,107,120,.12); border:1px solid rgba(255,107,120,.3);
                  color:#ffd0d4; font-size:clamp(11px,.9vw,14px); text-align:center}

                .tv-main{flex:1; display:flex; flex-direction:column; gap:clamp(4px,0.5vw,10px); margin-top:clamp(10px,1.1vw,20px); min-height:0}
                .tv-block{display:flex; flex-direction:column; gap:clamp(8px,.8vw,14px); flex:1; min-height:0}
                .tv-section-title{display:flex; align-items:center; justify-content:space-between; gap:10px;
                  font-size:clamp(14px,1.15vw,20px); font-weight:700; color:var(--txt); margin:2px 4px}
                .tv-title-label{display:flex; align-items:center; gap:10px; min-width:0; flex-wrap:wrap}
                .tv-section-title .tv-bar{width:6px; height:20px; border-radius:6px; background:linear-gradient(var(--gold-1),var(--gold-2)); flex:none}
                .tv-section-title.silver .tv-bar{background:linear-gradient(var(--silver-1),var(--silver-2))}
                .tv-section-title .tv-tic{display:inline-flex; width:26px; height:26px; flex:none}
                .tv-section-title .tv-tic svg{width:100%; height:100%}
                .tv-tic.gold-ic{color:var(--gold-1)}
                .tv-tic.silver-ic{color:var(--silver-1)}
                .tv-ounce{flex:none; font-weight:800; font-variant-numeric:tabular-nums;
                  font-size:clamp(14px,1.2vw,22px); padding:4px 16px; border-radius:999px;
                  background:rgba(255,255,255,.04); border:1px solid var(--line); white-space:nowrap}
                .tv-ounce.gold-o{color:var(--gold-1)}
                .tv-ounce.silver-o{color:var(--silver-1)}
                .tv-ounce.up{color:var(--up); border-color:rgba(65,225,166,.45)}
                .tv-ounce.down{color:var(--down); border-color:rgba(255,107,120,.45)}

                .tv-grid{
                  display:grid; gap:clamp(10px,1.1vw,18px); flex:1; min-height:0;
                  grid-template-columns:repeat(auto-fit, minmax(150px,1fr));
                }

                .tv-card{
                  position:relative; overflow:hidden; cursor:default;
                  background:linear-gradient(160deg,var(--card),var(--card-2));
                  border:1px solid var(--line); border-radius:20px;
                  padding:clamp(12px,1.3vw,24px);
                  min-height:140px;
                  display:flex; flex-direction:column; justify-content:space-between;
                  box-shadow:0 10px 30px rgba(0,0,0,.25);
                  transition:border-color .45s ease, box-shadow .45s ease;
                }
                .tv-card:hover{border-color:var(--silver-1); box-shadow:0 0 0 1px var(--silver-1), 0 12px 34px rgba(0,0,0,.32)}
                .tv-card::before{content:""; position:absolute; inset:0 auto 0 0; width:6px; background:linear-gradient(var(--gold-1),var(--gold-2))}
                .tv-card.silver::before{background:linear-gradient(var(--silver-1),var(--silver-2))}
                .tv-card.usd::before{background:linear-gradient(var(--usd-1),var(--usd-2))}
                .tv-glow{position:absolute; inset:auto -40px -50px auto; width:160px;height:160px;border-radius:50%;
                  background:var(--gold-glow); filter:blur(30px); pointer-events:none}
                .tv-card.silver .tv-glow{background:var(--silver-glow)}
                .tv-card.usd .tv-glow{background:var(--usd-glow)}
                .tv-card.buyable{cursor:pointer}
                .tv-card.buyable:active{transform:scale(.985)}

                .tv-top{display:flex; align-items:center; gap:12px; min-width:0}
                .tv-top > div{min-width:0; overflow:hidden}
                .tv-icon{
                  width:clamp(36px,2.9vw,52px); height:clamp(36px,2.9vw,52px); flex:none;
                  border-radius:14px; display:grid; place-items:center;
                  background:linear-gradient(145deg,var(--gold-1),var(--gold-2)); color:#2a2107;
                  box-shadow:0 6px 18px var(--gold-glow);
                }
                .tv-card.silver .tv-icon{background:linear-gradient(145deg,var(--silver-1),var(--silver-2)); color:#222a37; box-shadow:0 6px 18px var(--silver-glow)}
                .tv-card.usd .tv-icon{background:linear-gradient(145deg,var(--usd-1),var(--usd-2)); color:#06281c; box-shadow:0 6px 18px var(--usd-glow)}
                .tv-icon svg{width:60%; height:60%}
                .tv-name{font-size:clamp(12px,1vw,18px); font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
                .tv-sub{font-size:clamp(9px,.72vw,12px); color:var(--muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
                /* دلار: نام بلند است، اجازه‌ی چندخطی شدن کامل */
                .tv-card.usd .tv-name{white-space:normal; font-size:clamp(11px,.9vw,15px); line-height:1.25}

                .tv-value{flex:1; min-height:0; display:flex; flex-direction:column; align-items:center; justify-content:center;
                  gap:2px; text-align:center; position:relative; padding-bottom:24px; width:100%}
                .tv-price{font-size:clamp(18px,2.3vw,44px); font-weight:800; line-height:1.2; font-variant-numeric:tabular-nums;
                  max-width:100%; overflow:hidden; text-overflow:ellipsis;
                  background:linear-gradient(180deg,#fff,#cfd8ea); -webkit-background-clip:text; background-clip:text; color:transparent}
                .tv-card.silver .tv-price{font-size:clamp(20px,2.1vw,40px)}
                .tv-unit{font-size:clamp(11px,.95vw,16px); color:var(--muted)}
                .tv-chg{position:absolute; bottom:2px; left:2px;
                  display:inline-flex; align-items:center; gap:5px; font-size:clamp(11px,1vw,18px); font-weight:800;
                  padding:3px 8px; border-radius:999px; background:rgba(255,255,255,.06); font-variant-numeric:tabular-nums;
                  max-width:95%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
                .tv-chg.up{color:var(--up)} .tv-chg.down{color:var(--down)}
                .tv-chg.zero{color:var(--muted)}
                .tv-card.gold .tv-chg.zero{color:var(--gold-1)}
                .tv-card.silver .tv-chg.zero{color:var(--silver-1)}
                .tv-card.usd .tv-chg.zero{color:var(--usd-1)}

                .tv-card.flash{animation:tvflash 1s ease}
                @keyframes tvflash{0%{box-shadow:0 0 0 2px var(--gold-1) inset, 0 10px 30px rgba(0,0,0,.25)}100%{box-shadow:0 10px 30px rgba(0,0,0,.25)}}

                .tv-ratio-bar{
                  display:flex; align-items:center; justify-content:center; gap:clamp(10px,1.4vw,22px);
                  align-self:center; margin-top:clamp(6px,0.7vw,14px);
                  padding:clamp(4px,.5vw,10px) clamp(10px,1.3vw,28px); border-radius:999px;
                  background:rgba(255,255,255,.04); border:1px solid var(--line);
                  font-size:clamp(9px,0.9vw,15px); font-weight:600; color:var(--muted);
                }
                .tv-ratio-bar .r-label{font-weight:700; color:var(--silver-2)}
                .tv-ratio-bar .r-val{font-size:clamp(9px,0.9vw,15px); font-weight:800; color:var(--gold-1); font-variant-numeric:tabular-nums}

                /* لپ‌تاپ/تبلت با عرض متوسط: فقط گرید کمی جمع‌تر می‌شود، هدر افقی می‌ماند */
                @media (max-width:1100px){
                  .tv-grid{grid-template-columns:repeat(auto-fit, minmax(130px,1fr))}
                }

                /* موبایل واقعی: اینجا هدر می‌شکند و ستونی می‌شود */
                @media (max-width:700px){
                  .tv-wrap{padding:10px}
                  .tv-wrap header{flex-wrap:wrap; gap:10px; padding:10px 12px}
                  .tv-headright{flex:1 1 100%; align-items:center; order:2; text-align:center}
                  .tv-brandc{flex:1 1 100%; order:1}
                  .tv-status{flex:1 1 100%; justify-content:center; order:3; flex-wrap:wrap; gap:10px}
                  .tv-clock{text-align:center}
                  .tv-shopname{font-size:26px; line-height:1.9; padding-block:.2em .3em}
                  .tv-htitle{font-size:15px} .tv-hsub{font-size:11px}
                  .tv-clock .t{font-size:20px} .tv-clock .d{font-size:11px}
                  .tv-main{gap:12px; margin-top:8px}
                  .tv-block{gap:6px}
                  .tv-grid{grid-template-columns:repeat(2,1fr); gap:10px}
                  .tv-card{min-height:108px; padding:12px; border-radius:16px}
                  .tv-top{gap:8px}
                  .tv-icon{width:30px; height:30px; border-radius:10px}
                  .tv-name{font-size:13px} .tv-sub{font-size:9px}
                  .tv-price{font-size:22px} .tv-card.silver .tv-price{font-size:20px}
                  .tv-unit{font-size:10px} .tv-chg{font-size:11px; padding:3px 9px; max-width:90%}
                  .tv-section-title{font-size:14px}
                  .tv-title-label{gap:8px}
                  .tv-ounce{font-size:12px; padding:3px 10px}
                }

                @media (max-width:360px){
                  .tv-grid{grid-template-columns:1fr}
                }
            `}</style>

            <div className="tv-wrap">
                <header>
                    <div className="tv-headright">
                        <div className="tv-htitle">تابلوی قیمت لحظه‌ای</div>
                        <div className="tv-hsub">طلا، سکه، نقره و دلار</div>
                    </div>

                    <div className="tv-brandc">
                        <div className="tv-shopname">آبشده صفرپور</div>
                    </div>

                    <div className="tv-status">
                        <div className="tv-clock">
                            <div className="t">{now.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}</div>
                            <div className="d">{now.toLocaleDateString('fa-IR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</div>
                        </div>
                        {user ? (
                            <>
                                <Link href="/profile" className="tv-nav-pill user">{user.name}</Link>
                                <Link href="/logout" method="post" as="button" className="tv-nav-pill">خروج</Link>
                            </>
                        ) : (
                            <>
                                <Link href="/login" className="tv-nav-pill">ورود</Link>
                                <Link href="/register" className="tv-nav-pill gold">ثبت‌نام</Link>
                            </>
                        )}
                        <div className="tv-live">
                            <span className={`tv-dot${online ? '' : ' off'}`} />
                            <span>{online ? 'آنلاین' : 'قطع ارتباط'}</span>
                        </div>
                    </div>
                </header>

                {data.errors?.length > 0 && (
                    <div className="tv-banner">{data.errors.join(' | ')}</div>
                )}

                <main className="tv-main">
                    {/* بالا: نقره + دلار */}
                    <div className="tv-block">
                        <div className="tv-section-title silver">
                            <div className="tv-title-label">
                                <span className="tv-bar" />
                                <span className="tv-tic silver-ic"><IngotIcon /></span>
                                نقره و دلار <span style={{ fontWeight: 400 }}>(فروش)</span>
                            </div>
                            <OunceDisplay value={data.ounce?.silver} label="انس نقره" cls="silver-o" />
                        </div>
                        <div className="tv-grid">
                            {SILVER.map(s => (
                                <PriceCard key={s.key} id={`card-silver-${s.key}`} name={s.name} sub={s.sub}
                                    icon={<IngotIcon />} cls="silver" buyable href={`/trade/${s.key}`}
                                    price={data.silver?.[s.key]} open={open[`silver.${s.key}`]} />
                            ))}
                            <PriceCard id="card-usd-price" name="دلار (خرید و فروش نداریم)" sub="هر دلار"
                                icon={<UsdIcon />} cls="usd" buyable={false}
                                price={data.dollar?.price} open={open['dollar.price']} />
                        </div>
                    </div>

                    {/* نرخ برابری طلا به نقره */}
                    <div className="tv-ratio-bar">
                        <span className="r-label">نرخ برابری طلا / نقره ۹۹۹</span>
                        <span className="r-val">{ratio}</span>
                        <span>گرم نقره به ازای هر گرم طلا</span>
                    </div>

                    {/* پایین: طلا و سکه */}
                    <div className="tv-block">
                        <div className="tv-section-title">
                            <div className="tv-title-label">
                                <span className="tv-bar" />
                                <span className="tv-tic gold-ic"><CoinIcon /></span>
                                طلا و سکه <span style={{ fontWeight: 400 }}>(فروش)</span>
                            </div>
                            <OunceDisplay value={data.ounce?.gold} label="انس طلا" cls="gold-o" />
                        </div>
                        <div className="tv-grid">
                            {GOLD.map(g => (
                                <PriceCard key={g.key} id={`card-gold-${g.key}`} name={g.name} sub={g.sub}
                                    icon={<CoinIcon />} cls="gold" buyable href={`/trade/${g.key}`}
                                    price={data.gold?.[g.key]} open={open[`gold.${g.key}`]} />
                            ))}
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
