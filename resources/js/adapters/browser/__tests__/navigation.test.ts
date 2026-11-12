import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    assignUrl,
    currentHash,
    currentHref,
    currentOrigin,
    currentPathname,
    currentSearchParam,
    currentSearch,
    goBack,
    pushHistoryState,
    pushUrl,
    readScrollY,
    replaceUrl,
    scrollToTop,
} from '@/adapters/browser/navigation';

interface INavigationWindow {
    location: {
        pathname: string;
        href: string;
        search: string;
        hash: string;
        origin: string;
        assign: ReturnType<typeof vi.fn>;
    };
    history: {
        length: number;
        state?: unknown;
        replaceState: ReturnType<typeof vi.fn>;
        pushState: ReturnType<typeof vi.fn>;
        back: ReturnType<typeof vi.fn>;
    };
    scrollY: number;
    scrollTo: ReturnType<typeof vi.fn>;
}

/** Window palsu minimal untuk menguji adapter navigasi. */
function createWindow(historyEntries = 2): INavigationWindow {
    return {
        location: {
            pathname: '/dashboard',
            href: 'https://example.test/dashboard?tab=peserta',
            search: '?tab=peserta',
            hash: '#laporan',
            origin: 'https://example.test',
            assign: vi.fn(),
        },
        history: {
            length: historyEntries,
            replaceState: vi.fn(),
            pushState: vi.fn(),
            back: vi.fn(),
        },
        scrollY: 240,
        scrollTo: vi.fn(),
    };
}

afterEach((): void => {
    vi.unstubAllGlobals();
});

describe('navigation adapter', () => {
    it('is a safe no-op without a browser window (SSR)', () => {
        expect(currentPathname()).toBe('');
        expect(currentHref()).toBe('');
        expect(currentSearchParam('tab')).toBeNull();
        expect(currentHash()).toBe('');
        expect(readScrollY()).toBe(0);
        expect(goBack()).toBe(false);
        expect(currentOrigin()).toBe('');
        expect(() => replaceUrl('/x')).not.toThrow();
        expect(() => pushUrl('/x')).not.toThrow();
        expect(() => pushHistoryState({ authTab: 'register' }, '/x')).not.toThrow();
        expect(() => assignUrl('/x')).not.toThrow();
        expect(() => scrollToTop()).not.toThrow();
        expect(currentSearch()).toBe('');
    });

    it('reads location and query state', () => {
        vi.stubGlobal('window', createWindow());

        expect(currentPathname()).toBe('/dashboard');
        expect(currentHref()).toBe('https://example.test/dashboard?tab=peserta');
        expect(currentSearchParam('tab')).toBe('peserta');
        expect(currentSearchParam('missing')).toBeNull();
        expect(currentSearch()).toBe('?tab=peserta');
        expect(currentHash()).toBe('#laporan');
        expect(currentOrigin()).toBe('https://example.test');
        expect(readScrollY()).toBe(240);
    });

    it('writes history and scroll without reloading', () => {
        const fakeWindow = createWindow();
        vi.stubGlobal('window', fakeWindow);

        replaceUrl('/dashboard?tab=settings');
        pushUrl(new URL('https://example.test/dashboard?tab=interview'));
        scrollToTop();

        expect(fakeWindow.history.replaceState).toHaveBeenCalledWith(null, '', '/dashboard?tab=settings');
        expect(fakeWindow.history.pushState).toHaveBeenCalledWith(
            null,
            '',
            'https://example.test/dashboard?tab=interview'
        );
        expect(fakeWindow.scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' });
    });

    it('preserves explicit history state for tab navigation', () => {
        const fakeWindow = createWindow();
        vi.stubGlobal('window', fakeWindow);

        fakeWindow.history.state = { page: { url: '/auth/login' } };

        pushHistoryState({ authTab: 'register' }, '/auth/register');

        expect(fakeWindow.history.pushState).toHaveBeenCalledWith(
            { page: { url: '/auth/login' }, authTab: 'register' },
            '',
            '/auth/register'
        );
    });

    it('goes back only when history exists', () => {
        const fakeWindow = createWindow();
        vi.stubGlobal('window', fakeWindow);
        expect(goBack()).toBe(true);
        expect(fakeWindow.history.back).toHaveBeenCalledTimes(1);

        const firstPageWindow = createWindow(1);
        vi.stubGlobal('window', firstPageWindow);
        expect(goBack()).toBe(false);
        expect(firstPageWindow.history.back).not.toHaveBeenCalled();
    });

    it('assigns only allowed navigation urls', () => {
        const fakeWindow = createWindow();
        vi.stubGlobal('window', fakeWindow);

        assignUrl('/auth/login');
        assignUrl('https://accounts.google.com/o/oauth2');
        assignUrl('javascript:alert(1)');
        assignUrl('data:text/html,x');

        expect(fakeWindow.location.assign).toHaveBeenCalledTimes(2);
        expect(fakeWindow.location.assign).toHaveBeenNthCalledWith(1, '/auth/login');
        expect(fakeWindow.location.assign).toHaveBeenNthCalledWith(2, 'https://accounts.google.com/o/oauth2');
    });
});
