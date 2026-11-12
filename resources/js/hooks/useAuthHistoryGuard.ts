import { router } from '@inertiajs/vue3';
import { assignUrl, currentHref, currentOrigin, currentPathname } from '@/adapters/browser/navigation';
import { onWindowEvent } from '@/adapters/browser/domEvents';

const AUTH_PATH_PATTERN = /^\/auth(?:\/|$)/;
let registered = false;
let validationInFlight = false;

/** Return whether a pathname belongs to a browser-facing authentication page. */
function isAuthPath(pathname: string): boolean {
    return AUTH_PATH_PATTERN.test(pathname);
}

/**
 * Revalidate a restored auth snapshot and leave it when the session is active.
 * Adapter HTTP dimuat dinamis agar axios tidak masuk entry chunk; guard hanya
 * berjalan di halaman `/auth*`.
 */
function validateRestoredAuthPage(): void {
    if (isAuthPath(currentPathname()) === false || validationInFlight) return;

    validationInFlight = true;

    void (async (): Promise<void> => {
        try {
            const { probePageUrl } = await import('@/adapters/http/httpClient');
            const finalUrl = await probePageUrl(currentHref());
            const resolvedPath = new URL(finalUrl, currentOrigin()).pathname;

            if (isAuthPath(currentPathname()) && resolvedPath === '/') {
                assignUrl('/');
            }
        } catch {
            // Probe bersifat best-effort: kegagalan jaringan/chunk tidak boleh
            // memunculkan unhandled rejection saat guard global berjalan.
        } finally {
            validationInFlight = false;
        }
    })();
}

/** Guard auth history restores caused by Inertia navigation and browser BFCache. */
export function useAuthHistoryGuard(): void {
    if (registered) return;
    registered = true;

    router.on('navigate', (): void => {
        validateRestoredAuthPage();
    });

    onWindowEvent('popstate', (): void => {
        validateRestoredAuthPage();
    });

    onWindowEvent('pageshow', (event: PageTransitionEvent): void => {
        if (event.persisted) validateRestoredAuthPage();
    });
}
