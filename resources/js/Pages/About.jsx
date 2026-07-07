import { Link } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

export default function About() {
    return (
        <AppLayout>
            <main className="page">
                <section className="fcard">
                    <h1 style={{ fontSize: 28, margin: '0 0 14px' }}>خرید و فروش آنلاین طلا، نقره و سکه</h1>
                    <div style={{ color: 'var(--muted)', lineHeight: 2.2, fontSize: 15, textAlign: 'justify' }}>
                        <p>
                            آبشده صفرپور مرجع قیمت لحظه‌ای طلا، نقره و سکه و محل مطمئن خرید نقره و فروش نقره، خرید طلا و فروش طلا، و خرید و فروش انواع سکه است. در این تابلو نرخ روز نقره آبشده، نقره عیار ۹۹۹ و نقره عیار ۹۹۵(عیار ۹۹۹ و عیار ۹۹۵)، ساچمه نقره، سکه تمام (بهار آزادی)، نیم سکه و ربع سکه به‌صورت زنده نمایش داده می‌شود.
                        </p>
                        <p>
                            برای <Link href="/trade/geram">خرید و فروش طلای آبشده</Link>، <Link href="/trade/gram_999">خرید و فروش نقره عیار ۹۹۹</Link>، <Link href="/trade/gram_995">نقره عیار ۹۹۵</Link>، <Link href="/trade/bahar">سکه تمام بهار آزادی</Link>، <Link href="/trade/nim">نیم سکه</Link> و <Link href="/trade/rob">ربع سکه</Link> می‌توانید همین حالا با بهترین قیمت اقدام کنید.
                        </p>
                        <p style={{ color: 'var(--gold-1)', fontWeight: 700 }}>
                            نقره، طلا، خرید نقره، خرید طلا، فروش نقره، فروش طلا، سکه، خرید سکه، فروش سکه، سکه تمام، ربع سکه، نیم سکه، ساچمه، صفرپور، نقره آبشده، نقره عیار، نقره عیار ۹۹۵، نقره عیار ۹۹۹، عیار ۹۹۵، عیار ۹۹۹.
                        </p>
                    </div>
                </section>
            </main>
        </AppLayout>
    );
}
