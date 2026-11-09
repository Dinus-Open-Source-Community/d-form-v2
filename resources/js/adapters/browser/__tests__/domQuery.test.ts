import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    findElement,
    findElementById,
    findElements,
    focusElement,
    focusElementById,
    scrollElementIntoView,
    scrollToElementById,
} from '@/adapters/browser/domQuery';
import { onDocumentCustomEvent, onDocumentEvent, onWindowEvent } from '@/adapters/browser/domEvents';

/** Elemen palsu dengan method yang dipakai adapter. */
class FakeElement {
    focus = vi.fn();
    scrollIntoView = vi.fn();
}

/** Node palsu dipakai sebagai `Element` hanya di test adapter. */
function asElement(element: FakeElement): Element {
    return element as unknown as Element;
}

afterEach((): void => {
    vi.unstubAllGlobals();
});

describe('domQuery adapter', () => {
    it('is a safe no-op without a document (SSR)', () => {
        expect(findElementById('x')).toBeNull();
        expect(findElement('.x')).toBeNull();
        expect(findElements('.x')).toEqual([]);
        expect(focusElement(null)).toBe(false);
        expect(focusElementById('x')).toBe(false);
        expect(() => scrollElementIntoView(null)).not.toThrow();
        expect(() => scrollToElementById('x')).not.toThrow();
    });

    it('finds elements and focuses them', () => {
        const element = new FakeElement();
        const documentRef = {
            getElementById: vi.fn(() => element),
            querySelector: vi.fn(() => element),
            querySelectorAll: vi.fn(() => [element]),
        };
        vi.stubGlobal('document', documentRef);
        vi.stubGlobal('HTMLElement', FakeElement);

        expect(findElementById('x')).toBe(element);
        expect(findElement('.x')).toBe(element);
        expect(findElements('.x')).toEqual([element]);

        expect(focusElementById('x')).toBe(true);
        expect(element.focus).toHaveBeenCalledTimes(1);

        expect(focusElement(asElement(element))).toBe(true);
        expect(element.focus).toHaveBeenCalledTimes(2);
    });

    it('scrolls an element into view', () => {
        const element = new FakeElement();
        const documentRef = {
            getElementById: vi.fn(() => element),
            querySelector: vi.fn(() => element),
            querySelectorAll: vi.fn(() => []),
        };
        vi.stubGlobal('document', documentRef);
        vi.stubGlobal('HTMLElement', FakeElement);

        scrollElementIntoView(asElement(element));
        scrollToElementById('x');

        expect(element.scrollIntoView).toHaveBeenCalledTimes(2);
        expect(element.scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth' });
    });

    it('rejects non-HTMLElement nodes', () => {
        vi.stubGlobal('HTMLElement', FakeElement);

        expect(focusElement({} as Element)).toBe(false);
    });
});

describe('domEvents adapter', () => {
    it('is a safe no-op without browser globals (SSR)', () => {
        expect(onWindowEvent('scroll', (): void => undefined)).toBeTypeOf('function');
        expect(onDocumentEvent('visibilitychange', (): void => undefined)).toBeTypeOf('function');
        expect(onDocumentCustomEvent('titlechange', (): void => undefined)).toBeTypeOf('function');
    });

    it('subscribes and releases window listeners', () => {
        const addEventListener = vi.fn();
        const removeEventListener = vi.fn();
        vi.stubGlobal('window', { addEventListener, removeEventListener });

        const listener = vi.fn();
        const release = onWindowEvent('popstate', listener);

        expect(addEventListener).toHaveBeenCalledWith('popstate', listener, undefined);
        release();
        expect(removeEventListener).toHaveBeenCalledWith('popstate', listener, undefined);
    });

    it('subscribes and releases document listeners', () => {
        const addEventListener = vi.fn();
        const removeEventListener = vi.fn();
        vi.stubGlobal('document', { addEventListener, removeEventListener });

        const visibilityListener = vi.fn();
        const releaseVisibility = onDocumentEvent('visibilitychange', visibilityListener);
        const customListener = vi.fn();
        const releaseCustom = onDocumentCustomEvent('titlechange', customListener);

        expect(addEventListener).toHaveBeenCalledWith('visibilitychange', visibilityListener, undefined);
        expect(addEventListener).toHaveBeenCalledWith('titlechange', customListener, undefined);

        releaseVisibility();
        releaseCustom();
        expect(removeEventListener).toHaveBeenCalledTimes(2);
    });
});
