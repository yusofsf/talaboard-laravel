import { useEffect, useId, useRef, useState } from 'react';

let scriptPromise = null;
function loadTvScript() {
    if (window.TradingView) return Promise.resolve();
    if (scriptPromise) return scriptPromise;
    scriptPromise = new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://s3.tradingview.com/tv.js';
        s.async = true;
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
    });
    return scriptPromise;
}

export default function TradingViewChart({ symbol, height = 420, fallbackUrl }) {
    const rawId = useId();
    const containerId = 'tv-chart-' + rawId.replace(/[:]/g, '');
    const widgetRef = useRef(null);
    const [status, setStatus] = useState('loading'); // loading | ok | failed

    useEffect(() => {
        let cancelled = false;
        setStatus('loading');

        const timeout = setTimeout(() => {
            if (!cancelled) setStatus('failed');
        }, 8000);

        loadTvScript().then(() => {
            if (cancelled) return;
            clearTimeout(timeout);
            widgetRef.current = new window.TradingView.widget({
                autosize: true,
                symbol,
                interval: '60',
                timezone: 'Asia/Tehran',
                theme: 'dark',
                style: '1',
                locale: 'fa_IR',
                toolbar_bg: '#171f3f',
                enable_publishing: false,
                hide_top_toolbar: false,
                hide_legend: false,
                save_image: false,
                container_id: containerId,
            });
            setStatus('ok');
        }).catch(() => {
            if (!cancelled) setStatus('failed');
        });

        return () => { cancelled = true; clearTimeout(timeout); };
    }, [symbol, containerId]);

    return (
        <div style={{ position: 'relative' }}>
            <div
                id={containerId}
                style={{
                    height, width: '100%', borderRadius: 14,
                    overflow: 'hidden', border: '1px solid var(--line)',
                    display: status === 'failed' ? 'none' : 'block',
                }}
            />
            {status === 'loading' && (
                <div style={{ textAlign: 'center', padding: '40px 0', color: 'var(--muted)', fontSize: 13 }}>
                    در حال بارگذاری چارت...
                </div>
            )}
            {status === 'failed' && (
                fallbackUrl ? (
                    <div>
                        <div className="alert info" style={{ textAlign: 'center', marginBottom: 10 }}>
                            چارت TradingView بارگذاری نشد؛ نسخه‌ی جایگزین از TGJU نمایش داده می‌شود.
                        </div>
                        <iframe
                            title="چارت TGJU"
                            src={fallbackUrl}
                            style={{ width: '100%', height, border: '1px solid var(--line)', borderRadius: 14, background: '#fff' }}
                            loading="lazy"
                        />
                        <div style={{ textAlign: 'center', marginTop: 8 }}>
                            <a href={fallbackUrl} target="_blank" rel="noopener noreferrer" style={{ color: 'var(--gold-1)', fontWeight: 700, fontSize: 13 }}>
                                اگر چارت نمایش داده نشد، اینجا در TGJU باز کنید ↗
                            </a>
                        </div>
                    </div>
                ) : (
                    <div className="alert err" style={{ textAlign: 'center' }}>
                        چارت TradingView بارگذاری نشد (ممکن است از ایران در دسترس نباشد).
                    </div>
                )
            )}
        </div>
    );
}
