import { readDocument } from '@/adapters/browser/environment';
import { isSafeDownloadUrl } from '@/lib/urlPolicy';

/**
 * Simpan blob sebagai file unduhan.
 * `false` bila browser tidak menyediakan API yang dibutuhkan (mis. SSR).
 */
export function saveBlobAsFile(blob: Blob, filename: string): boolean {
    const documentRef = readDocument();
    if (documentRef === null) return false;

    const objectUrl = URL.createObjectURL(blob);
    const anchor = documentRef.createElement('a');
    anchor.href = objectUrl;
    anchor.download = filename;
    anchor.rel = 'noopener';
    documentRef.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    // WebKit membatalkan unduhan bila object URL di-revoke terlalu cepat.
    globalThis.setTimeout((): void => URL.revokeObjectURL(objectUrl), 1000);

    return true;
}

/**
 * Buka URL di tab baru. Dipakai sebagai fallback unduhan ketika blob tidak bisa
 * diambil; `false` bila browser tidak tersedia.
 */
export function openUrlInNewTab(url: string, filename?: string): boolean {
    const documentRef = readDocument();
    if (documentRef === null || !isSafeDownloadUrl(url)) return false;

    const anchor = documentRef.createElement('a');
    anchor.href = url;
    anchor.target = '_blank';
    anchor.rel = 'noopener noreferrer';
    if (filename !== undefined && filename.length > 0) anchor.download = filename;
    documentRef.body.appendChild(anchor);
    anchor.click();
    anchor.remove();

    return true;
}

/**
 * Picu unduhan langsung dari URL (mis. endpoint export yang mengirim attachment).
 * `filename` kosong membiarkan browser memakai nama dari server.
 */
export function downloadUrl(url: string, filename = ''): boolean {
    const documentRef = readDocument();
    if (documentRef === null || !isSafeDownloadUrl(url)) return false;

    const anchor = documentRef.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.rel = 'noopener';
    documentRef.body.appendChild(anchor);
    anchor.click();
    anchor.remove();

    return true;
}
