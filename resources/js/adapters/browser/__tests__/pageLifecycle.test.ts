import { afterEach, describe, expect, it, vi } from 'vitest';
import { onPageHide } from '@/adapters/browser/pageLifecycle';

interface IFakeWindow {
    addEventListener: ReturnType<typeof vi.fn>;
    removeEventListener: ReturnType<typeof vi.fn>;
}

function stubWindow(): IFakeWindow {
    const fakeWindow: IFakeWindow = {
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    };
    vi.stubGlobal('window', fakeWindow);

    return fakeWindow;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('onPageHide', () => {
    it('mendaftarkan satu listener pagehide dan melepasnya lewat cleanup', () => {
        const fakeWindow = stubWindow();
        const handler = vi.fn();

        const stop = onPageHide(handler);

        expect(fakeWindow.addEventListener).toHaveBeenCalledWith('pagehide', handler);

        stop();

        expect(fakeWindow.removeEventListener).toHaveBeenCalledWith('pagehide', handler);
    });

    it('no-op saat window tidak tersedia (SSR/Node)', () => {
        vi.stubGlobal('window', undefined);

        const stop = onPageHide(vi.fn());

        expect(() => stop()).not.toThrow();
    });
});
