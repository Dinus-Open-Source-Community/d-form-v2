import { readWindow } from '@/adapters/browser/environment';

/** True bila media query cocok; `false` saat SSR. */
export function matchesMediaQuery(query: string): boolean {
    const browserWindow = readWindow();
    if (browserWindow === null || typeof browserWindow.matchMedia !== 'function') return false;

    return browserWindow.matchMedia(query).matches;
}

/**
 * Berlangganan perubahan media query.
 * Mengembalikan fungsi pelepas; no-op saat SSR.
 */
export function onMediaQueryChange(query: string, listener: (matches: boolean) => void): () => void {
    const browserWindow = readWindow();
    if (browserWindow === null || typeof browserWindow.matchMedia !== 'function') return () => undefined;

    const mediaQuery = browserWindow.matchMedia(query);
    const handler = (event: MediaQueryListEvent): void => listener(event.matches);
    mediaQuery.addEventListener('change', handler);

    return () => mediaQuery.removeEventListener('change', handler);
}
