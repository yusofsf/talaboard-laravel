import AppLayout from '../Layouts/AppLayout';

const fallbackBody = [
    'آبشده صفرپور مرجع قیمت لحظه‌ای طلا، نقره و سکه و محل مطمئن خرید و فروش آنلاین فلزات گران‌بها است.',
    'برای خرید و فروش طلا، نقره و سکه می‌توانید از صفحه معامله اقدام کنید.',
].join('\n\n');

function paragraphs(text) {
    return String(text || '').split(/\n{2,}/).map(p => p.trim()).filter(Boolean);
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
                            <p key={i}>{p}</p>
                        ))}
                    </div>
                </section>
            </main>
        </AppLayout>
    );
}
