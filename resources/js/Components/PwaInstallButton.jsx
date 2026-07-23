import { useEffect, useState } from 'react';

export default function PwaInstallButton() {
    const [deferredPrompt, setDeferredPrompt] = useState(null);
    const [installed, setInstalled] = useState(false);
    const [helpOpen, setHelpOpen] = useState(false);

    useEffect(() => {
        const onBeforeInstall = event => {
            event.preventDefault();
            setDeferredPrompt(event);
            setHelpOpen(false);
        };
        const onInstalled = () => {
            setDeferredPrompt(null);
            setInstalled(true);
            setHelpOpen(false);
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
        if (!deferredPrompt) {
            setHelpOpen(open => !open);
            return;
        }

        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        setDeferredPrompt(null);
    }

    if (installed) return <span className="pwa-installed">نسخهٔ نصب‌شده</span>;

    return (
        <div className="pwa-install-wrap">
            <button
                type="button"
                onClick={install}
                className="pwa-install-button"
                aria-expanded={helpOpen}
                aria-controls="pwa-install-help"
            >
                نصب برنامه
            </button>
            {helpOpen && (
                <div id="pwa-install-help" className="pwa-install-help" role="status">
                    در Chrome یا Edge از منوی مرورگر گزینهٔ نصب برنامه را بزنید. در iPhone از Share و سپس Add to Home Screen استفاده کنید.
                </div>
            )}
        </div>
    );
}
