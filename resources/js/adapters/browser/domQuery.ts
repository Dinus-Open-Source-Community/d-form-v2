import { readDocument } from '@/adapters/browser/environment';

/** Cari satu elemen berdasarkan id; `null` saat SSR. */
export function findElementById(id: string): HTMLElement | null {
    return readDocument()?.getElementById(id) ?? null;
}

/** Cari elemen pertama dengan selector CSS. */
export function findElement(selector: string): HTMLElement | null {
    return readDocument()?.querySelector<HTMLElement>(selector) ?? null;
}

/** Cari semua elemen dengan selector CSS. */
export function findElements(selector: string): HTMLElement[] {
    const documentRef = readDocument();
    if (documentRef === null) return [];

    return Array.from(documentRef.querySelectorAll<HTMLElement>(selector));
}

/** Fokus elemen bila ada; `false` bila elemen tidak ditemukan. */
export function focusElement(element: Element | null): boolean {
    if (typeof HTMLElement === 'undefined' || !(element instanceof HTMLElement)) return false;

    element.focus();

    return true;
}

/** Fokus elemen berdasarkan id; `false` bila tidak ada. */
export function focusElementById(id: string): boolean {
    return focusElement(findElementById(id));
}

/** Scroll elemen ke viewport dengan animasi halus. */
export function scrollElementIntoView(element: Element | null): void {
    if (typeof HTMLElement === 'undefined' || !(element instanceof HTMLElement)) return;

    element.scrollIntoView({ behavior: 'smooth' });
}

/** Scroll elemen berdasarkan id ke viewport. */
export function scrollToElementById(id: string): void {
    scrollElementIntoView(findElementById(id));
}
