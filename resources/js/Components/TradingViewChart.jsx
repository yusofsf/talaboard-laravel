import { useEffect, useId, useRef } from 'react';

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

export default function TradingViewChart({ symbol, height = 420 }) {
    const rawId = useId();
    const containerId = 'tv-chart-' + rawId.replace(/[:]/g, '');
    const widgetRef = useRef(null);

    useEffect(() => {
        let cancelled = false;
        loadTvScript().then(() => {
            if (cancelled) return;
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
        });
        return () => { cancelled = true; };
    }, [symbol, containerId]);

    return (
        <div
            id={containerId}
            style={{
                height, width: '100%', borderRadius: 14,
                overflow: 'hidden', border: '1px solid var(--line)',
            }}
        />
    );
}
