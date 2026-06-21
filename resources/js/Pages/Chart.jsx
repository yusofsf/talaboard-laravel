import { useState } from 'react';
import AppLayout from '../Layouts/AppLayout';
import TradingViewChart from '../Components/TradingViewChart';

const ASSETS = {
    gold:   { symbol: 'OANDA:XAUUSD', label: 'طلا (XAU/USD)', fallback: 'https://www.tgju.org/chart/geram18' },
    silver: { symbol: 'OANDA:XAGUSD', label: 'نقره (XAG/USD)', fallback: 'https://www.tgju.org/chart/silver' },
};

export default function Chart() {
    const [asset, setAsset] = useState('gold');

    return (
        <AppLayout>
            <div className="page-wide">
                <div className="fcard">
                    <h2>📈 چارت قیمت</h2>
                    <div style={{ height: 20 }} />

                    <div className="btn-row" style={{ width: 'auto', display: 'inline-flex', gap: 8, marginBottom: 18 }}>
                        {Object.entries(ASSETS).map(([key, a]) => (
                            <button key={key} type="button" onClick={() => setAsset(key)}
                                style={{
                                    padding: '9px 22px', borderRadius: 10, fontFamily: 'inherit',
                                    fontSize: 14, fontWeight: 700, cursor: 'pointer', border: 'none',
                                    background: asset === key ? 'rgba(246,207,99,.2)' : 'rgba(255,255,255,.06)',
                                    color: asset === key ? 'var(--gold-1)' : 'var(--muted)',
                                    outline: asset === key ? '2px solid var(--gold-1)' : '2px solid transparent',
                                }}>
                                {a.label}
                            </button>
                        ))}
                    </div>

                    <TradingViewChart key={asset} symbol={ASSETS[asset].symbol} height={500} fallbackUrl={ASSETS[asset].fallback} />
                </div>
            </div>
        </AppLayout>
    );
}
