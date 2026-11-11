import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mountedCallbacks: Array<() => void> = [];
const unmountedCallbacks: Array<() => void> = [];

vi.mock('vue', async () => {
    const actual = await vi.importActual<typeof import('vue')>('vue');
    return {
        ...actual,
        onMounted: (callback: () => void): void => {
            mountedCallbacks.push(callback);
        },
        onUnmounted: (callback: () => void): void => {
            unmountedCallbacks.push(callback);
        },
    };
});

const findElementById = vi.hoisted(() => vi.fn());
vi.mock('@/adapters/browser/domQuery', () => ({ findElementById }));

import { useViewportReveal } from '@/hooks/useViewportReveal';

const observerInstances: StubIntersectionObserver[] = [];

class StubIntersectionObserver {
    public readonly disconnect = vi.fn();
    public readonly observe = vi.fn();
    private readonly callback: IntersectionObserverCallback;

    public constructor(callback: IntersectionObserverCallback) {
        this.callback = callback;
        observerInstances.push(this);
    }

    public triggerIntersecting(): void {
        const entry: IntersectionObserverEntry = {
            boundingClientRect: new DOMRectReadOnly(),
            intersectionRatio: 1,
            intersectionRect: new DOMRectReadOnly(),
            isIntersecting: true,
            rootBounds: null,
            target: new HTMLElement(),
            time: 0,
        };

        Reflect.apply(this.callback, null, [[entry], null]);
    }
}

beforeEach(() => {
    mountedCallbacks.length = 0;
    unmountedCallbacks.length = 0;
    observerInstances.length = 0;
    vi.stubGlobal('HTMLElement', class StubElement {});
    vi.stubGlobal('DOMRectReadOnly', class StubDOMRectReadOnly {});
    findElementById.mockReturnValue(new HTMLElement());
    vi.stubGlobal('IntersectionObserver', StubIntersectionObserver);
    vi.stubGlobal('window', { matchMedia: vi.fn(() => ({ matches: false })) });
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.clearAllMocks();
});

describe('useViewportReveal', () => {
    it('disconnects only its own observer on unmount for AUDIT-P3-005', () => {
        const first = useViewportReveal({ targetId: 'first', threshold: 0.1 });
        const second = useViewportReveal({ targetId: 'second', threshold: 0.2 });
        mountedCallbacks.forEach((callback) => callback());

        unmountedCallbacks[0]?.();

        expect(first.visible.value).toBe(false);
        expect(second.visible.value).toBe(false);
        expect(observerInstances[0]?.disconnect).toHaveBeenCalledTimes(1);
        expect(observerInstances[1]?.disconnect).not.toHaveBeenCalled();
    });

    it('reveals immediately when reduced motion is preferred', () => {
        vi.stubGlobal('window', { matchMedia: vi.fn(() => ({ matches: true })) });
        const state = useViewportReveal({ targetId: 'section', threshold: 0.1 });

        mountedCallbacks[0]?.();

        expect(state.visible.value).toBe(true);
        expect(observerInstances).toHaveLength(0);
    });

    it('creates an observer and reveals after an intersecting entry', () => {
        const state = useViewportReveal({ targetId: 'section', threshold: 0.15 });

        mountedCallbacks[0]?.();

        expect(observerInstances).toHaveLength(1);
        expect(observerInstances[0]?.observe).toHaveBeenCalledTimes(1);
        expect(state.visible.value).toBe(false);

        observerInstances[0]?.triggerIntersecting();

        expect(state.visible.value).toBe(true);
        expect(observerInstances[0]?.disconnect).toHaveBeenCalledTimes(1);
    });
});
