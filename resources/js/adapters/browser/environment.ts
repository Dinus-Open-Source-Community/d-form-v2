/**
 * Akses aman ke global browser. Semua adapter browser memakai helper ini agar
 * tidak melempar `ReferenceError` saat SSR atau saat unit test berjalan di Node.
 */
export function readWindow(): Window | null {
    return typeof globalThis.window === 'undefined' ? null : globalThis.window;
}

export function readDocument(): Document | null {
    return typeof globalThis.document === 'undefined' ? null : globalThis.document;
}

export function readNavigator(): Navigator | null {
    return typeof globalThis.navigator === 'undefined' ? null : globalThis.navigator;
}
