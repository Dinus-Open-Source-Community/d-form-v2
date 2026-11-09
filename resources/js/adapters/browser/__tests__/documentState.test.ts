import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    isDocumentHidden,
    isFocusOnBody,
    observeThemeAttribute,
    onVisibilityChange,
    readDocumentTitle,
    readThemeAttribute,
    writeThemeAttribute,
} from '@/adapters/browser/documentState';
import { matchesMediaQuery, onMediaQueryChange } from '@/adapters/browser/mediaQuery';

/** Document palsu dengan listener dan atribut root yang bisa dikendalikan test. */
function createDocument(): {
    title: string;
    hidden: boolean;
    documentElement: { getAttribute: (name: string) => string | null; setAttribute: (name: string, value: string) => void };
    listeners: Map<string, EventListener>;
    addEventListener: ReturnType<typeof vi.fn>;
    removeEventListener: ReturnType<typeof vi.fn>;
} {
    const listeners = new Map<string, EventListener>();

    return {
        title: 'Dashboard',
        hidden: false,
        documentElement: {
            getAttribute: (): string | null => 'dark',
            setAttribute: vi.fn(),
        },
        listeners,
        addEventListener: vi.fn((type: string, listener: EventListener): void => {
            listeners.set(type, listener);
        }),
        removeEventListener: vi.fn((type: string): void => {
            listeners.delete(type);
        }),
    };
}

afterEach((): void => {
    vi.unstubAllGlobals();
});

describe('documentState adapter', () => {
    it('is a safe no-op without a document (SSR)', () => {
        expect(readDocumentTitle()).toBe('');
        expect(isDocumentHidden()).toBe(false);
        expect(readThemeAttribute()).toBeNull();
        expect(onVisibilityChange((): void => undefined)).toBeTypeOf('function');
        expect(observeThemeAttribute((): void => undefined)).toBeTypeOf('function');
        expect(() => writeThemeAttribute('dark')).not.toThrow();
    });

    it('reads the document title', () => {
        vi.stubGlobal('document', createDocument());

        expect(readDocumentTitle()).toBe('Dashboard');
    });

    it('detects whether focus sits on the body', () => {
        expect(isFocusOnBody()).toBe(true);

        const body = {};
        vi.stubGlobal('document', { activeElement: body, body });
        expect(isFocusOnBody()).toBe(true);

        vi.stubGlobal('document', { activeElement: { tagName: 'INPUT' }, body });
        expect(isFocusOnBody()).toBe(false);
    });

    it('reflects visibility and releases the listener', () => {
        const fakeDocument = createDocument();
        vi.stubGlobal('document', fakeDocument);
        const listener = vi.fn();
        const release = onVisibilityChange(listener);

        fakeDocument.hidden = true;
        fakeDocument.listeners.get('visibilitychange')?.({} as Event);

        expect(listener).toHaveBeenCalledWith(true);
        release();
        expect(fakeDocument.removeEventListener).toHaveBeenCalledWith('visibilitychange', expect.any(Function));
    });

    it('observes theme attribute changes and disconnects', () => {
        const observer: { callback: MutationCallback | null } = { callback: null };
        const disconnect = vi.fn();
        const observe = vi.fn();

        class FakeMutationObserver {
            constructor(callback: MutationCallback) {
                observer.callback = callback;
            }

            observe(...args: unknown[]): void {
                observe(...args);
            }

            disconnect(): void {
                disconnect();
            }
        }

        vi.stubGlobal('MutationObserver', FakeMutationObserver);
        vi.stubGlobal('document', createDocument());
        const listener = vi.fn();
        const release = observeThemeAttribute(listener);

        expect(observe).toHaveBeenCalledWith(expect.anything(), {
            attributes: true,
            attributeFilter: ['data-theme'],
        });

        observer.callback?.([], {} as MutationObserver);
        expect(listener).toHaveBeenCalledWith('dark');

        release();
        expect(disconnect).toHaveBeenCalled();
    });
});

describe('mediaQuery adapter', () => {
    it('returns false without matchMedia (SSR)', () => {
        expect(matchesMediaQuery('(min-width: 768px)')).toBe(false);
        expect(onMediaQueryChange('(min-width: 768px)', (): void => undefined)).toBeTypeOf('function');
    });

    it('reads and subscribes to media query changes', () => {
        const listeners = new Map<string, (event: MediaQueryListEvent) => void>();
        const removeEventListener = vi.fn((type: string): void => {
            listeners.delete(type);
        });
        const matchMedia = vi.fn(() => ({
            matches: true,
            addEventListener: (type: string, listener: (event: MediaQueryListEvent) => void): void => {
                listeners.set(type, listener);
            },
            removeEventListener,
        }));
        vi.stubGlobal('window', { matchMedia });

        expect(matchesMediaQuery('(hover: hover)')).toBe(true);

        const listener = vi.fn();
        const release = onMediaQueryChange('(min-width: 768px)', listener);
        listeners.get('change')?.({ matches: false } as MediaQueryListEvent);

        expect(listener).toHaveBeenCalledWith(false);
        release();
        expect(removeEventListener).toHaveBeenCalledWith('change', expect.any(Function));
    });
});
