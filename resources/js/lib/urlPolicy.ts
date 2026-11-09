/**
 * Kebijakan URL untuk navigasi dan unduhan yang dibuat adapter.
 * Semua fungsi di sini murni dan tidak menyentuh browser global.
 */

/** Skema yang boleh dipakai untuk navigasi penuh (`window.location.assign`). */
const NAVIGATION_PROTOCOLS = new Set(['http:', 'https:']);

/** Skema yang boleh dipakai untuk unduhan/buka tab baru. */
const DOWNLOAD_PROTOCOLS = new Set(['http:', 'https:', 'blob:']);

/**
 * True bila nilai adalah path internal yang aman: diawali satu `/`, bukan `//`,
 * dan tidak memuat tab/newline (WHATWG menghapus karakter itu sebelum parsing,
 * sehingga `"/\t/evil.test"` bisa berubah menjadi `//evil.test`).
 */
export function isInternalPath(value: string): boolean {
    if (!value.startsWith('/')) return false;
    if (value.startsWith('//') || value.startsWith('/\\')) return false;

    return !/[\t\n\r]/.test(value);
}

/** True bila URL relatif/absolut boleh dipakai untuk navigasi penuh. */
export function isSafeNavigationUrl(value: string): boolean {
    if (value.length === 0) return false;
    if (isInternalPath(value)) return true;

    return parseProtocol(value) !== null && NAVIGATION_PROTOCOLS.has(parseProtocol(value) ?? '');
}

/** True bila URL relatif/absolut boleh dipakai untuk unduhan atau tab baru. */
export function isSafeDownloadUrl(value: string): boolean {
    if (value.length === 0) return false;
    if (isInternalPath(value)) return true;

    const protocol = parseProtocol(value);

    return protocol !== null && DOWNLOAD_PROTOCOLS.has(protocol);
}

/**
 * Ambil protokol URL tanpa melempar.
 * Nilai seperti `javascript:alert(1)` tidak bisa diparse `new URL` tanpa base,
 * sehingga jalur itu dikembalikan sebagai protokol mentah agar bisa ditolak.
 */
function parseProtocol(value: string): string | null {
    const raw = value.trim();
    const scheme = raw.match(/^([a-z][a-z0-9+.-]*):/i);
    if (scheme === null) return null;

    return `${scheme[1].toLowerCase()}:`;
}

/**
 * True bila nilai boleh disimpan sebagai URL gambar: non-kosong dan bukan
 * `data:`/`blob:` (gambar sementara tidak boleh masuk jawaban/editor).
 */
export function isPersistableImageUrl(value: string): boolean {
    const url = value.trim();

    return url.length > 0 && !url.startsWith('data:') && !url.startsWith('blob:');
}
