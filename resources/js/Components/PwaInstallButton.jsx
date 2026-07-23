import { useEffect, useState } from 'react';

export default function PwaInstallButton() {
    const [deferredPrompt, setDeferredPrompt] = useState(null);
    const [installed, setInstalled] = useState(false);

    useEffect(() => {
        const onBeforeInstall = event => {
            event.preventDefault();
            setDeferredPrompt(event);
        };
        const onInstalled = () => {
            setDeferredPrompt(null);
            setInstalled(true);
        };

        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
            setInstalled(true);
        }

        window.addEventListener('beforeinstallprompt', onBeforeInstall);
        window.addEventListener('appinstalled', onInstalled);

        return () => {
            window.removeEventListener('beforeinstallprompt', onBeforeInstall);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    async function install() {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        setDeferredPrompt(null);
    }

    if (installed) return <span className="pwa-installed">نسخهٔ نصب‌شده</span>;
    if (!deferredPrompt) return null;

    return <button type="button" onClick={install} className="pwa-install-button">نصب برنامه</button>;
}
