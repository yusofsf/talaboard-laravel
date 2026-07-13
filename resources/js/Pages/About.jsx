import { Fragment } from 'react';
import AppLayout from '../Layouts/AppLayout';

const fallbackBody = [
    '**آبشده صفرپور** مرجع **قیمت لحظه‌ای طلا، نقره و سکه** و محل مطمئن **خرید نقره** و **فروش نقره**، **خرید طلا** و **فروش طلا**، و خرید و فروش انواع **سکه** است. در این تابلو نرخ روز **نقره آبشده**، **نقره عیار ۹۹۹** و **نقره عیار ۹۹۵**(عیار ۹۹۹ و عیار ۹۹۵)، **ساچمه نقره**، **سکه تمام** (بهار آزادی)، **نیم سکه** و **ربع سکه** به‌صورت زنده نمایش داده می‌شود.',
    'برای [خرید و فروش طلای آبشده](https://metalsp.ir/trade/geram)، [خرید و فروش نقره عیار ۹۹۹](https://metalsp.ir/trade/gram_999)، [نقره عیار ۹۹۵](https://metalsp.ir/trade/gram_995)، [سکه تمام بهار آزادی](https://metalsp.ir/trade/bahar)، [نیم سکه](https://metalsp.ir/trade/nim) و [ربع سکه](https://metalsp.ir/trade/rob) می‌توانید همین حالا با بهترین قیمت اقدام کنید.',
    'نقره، طلا، خرید نقره، خرید طلا، فروش نقره، فروش طلا، سکه، خرید سکه، فروش سکه، سکه تمام، ربع سکه، نیم سکه، ساچمه، صفرپور، نقره آبشده، نقره عیار، نقره عیار ۹۹۵، نقره عیار ۹۹۹، عیار ۹۹۵، عیار ۹۹۹.',
].join('\n\n');

function paragraphs(text) {
    return String(text || '').split(/\n{2,}/).map(p => p.trim()).filter(Boolean);
}

function renderInline(text) {
    const nodes = [];
    const pattern = /(\*\*([^*]+)\*\*)|(\[([^\]]+)]\((https?:\/\/[^)\s]+)\))/g;
    let lastIndex = 0;
    let match;

    while ((match = pattern.exec(text)) !== null) {
        if (match.index > lastIndex) {
            nodes.push(text.slice(lastIndex, match.index));
        }

        if (match[2]) {
            nodes.push(<strong key={`strong-${match.index}`}>{match[2]}</strong>);
        } else if (match[4] && match[5]) {
            nodes.push(
                <a key={`link-${match.index}`} href={match[5]}>
                    {match[4]}
                </a>
            );
        }

        lastIndex = pattern.lastIndex;
    }

    if (lastIndex < text.length) {
        nodes.push(text.slice(lastIndex));
    }

    return nodes.map((node, index) => <Fragment key={index}>{node}</Fragment>);
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
                        {bodyParagraphs.map((paragraph, index) => (
                            <p key={index}>{renderInline(paragraph)}</p>
                        ))}
                    </div>
                </section>
            </main>
        </AppLayout>
    );
}
