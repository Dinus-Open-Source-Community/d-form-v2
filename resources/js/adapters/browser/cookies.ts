import { readDocument } from '@/adapters/browser/environment';

/**
 * Baca cookie non-HttpOnly berdasarkan nama. Dipakai request `keepalive` saat
 * halaman ditinggalkan, yang tidak melewati axios sehingga token XSRF tidak
 * disuntikkan otomatis.
 */
export function readCookie(name: string): string {
    const document = readDocument();
    if (document === null) return '';

    for (const part of document.cookie.split(';')) {
        const trimmed = part.trim();
        const separator = trimmed.indexOf('=');
        if (separator === -1) continue;
        if (trimmed.slice(0, separator).trim() === name) return trimmed.slice(separator + 1).trim();
    }

    return '';
}
