/**
 * Generator id yang aman di semua konteks penyajian.
 *
 * `crypto.randomUUID` hanya tersedia pada secure context (HTTPS atau localhost).
 * Builder juga dilayani lewat HTTP LAN (mis. `http://192.168.0.170:3000`), jadi
 * pemanggilan langsung `crypto.randomUUID()` melempar TypeError dan mematikan
 * seluruh operasi yang membuat id. Helper ini memilih sumber entropy terbaik yang
 * tersedia dan tidak pernah melempar.
 */

/** Bagian `crypto` global yang dipakai generator; opsional karena `randomUUID`
 *  hilang di insecure context dan `getRandomValues` bisa tidak ada di luar browser. */
interface ICryptoEntropySource {
    randomUUID?: () => string;
    getRandomValues?: (array: Uint8Array) => Uint8Array;
}

const HEX_ALPHABET = '0123456789abcdef';
const UUID_BYTE_LENGTH = 16;
const UUID_HEX_LENGTH = 32;
const FALLBACK_SEQUENCE_MODULO = 0x10000;

/** Counter proses untuk fallback non-kriptografis; lihat `createFallbackUuid`. */
let fallbackSequence = 0;

/**
 * Sumber entropy global. `globalThis.crypto` selalu ada di browser modern, tetapi
 * dipertahankan nullable agar runtime non-browser (SSR/test) tetap aman.
 */
function entropySource(): ICryptoEntropySource | undefined {
    return typeof globalThis.crypto === 'undefined' ? undefined : globalThis.crypto;
}

/** Susun 16 byte acak menjadi UUID RFC 4122 versi 4 (bit versi + varian dipaksa). */
function formatUuidV4(bytes: Uint8Array): string {
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    let hex = '';
    for (const byte of bytes) {
        hex += byte.toString(16).padStart(2, '0');
    }

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/**
 * Fallback dev-only saat Web Crypto tidak tersedia sama sekali (mis. runtime lama).
 * Tidak kriptografis: hanya timestamp + counter proses, cukup unik untuk satu sesi.
 * Bentuknya tetap UUID v4 agar konsumen tidak perlu membedakan jalur.
 */
function createFallbackUuid(): string {
    fallbackSequence = (fallbackSequence + 1) % FALLBACK_SEQUENCE_MODULO;

    const timestamp = Date.now().toString(16).padStart(12, '0').slice(-12);
    const sequence = fallbackSequence.toString(16).padStart(4, '0');
    const entropy = `${timestamp}${sequence}`.padEnd(UUID_HEX_LENGTH, '0');

    return `${entropy.slice(0, 8)}-${entropy.slice(8, 12)}-4${entropy.slice(13, 16)}-8${entropy.slice(17, 20)}-${entropy.slice(20, 32)}`;
}

/**
 * Buat UUID v4. Urutan sumber: `randomUUID` (bila secure context), lalu
 * `getRandomValues` (tersedia juga di HTTP), lalu fallback dev-only.
 */
export function createUuid(): string {
    const source = entropySource();

    if (typeof source?.randomUUID === 'function') {
        return source.randomUUID();
    }

    if (typeof source?.getRandomValues === 'function') {
        const bytes = new Uint8Array(UUID_BYTE_LENGTH);
        source.getRandomValues(bytes);

        return formatUuidV4(bytes);
    }

    return createFallbackUuid();
}

/**
 * Id pendek heksadesimal untuk nama field teknis dan kios QR.
 * `length` non-positif atau bukan integer menghasilkan string kosong.
 */
export function createShortId(length: number): string {
    if (!Number.isInteger(length) || length <= 0) return '';

    const source = entropySource();

    if (typeof source?.getRandomValues === 'function') {
        const bytes = new Uint8Array(length);
        source.getRandomValues(bytes);

        let id = '';
        for (const byte of bytes) {
            id += HEX_ALPHABET.charAt(byte & 0x0f);
        }

        return id;
    }

    let id = '';
    while (id.length < length) {
        id += createUuid().replace(/-/g, '');
    }

    return id.slice(0, length);
}
