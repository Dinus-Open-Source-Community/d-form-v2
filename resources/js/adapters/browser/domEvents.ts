import { readDocument, readWindow } from '@/adapters/browser/environment';

/**
 * Berlangganan event window bertipe. Mengembalikan fungsi pelepas; no-op saat SSR.
 * Dipakai menggantikan `window.addEventListener` + `removeEventListener` manual.
 */
export function onWindowEvent<K extends keyof WindowEventMap>(
    type: K,
    listener: (event: WindowEventMap[K]) => void,
    options?: AddEventListenerOptions,
): () => void {
    const browserWindow = readWindow();
    if (browserWindow === null) return () => undefined;

    browserWindow.addEventListener(type, listener, options);

    return () => browserWindow.removeEventListener(type, listener, options);
}

/** Berlangganan event document bertipe. */
export function onDocumentEvent<K extends keyof DocumentEventMap>(
    type: K,
    listener: (event: DocumentEventMap[K]) => void,
    options?: AddEventListenerOptions,
): () => void {
    const documentRef = readDocument();
    if (documentRef === null) return () => undefined;

    documentRef.addEventListener(type, listener, options);

    return () => documentRef.removeEventListener(type, listener, options);
}

/** Berlangganan event kustom document yang tidak ada di `DocumentEventMap`. */
export function onDocumentCustomEvent(
    type: string,
    listener: (event: Event) => void,
    options?: AddEventListenerOptions,
): () => void {
    const documentRef = readDocument();
    if (documentRef === null) return () => undefined;

    documentRef.addEventListener(type, listener, options);

    return () => documentRef.removeEventListener(type, listener, options);
}
