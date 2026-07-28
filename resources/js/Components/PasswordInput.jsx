import { useState } from 'react';

function EyeIcon({ visible }) {
    return visible ? (
        <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 3l18 18" />
            <path d="M10.6 10.6a2 2 0 002.8 2.8" />
            <path d="M9.9 4.2A10.8 10.8 0 0112 4c5.5 0 9 5 9 8a10.8 10.8 0 01-2 3.6" />
            <path d="M6.6 6.6C4.3 8 3 10.2 3 12c0 3 3.5 8 9 8 1.2 0 2.3-.2 3.3-.7" />
        </svg>
    ) : (
        <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 12c0-3 3.5-8 9-8s9 5 9 8-3.5 8-9 8-9-5-9-8z" />
            <circle cx="12" cy="12" r="2.5" />
        </svg>
    );
}

export default function PasswordInput({ style = {}, ...props }) {
    const [visible, setVisible] = useState(false);
    const { width = '100%', ...inputStyle } = style;
    const label = visible ? 'مخفی کردن رمز عبور' : 'نمایش رمز عبور';

    return (
        <div style={{ position: 'relative', width, maxWidth: '100%' }}>
            <input
                {...props}
                type={visible ? 'text' : 'password'}
                style={{ ...inputStyle, width: '100%', paddingInlineEnd: 52 }}
            />
            <button
                type="button"
                className="password-toggle"
                aria-label={label}
                aria-pressed={visible}
                title={label}
                onClick={() => setVisible(value => !value)}
                style={{
                    position: 'absolute', insetInlineEnd: 2, top: '50%', transform: 'translateY(-50%)',
                    width: 44, height: 44, display: 'grid', placeItems: 'center', padding: 0,
                    border: 0, borderRadius: 10, background: 'transparent', color: 'var(--muted)',
                    cursor: 'pointer', transition: 'color .2s ease, background .2s ease',
                }}
            >
                <EyeIcon visible={visible} />
            </button>
        </div>
    );
}
