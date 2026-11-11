import { readWindow } from '@/adapters/browser/environment';

/**
 * Daftarkan handler `pagehide`: dipanggil browser tepat sebelum halaman
 * di-reload/ditinggalkan (termasuk masuk bfcache). Cleanup wajib dipanggil saat
 * hook dilepas agar listener tidak menumpuk per mount.
 */
export function onPageHide(handler: () => void): () => void {
    const browserWindow = readWindow();
    if (browserWindow === null) return () => {};

    browserWindow.addEventListener('pagehide', handler);

    return (): void => {
        browserWindow.removeEventListener('pagehide', handler);
    };
}
