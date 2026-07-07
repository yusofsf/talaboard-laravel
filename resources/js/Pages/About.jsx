import { Link } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

const fallbackBody = [
    'آبشده صفرپور مرجع قیمت لحظه‌ای طلا، نقره و سکه و محل مطمئن خرید و فروش آنلاین فلزات گران‌بها است.',
    'برای [خرید و فروش طلای آبشده](/trade/geram)، [خرید و فروش نقره عیار ۹۹۹](/trade/gram_999)، [نقره عیار ۹۹۵](/trade/gram_995)، [سکه تمام بهار آزادی](/trade/bahar)، [نیم سکه](/trade/nim) و [ربع سکه](/trade/rob) می‌توانید همین حالا با بهترین قیمت اقدام کنید.',
].join('\n\n');

function paragraphs(text) {
    return String(text || '').split(/\n{2,}/).map(p => p.trim()).filter(Boolean);
}

function renderInlineLinks(text) {
    const parts = [];
    const pattern = /\[([^\]]+)]\((\/[^)\s]+)\)/g;
    let lastIndex = 0;
    let match;

    while ((match = pattern.exec(text)) !== null) {
        if (match.index > lastIndex) {
            parts.push(text.slice(lastIndex, match.index));
        }

        parts.push(
            <Link key={`${match[2]}-${match.index}`} href={match[2]}>
                {match[1]}
            </Link>
        );
        lastIndex = pattern.lastIndex;
    }

    if (lastIndex < text.length) {
        parts.push(text.slice(lastIndex));
    }

    return parts;
}

export default function About({ content }) {
    const title = content?.title || 'خرید و فروش آنلاین طلا، نقره و سکه';
    const body = content?.body || fallbackBody;
    const bodyParagraphs = paragraphs(body);

    return (
        <AppLayout>
            <main className="page">
                <section className="fcard">
                    <h1 style={{ fontSize: 28, margin: '0 0 14px' }}>{title}</h1>
                    <div style={{ color: 'var(--muted)', lineHeight: 2.2, fontSize: 15, textAlign: 'justify' }}>
                        {bodyParagraphs.map((p, i) => (
                            <p key={i}>{renderInlineLinks(p)}</p>
                        ))}
                    </div>
                </section>
            </main>
        </AppLayout>
    );
}
