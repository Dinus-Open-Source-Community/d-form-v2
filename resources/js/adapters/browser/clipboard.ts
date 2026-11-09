import { readNavigator, readWindow } from '@/adapters/browser/environment';

/**
 * Salin teks ke clipboard.
 * Mengembalikan `false` bila API tidak tersedia, konteks tidak aman (non-HTTPS),
 * atau browser menolak permintaan; tidak pernah melempar.
 */
export async function copyTextToClipboard(text: string): Promise<boolean> {
    const browserWindow = readWindow();
    const browserNavigator = readNavigator();
    if (browserWindow === null || browserNavigator === null) return false;
    if (browserWindow.isSecureContext !== true) return false;
    if (typeof browserNavigator.clipboard === 'undefined') return false;

    try {
        await browserNavigator.clipboard.writeText(text);

        return true;
    } catch {
        return false;
    }
}
