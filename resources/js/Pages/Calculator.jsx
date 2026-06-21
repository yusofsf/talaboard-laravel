import { useEffect, useState } from 'react';
import AppLayout from '../Layouts/AppLayout';

const FA = s => String(s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** نمایش عدد با جداکننده‌ی هزارگان و ارقام فارسی (بدون از دست رفتن اعشار). */
function pretty(str) {
    if (str === 'Error') return 'خطا';
    const neg = str.startsWith('-');
    const s = neg ? str.slice(1) : str;
    const [intPart, decPart] = s.split('.');
    const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '٬');
    return (neg ? '-' : '') + FA(grouped + (decPart !== undefined ? '.' + FA(decPart) : ''));
}

export default function Calculator() {
    const [display, setDisplay] = useState('0');   // عملوند فعلی (رشته)
    const [acc, setAcc] = useState(null);           // انباشته
    const [op, setOp] = useState(null);             // عملگر در انتظار
    const [fresh, setFresh] = useState(true);       // آیا ورودی بعدی عملوند جدید را شروع می‌کند

    const round = n => Math.round((n + Number.EPSILON) * 1e8) / 1e8;

    function inputDigit(d) {
        if (display === 'Error') return clearAll();
        if (fresh) { setDisplay(d === '.' ? '0.' : d); setFresh(false); return; }
        if (d === '.' && display.includes('.')) return;
        if (display === '0' && d !== '.') { setDisplay(d); return; }
        if (display.replace(/[-.]/g, '').length >= 14) return; // سقف طول
        setDisplay(display + d);
    }

    function compute(a, b, operator) {
        switch (operator) {
            case '+': return a + b;
            case '-': return a - b;
            case '×': return a * b;
            case '÷': return b === 0 ? null : a / b;
            default: return b;
        }
    }

    function chooseOp(nextOp) {
        if (display === 'Error') return;
        const cur = parseFloat(display);
        if (acc === null) {
            setAcc(cur);
        } else if (!fresh) {
            const r = compute(acc, cur, op);
            if (r === null) return reset('Error');
            setAcc(round(r));
            setDisplay(String(round(r)));
        }
        setOp(nextOp);
        setFresh(true);
    }

    function equals() {
        if (op === null || acc === null || display === 'Error') return;
        const cur = parseFloat(display);
        const r = compute(acc, cur, op);
        if (r === null) return reset('Error');
        setDisplay(String(round(r)));
        setAcc(null);
        setOp(null);
        setFresh(true);
    }

    function percent() {
        if (display === 'Error') return;
        const cur = parseFloat(display);
        const base = acc !== null ? acc : 1;
        setDisplay(String(round((base * cur) / 100)));
        setFresh(true);
    }

    function toggleSign() {
        if (display === 'Error' || display === '0') return;
        setDisplay(display.startsWith('-') ? display.slice(1) : '-' + display);
    }

    function backspace() {
        if (display === 'Error' || fresh) return;
        const next = display.length <= 1 || (display.length === 2 && display.startsWith('-')) ? '0' : display.slice(0, -1);
        setDisplay(next);
    }

    function clearAll() { setDisplay('0'); setAcc(null); setOp(null); setFresh(true); }
    function reset(val) { setDisplay(val); setAcc(null); setOp(null); setFresh(true); }

    // پشتیبانی از کیبورد
    useEffect(() => {
        function onKey(e) {
            const k = e.key;
            if (k >= '0' && k <= '9') inputDigit(k);
            else if (k === '.') inputDigit('.');
            else if (k === '+' || k === '-') chooseOp(k);
            else if (k === '*') chooseOp('×');
            else if (k === '/') { e.preventDefault(); chooseOp('÷'); }
            else if (k === 'Enter' || k === '=') { e.preventDefault(); equals(); }
            else if (k === '%') percent();
            else if (k === 'Backspace') backspace();
            else if (k === 'Escape') clearAll();
        }
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });

    const Key = ({ label, onClick, variant, wide }) => (
        <button onClick={onClick} className={`calc-key${variant ? ' ' + variant : ''}${wide ? ' wide' : ''}`}>
            {label}
        </button>
    );

    return (
        <AppLayout>
            <div className="page">
                <div className="fcard">
                    <h2>🧮 ماشین حساب</h2>
                    <div style={{ height: 16 }} />

                    <div style={{
                        background: 'rgba(0,0,0,.25)', border: '1px solid var(--line)', borderRadius: 16,
                        padding: '18px 20px', marginBottom: 16, minHeight: 92, textAlign: 'left',
                    }}>
                        <div style={{ minHeight: 20, fontSize: 14, color: 'var(--muted)', direction: 'ltr' }}>
                            {acc !== null ? `${pretty(String(acc))} ${op || ''}` : ' '}
                        </div>
                        <div dir="ltr" style={{
                            fontSize: 40, fontWeight: 800, color: display === 'Error' ? 'var(--down)' : 'var(--txt)',
                            lineHeight: 1.2, overflowWrap: 'anywhere', fontVariantNumeric: 'tabular-nums',
                        }}>
                            {pretty(display)}
                        </div>
                    </div>

                    <div className="calc-grid">
                        <Key label="C" variant="muted" onClick={clearAll} />
                        <Key label="±" variant="muted" onClick={toggleSign} />
                        <Key label="٪" variant="muted" onClick={percent} />
                        <Key label="÷" variant="op" onClick={() => chooseOp('÷')} />

                        <Key label="۷" onClick={() => inputDigit('7')} />
                        <Key label="۸" onClick={() => inputDigit('8')} />
                        <Key label="۹" onClick={() => inputDigit('9')} />
                        <Key label="×" variant="op" onClick={() => chooseOp('×')} />

                        <Key label="۴" onClick={() => inputDigit('4')} />
                        <Key label="۵" onClick={() => inputDigit('5')} />
                        <Key label="۶" onClick={() => inputDigit('6')} />
                        <Key label="−" variant="op" onClick={() => chooseOp('-')} />

                        <Key label="۱" onClick={() => inputDigit('1')} />
                        <Key label="۲" onClick={() => inputDigit('2')} />
                        <Key label="۳" onClick={() => inputDigit('3')} />
                        <Key label="+" variant="op" onClick={() => chooseOp('+')} />

                        <Key label="۰" wide onClick={() => inputDigit('0')} />
                        <Key label="٫" onClick={() => inputDigit('.')} />
                        <Key label="=" variant="eq" onClick={equals} />
                    </div>

                    <div className="calc-foot">
                        <button className="calc-key muted" style={{ flex: 1 }} onClick={backspace}>⌫ حذف</button>
                    </div>
                    <p style={{ textAlign: 'center', color: 'var(--muted)', fontSize: 12, marginTop: 14 }}>
                        با صفحه‌کلید هم کار می‌کند: اعداد، + − × ÷، Enter، Backspace، Esc
                    </p>
                </div>
            </div>

            <style>{`
                .calc-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
                .calc-foot { margin-top: 10px; display: flex; }
                .calc-key {
                    height: 60px; border-radius: 16px; border: 1px solid var(--line);
                    background: rgba(255,255,255,.06); color: var(--txt);
                    font-family: inherit; font-size: 22px; font-weight: 700; cursor: pointer;
                    transition: background .12s, transform .06s, filter .12s;
                }
                .calc-key:hover { background: rgba(255,255,255,.12); }
                .calc-key:active { transform: scale(.95); }
                .calc-key.muted { background: rgba(255,255,255,.04); color: var(--muted); font-size: 19px; }
                .calc-key.op { background: rgba(246,207,99,.14); color: var(--gold-1); border-color: rgba(246,207,99,.3); }
                .calc-key.op:hover { background: rgba(246,207,99,.24); }
                .calc-key.eq { background: linear-gradient(135deg,var(--gold-1),var(--gold-2)); color: #1a1200; border: none; }
                .calc-key.eq:hover { filter: brightness(1.08); }
                .calc-key.wide { grid-column: span 2; }
            `}</style>
        </AppLayout>
    );
}
