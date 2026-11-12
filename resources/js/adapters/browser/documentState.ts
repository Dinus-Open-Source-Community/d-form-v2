import { readDocument } from '@/adapters/browser/environment';

/** Judul dokumen saat ini; string kosong saat SSR. */
export function readDocumentTitle(): string {
    return readDocument()?.title ?? '';
}

/** True bila tab sedang tidak terlihat. */
export function isDocumentHidden(): boolean {
    return readDocument()?.hidden === true;
}

/**
 * Berlangganan perubahan visibilitas tab.
 * Mengembalikan fungsi pelepas; no-op saat SSR.
 */
export function onVisibilityChange(listener: (hidden: boolean) => void): () => void {
    const documentRef = readDocument();
    if (documentRef === null) return () => undefined;

    const handler = (): void => listener(documentRef.hidden);
    documentRef.addEventListener('visibilitychange', handler);

    return () => documentRef.removeEventListener('visibilitychange', handler);
}

/** True bila fokus ada di body atau tidak ada elemen aktif (halaman baru selesai navigasi). */
export function isFocusOnBody(): boolean {
    const documentRef = readDocument();
    if (documentRef === null) return true;

    return documentRef.activeElement === null || documentRef.activeElement === documentRef.body;
}

/** Nilai atribut `data-theme` pada elemen root. */
export function readThemeAttribute(): string | null {
    return readDocument()?.documentElement.getAttribute('data-theme') ?? null;
}

/** Set atribut `data-theme` pada elemen root. */
export function writeThemeAttribute(theme: string): void {
    readDocument()?.documentElement.setAttribute('data-theme', theme);
}

/**
 * Berlangganan perubahan atribut `data-theme`.
 * Mengembalikan fungsi pelepas; no-op saat SSR atau tanpa MutationObserver.
 */
export function observeThemeAttribute(listener: (theme: string | null) => void): () => void {
    const documentRef = readDocument();
    if (documentRef === null || typeof MutationObserver === 'undefined') return () => undefined;

    const observer = new MutationObserver((): void => {
        listener(documentRef.documentElement.getAttribute('data-theme'));
    });
    observer.observe(documentRef.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    return () => observer.disconnect();
}
