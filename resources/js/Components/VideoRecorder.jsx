import { useEffect, useRef, useState } from 'react';

// محدودیت‌های پایین‌تر برای ضبط، تا حجم فایل خام از همان ابتدا کوچک بماند
const VIDEO_CONSTRAINTS = { width: { ideal: 640 }, height: { ideal: 480 }, frameRate: { ideal: 24 } };
const RECORDER_OPTIONS = { mimeType: 'video/webm;codecs=vp8,opus', videoBitsPerSecond: 600_000, audioBitsPerSecond: 64_000 };

export default function VideoRecorder({ onRecorded, maxSeconds = 30 }) {
    const videoRef = useRef(null);
    const mediaRecorderRef = useRef(null);
    const chunksRef = useRef([]);
    const streamRef = useRef(null);
    const timerRef = useRef(null);

    const [phase, setPhase] = useState('idle'); // idle | live | recording | preview | error
    const [seconds, setSeconds] = useState(0);
    const [error, setError] = useState('');
    const [previewUrl, setPreviewUrl] = useState(null);

    useEffect(() => () => stopStream(), []);

    // المنت <video> فقط وقتی phase به live برسد رندر می‌شود، پس اتصال استریم باید
    // *بعد* از آن رندر انجام شود، نه داخل openCamera (وگرنه ref هنوز null است و صفحه سیاه می‌ماند).
    useEffect(() => {
        if (phase === 'live' && videoRef.current && streamRef.current) {
            videoRef.current.srcObject = streamRef.current;
            videoRef.current.play().catch(() => {});
        }
    }, [phase]);

    function stopStream() {
        streamRef.current?.getTracks().forEach(t => t.stop());
        streamRef.current = null;
        clearInterval(timerRef.current);
    }

    async function openCamera() {
        setError('');
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: VIDEO_CONSTRAINTS, audio: true });
            streamRef.current = stream;
            setPhase('live');
        } catch (e) {
            setError('دسترسی به دوربین ممکن نشد. مرورگر یا دستگاه شما اجازه‌ی دسترسی به دوربین/میکروفون را نداد.');
            setPhase('error');
        }
    }

    function startRecording() {
        if (!streamRef.current) return;
        chunksRef.current = [];
        let mr;
        try {
            mr = new MediaRecorder(streamRef.current, RECORDER_OPTIONS);
        } catch {
            mr = new MediaRecorder(streamRef.current); // اگر مرورگر این codec/bitrate را نپذیرفت
        }
        mr.ondataavailable = e => { if (e.data.size > 0) chunksRef.current.push(e.data); };
        mr.onstop = () => {
            const blob = new Blob(chunksRef.current, { type: 'video/webm' });
            const file = new File([blob], 'verification.webm', { type: 'video/webm' });
            const url = URL.createObjectURL(blob);
            setPreviewUrl(url);
            onRecorded(file);
            stopStream();
            setPhase('preview');
        };
        mediaRecorderRef.current = mr;
        mr.start();
        setSeconds(0);
        setPhase('recording');
        timerRef.current = setInterval(() => {
            setSeconds(s => {
                if (s + 1 >= maxSeconds) {
                    mediaRecorderRef.current?.stop();
                    clearInterval(timerRef.current);
                }
                return s + 1;
            });
        }, 1000);
    }

    function stopRecording() {
        mediaRecorderRef.current?.stop();
        clearInterval(timerRef.current);
    }

    function reset() {
        setPreviewUrl(null);
        setPhase('idle');
        onRecorded(null);
    }

    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 16, background: 'rgba(255,255,255,.03)' }}>
            <div className="alert info" style={{ fontSize: 13, marginBottom: 12 }}>
                لطفاً <strong>صورت خود را به‌طور کامل و واضح</strong> در کادر دوربین قرار دهید، در محیطی با نور کافی،
                و در صورت امکان با <strong>پس‌زمینه‌ی ساده و سفید</strong> (مثلاً دیوار سفید) فیلم بگیرید.
            </div>

            {error && <div className="alert err" style={{ marginBottom: 12 }}>{error}</div>}

            {phase === 'idle' && (
                <button type="button" className="btn" onClick={openCamera} style={{ width: 'auto', padding: '10px 24px' }}>
                    🎥 باز کردن دوربین و ضبط فیلم
                </button>
            )}

            {(phase === 'live' || phase === 'recording') && (
                <div>
                    <video ref={videoRef} muted autoPlay playsInline
                        style={{ width: '100%', maxHeight: 360, borderRadius: 10, background: '#000', transform: 'scaleX(-1)' }} />
                    <div style={{ marginTop: 12, display: 'flex', alignItems: 'center', gap: 12 }}>
                        {phase === 'live' && (
                            <button type="button" className="btn" onClick={startRecording} style={{ width: 'auto', padding: '9px 22px', background: 'linear-gradient(135deg,var(--down),#c93c46)' }}>
                                ⏺ شروع ضبط
                            </button>
                        )}
                        {phase === 'recording' && (
                            <>
                                <button type="button" className="btn-outline btn" onClick={stopRecording} style={{ width: 'auto', padding: '9px 22px' }}>
                                    ⏹ توقف ضبط
                                </button>
                                <span style={{ color: 'var(--down)', fontWeight: 700 }}>
                                    در حال ضبط... {seconds}/{maxSeconds} ثانیه
                                </span>
                            </>
                        )}
                    </div>
                </div>
            )}

            {phase === 'preview' && previewUrl && (
                <div>
                    <video src={previewUrl} controls style={{ width: '100%', maxHeight: 360, borderRadius: 10, background: '#000' }} />
                    <div style={{ marginTop: 12 }}>
                        <button type="button" className="btn btn-outline" onClick={reset} style={{ width: 'auto', padding: '9px 22px' }}>
                            🔄 ضبط دوباره
                        </button>
                    </div>
                </div>
            )}

            {phase === 'error' && (
                <button type="button" className="btn" onClick={openCamera} style={{ width: 'auto', padding: '10px 24px' }}>
                    تلاش دوباره
                </button>
            )}
        </div>
    );
}
