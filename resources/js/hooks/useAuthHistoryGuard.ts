import { router } from '@inertiajs/vue3';

const AUTH_PATH_PATTERN = /^\/auth(?:\/|$)/;

let registered = false;
let validationInFlight = false;

/** Return whether a pathname belongs to a browser-facing authentication page. */
function isAuthPath(pathname: string): boolean {
    return AUTH_PATH_PATTERN.test(pathname);
}

/** Revalidate a restored auth snapshot and leave it when the session is active. */
function validateRestoredAuthPage(): void {
    if (isAuthPath(window.location.pathname) === false || validationInFlight) return;

    validationInFlight = true;

    void fetch(window.location.href, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'text/html' },
        redirect: 'follow',
    })
        .then((response: Response): void => {
            const resolvedPath = new URL(response.url, window.location.origin).pathname;

            if (isAuthPath(window.location.pathname) && resolvedPath === '/') {
                window.location.replace('/');
            }
        })
        .catch((): undefined => undefined)
        .finally((): void => {
            validationInFlight = false;
        });
}

/** Guard auth history restores caused by Inertia navigation and browser BFCache. */
export function useAuthHistoryGuard(): void {
    if (registered) return;
    registered = true;

    router.on('navigate', (): void => {
        validateRestoredAuthPage();
    });

    window.addEventListener('popstate', (): void => {
        validateRestoredAuthPage();
    });

    window.addEventListener('pageshow', (event: PageTransitionEvent): void => {
        if (event.persisted) validateRestoredAuthPage();
    });
}
