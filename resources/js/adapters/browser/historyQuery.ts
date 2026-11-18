import { readWindow } from '@/adapters/browser/environment';

/**
 * Ganti query string URL aktif tanpa navigasi dan tanpa menambah history.
 *
 * Parameter lain dipertahankan apa adanya; nilai `null` menghapus key. Dipakai panel
 * pengguna agar `?edit`/`?create` jujur tanpa memicu request Inertia.
 */
export function replaceHistoryQuery(params: Record<string, string | null>): void {
    const browserWindow = readWindow();
    if (browserWindow === null) return;

    const search = new URLSearchParams(browserWindow.location.search);
    for (const [key, value] of Object.entries(params)) {
        if (value === null) {
            search.delete(key);
        } else {
            search.set(key, value);
        }
    }

    const query = search.toString();
    const nextUrl = query === '' ? browserWindow.location.pathname : `${browserWindow.location.pathname}?${query}`;

    browserWindow.history.replaceState(browserWindow.history.state, '', `${nextUrl}${browserWindow.location.hash}`);
}
