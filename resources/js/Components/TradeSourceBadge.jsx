export default function TradeSourceBadge({ isFromBot, style }) {
    if (!isFromBot) return null;

    return (
        <span className="badge bot-source" title="ثبت‌شده از ربات تلگرام" style={{ marginInlineStart: 6, ...style }}>
            از ربات
        </span>
    );
}
