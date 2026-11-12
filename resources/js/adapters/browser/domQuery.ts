import { readDocument } from '@/adapters/browser/environment';

/** Kontrak minimal elemen yang bisa difokuskan; fake test tidak perlu `Element` penuh. */
export interface IFocusableElement {
    focus(): void;
}

/** Kontrak minimal elemen yang bisa di-scroll ke viewport. */
export interface IScrollableElement {
    scrollIntoView(options?: ScrollIntoViewOptions): void;
}

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

/** Fokus elemen bila ada dan benar-benar HTMLElement; `false` bila tidak. */
export function focusElement(element: IFocusableElement | null): boolean {
    if (typeof HTMLElement === 'undefined' || !(element instanceof HTMLElement)) return false;

    element.focus();

    return true;
}

/** Fokus elemen berdasarkan id; `false` bila tidak ada. */
export function focusElementById(id: string): boolean {
    return focusElement(findElementById(id));
}

/** Scroll elemen ke viewport dengan animasi halus; no-op bila bukan HTMLElement. */
export function scrollElementIntoView(element: IScrollableElement | null): void {
    if (typeof HTMLElement === 'undefined' || !(element instanceof HTMLElement)) return;

    element.scrollIntoView({ behavior: 'smooth' });
}

/** Scroll elemen berdasarkan id ke viewport. */
export function scrollToElementById(id: string): void {
    scrollElementIntoView(findElementById(id));
}
