import { useRef, useState } from 'react';
import AppLayout, { faNum } from '../Layouts/AppLayout';

// تست سرعت سمت کاربر — دانلود فایل تصادفی و اندازه‌گیری زمان.
// از فایل‌های CDN با اندازه مشخص استفاده می‌کنیم.
const TEST_FILES = [
    { url: 'https://speed.cloudflare.com/__down?bytes=10000000', bytes: 10_000_000 },
    { url: 'https://speed.cloudflare.com/__down?bytes=25000000', bytes: 25_000_000 },
];

export default function SpeedTest() {
    const [phase, setPhase] = useState('idle'); // idle | ping | download | done | error
    const [ping, setPing] = useState(null);
    const [speed, setSpeed] = useState(0);       // Mbps فعلی
    const [maxSpeed, setMaxSpeed] = useState(0); // بهترین نتیجه
    const [progress, setProgress] = useState(0);
    const abortRef = useRef(null);

    async function measurePing() {
        const samples = [];
        for (let i = 0; i < 4; i++) {
            const t0 = performance.now();
            try {
                await fetch('https://speed.cloudflare.com/__down?bytes=0', { cache: 'no-store' });
                samples.push(performance.now() - t0);
            } catch { /* ignore */ }
        }
        if (!samples.length) return null;
        return Math.round(samples.reduce((a, b) => a + b, 0) / samples.length);
    }

    async function runTest() {
        setPhase('ping');
        setSpeed(0); setMaxSpeed(0); setProgress(0); setPing(null);

        const p = await measurePing();
        setPing(p);

        setPhase('download');
        const controller = new AbortController();
        abortRef.current = controller;

        try {
            for (const file of TEST_FILES) {
                const t0 = performance.now();
                const res = await fetch(file.url + '&t=' + Date.now(), {
                    cache: 'no-store',
                    signal: controller.signal,
                });
                const reader = res.body.getReader();
                let received = 0;

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    received += value.length;
                    const elapsed = (performance.now() - t0) / 1000;
                    const mbps = (received * 8) / elapsed / 1_000_000;
                    setSpeed(mbps);
                    setMaxSpeed(prev => Math.max(prev, mbps));
                    setProgress(Math.min(100, (received / file.bytes) * 100));
                }
            }
            setPhase('done');
        } catch (e) {
            if (e.name === 'AbortError') {
                setPhase('idle');
            } else {
                setPhase('error');
            }
        }
    }

    function stopTest() {
        abortRef.current?.abort();
    }

    const isRunning = phase === 'ping' || phase === 'download';
    const display = isRunning ? speed : maxSpeed;

    return (
        <AppLayout>
            <div className="page">
                <div className="fcard" style={{ textAlign: 'center' }}>
                    <h2 style={{ justifyContent: 'center' }}>⚡ تست سرعت اینترنت</h2>
                    <div style={{ height: 24 }} />

                    {/* عقربه/نمایشگر سرعت */}
                    <div style={{
                        width: 200, height: 200, margin: '0 auto 24px',
                        borderRadius: '50%', position: 'relative',
                        background: `conic-gradient(var(--gold-1) ${Math.min(display / 100 * 360, 360)}deg, rgba(255,255,255,.06) 0deg)`,
                        display: 'grid', placeItems: 'center',
                    }}>
                        <div style={{
                            width: 168, height: 168, borderRadius: '50%',
                            background: 'var(--card)', display: 'grid', placeItems: 'center',
                        }}>
                            <div>
                                <div style={{ fontSize: 44, fontWeight: 900, color: 'var(--gold-1)', lineHeight: 1 }}>
                                    {display.toFixed(1).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d])}
                                </div>
                                <div style={{ fontSize: 13, color: 'var(--muted)', marginTop: 4 }}>Mbps</div>
                            </div>
                        </div>
                    </div>

                    {/* پینگ */}
                    {ping != null && (
                        <div style={{ marginBottom: 16, fontSize: 14, color: 'var(--muted)' }}>
                            پینگ: <strong style={{ color: 'var(--txt)' }}>{faNum(ping)} ms</strong>
                        </div>
                    )}

                    {/* نوار پیشرفت */}
                    {phase === 'download' && (
                        <div style={{ height: 6, background: 'rgba(255,255,255,.08)', borderRadius: 999, overflow: 'hidden', marginBottom: 20 }}>
                            <div style={{ height: '100%', width: `${progress}%`, background: 'linear-gradient(90deg,var(--gold-1),var(--gold-2))', transition: 'width .2s' }} />
                        </div>
                    )}

                    {/* وضعیت */}
                    <div style={{ marginBottom: 20, fontSize: 14, color: 'var(--muted)', minHeight: 20 }}>
                        {phase === 'idle' && 'برای شروع، دکمه را بزنید'}
                        {phase === 'ping' && 'در حال اندازه‌گیری پینگ...'}
                        {phase === 'download' && 'در حال تست سرعت دانلود...'}
                        {phase === 'done' && `✅ بیشترین سرعت: ${maxSpeed.toFixed(1).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d])} Mbps`}
                        {phase === 'error' && '❌ خطا در اجرای تست. اتصال اینترنت را بررسی کنید.'}
                    </div>

                    {/* دکمه */}
                    {isRunning ? (
                        <button className="btn btn-outline" onClick={stopTest}>توقف</button>
                    ) : (
                        <button className="btn" onClick={runTest}>
                            {phase === 'done' || phase === 'error' ? 'تست مجدد' : 'شروع تست'}
                        </button>
                    )}

                    <div style={{ marginTop: 16, fontSize: 11, color: 'var(--muted)', opacity: .6 }}>
                        نتیجه تقریبی است و به سرور آزمایش (Cloudflare) بستگی دارد.
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
