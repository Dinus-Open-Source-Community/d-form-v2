import { readWindow } from '@/adapters/browser/environment';

/**
 * Object URL (`blob:`) untuk preview lokal gambar sebelum upload selesai.
 * Adapter ini satu-satunya pemegang `URL.createObjectURL`/`revokeObjectURL` agar
 * komponen dan hook tidak menyentuh browser API secara langsung.
 *
 * Guard mengikuti `storage.ts`: akses hanya lewat `readWindow()` dan setiap
 * panggilan dibungkus `try/catch`, sehingga SSR/Node atau browser yang memblokir
 * API tersebut tidak melempar (preview jatuh ke URL server).
 */

/** `URL` yang benar-benar punya object-URL API; `null` di SSR/Node. */
function readObjectUrlApi(): typeof URL | null {
    if (readWindow() === null) return null;

    return typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function' ? URL : null;
}

/** Buat object URL preview; string kosong bila API tidak tersedia. Pemanggil wajib me-revoke. */
export function createObjectUrl(file: Blob): string {
    const api = readObjectUrlApi();
    if (api === null) return '';

    try {
        return api.createObjectURL(file);
    } catch {
        return '';
    }
}

/**
 * Lepas object URL preview. Aman untuk `null`/non-blob (no-op) dan saat API tidak
 * tersedia, sehingga pembersihan tidak perlu mengulang guard di setiap komponen.
 */
export function revokeObjectUrl(url: string | null | undefined): void {
    if (typeof url !== 'string' || !url.startsWith('blob:')) return;

    const api = readObjectUrlApi();
    if (api === null) return;

    try {
        api.revokeObjectURL(url);
    } catch {
        // Object URL sudah dilepas atau tidak valid; tidak ada yang perlu ditangani.
    }
}
