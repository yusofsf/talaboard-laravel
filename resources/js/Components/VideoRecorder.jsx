import { useEffect, useRef, useState } from 'react';

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

    function stopStream() {
        streamRef.current?.getTracks().forEach(t => t.stop());
        streamRef.current = null;
        clearInterval(timerRef.current);
    }

    async function openCamera() {
        setError('');
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
            streamRef.current = stream;
            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                videoRef.current.play();
            }
            setPhase('live');
        } catch (e) {
            setError('دسترسی به دوربین ممکن نشد. مرورگر یا دستگاه شما اجازه‌ی دسترسی به دوربین/میکروفون را نداد.');
            setPhase('error');
        }
    }

    function startRecording() {
        if (!streamRef.current) return;
        chunksRef.current = [];
        const mr = new MediaRecorder(streamRef.current, { mimeType: 'video/webm' });
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
            {error && <div className="alert err" style={{ marginBottom: 12 }}>{error}</div>}

            {phase === 'idle' && (
                <button type="button" className="btn" onClick={openCamera} style={{ width: 'auto', padding: '10px 24px' }}>
                    🎥 باز کردن دوربین و ضبط فیلم
                </button>
            )}

            {(phase === 'live' || phase === 'recording') && (
                <div>
                    <video ref={videoRef} muted playsInline
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
