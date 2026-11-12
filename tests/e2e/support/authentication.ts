import { request as playwrightRequest, type APIRequestContext, type BrowserContext } from '@playwright/test';
import { sessionCookieName } from './authGuard';
import { readEnvironment } from './environment';

/** Storage state yang dibuat oleh API login E2E dan dapat dipakai browser context. */
export type TPlaywrightStorageState = Awaited<ReturnType<APIRequestContext['storageState']>>;

interface IAuthenticatedDashboardPayload {
    props: {
        auth: {
            user: {
                email: string;
            };
        };
    };
}

/** Login dengan cookie jar kosong, validasi identitas dashboard, lalu kembalikan state sesi. */
export async function loginE2eUser(email: string, password: string): Promise<TPlaywrightStorageState> {
    const environment = readEnvironment();
    const api = await playwrightRequest.newContext({
        baseURL: environment.baseUrl,
        storageState: { cookies: [], origins: [] },
    });

    try {
        await api.get('/auth/login', { headers: { Accept: 'text/html' }, failOnStatusCode: false });

        const cookies = (await api.storageState()).cookies;
        const xsrfCookie = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');
        const xsrfToken = xsrfCookie === undefined ? '' : decodeURIComponent(xsrfCookie.value);

        const response = await api.post('/auth/login', {
            form: { email, password },
            headers: { 'X-XSRF-TOKEN': xsrfToken, Accept: 'text/html' },
            maxRedirects: 0,
            failOnStatusCode: false,
        });

        if (![200, 302].includes(response.status())) {
            throw new Error(`Login E2E gagal (HTTP ${response.status()}).`);
        }

        const dashboardResponse = await api.get('/dashboard', {
            maxRedirects: 0,
            failOnStatusCode: false,
        });

        if (dashboardResponse.status() !== 200) {
            throw new Error(`Sesi login E2E tidak dapat membuka dashboard (HTTP ${dashboardResponse.status()}).`);
        }

        if (authenticatedEmailFromDashboardHtml(await dashboardResponse.text()) !== email) {
            throw new Error('Sesi login E2E tidak terautentikasi sebagai aktor yang diminta.');
        }

        const state = await api.storageState();

        if (!state.cookies.some((cookie) => cookie.name === sessionCookieName() && cookie.value !== '')) {
            throw new Error('Login E2E tidak menghasilkan cookie sesi.');
        }

        return state;
    } finally {
        await api.dispose();
    }
}

/** Pastikan state yang dipindahkan ke browser context tetap mewakili aktor yang diminta. */
export async function assertBrowserContextAuthenticatedAs(context: BrowserContext, email: string): Promise<void> {
    const response = await context.request.get('/dashboard', {
        maxRedirects: 0,
        failOnStatusCode: false,
    });

    if (response.status() !== 200 || authenticatedEmailFromDashboardHtml(await response.text()) !== email) {
        throw new Error('Browser context tidak terautentikasi sebagai aktor E2E yang diharapkan.');
    }
}

function authenticatedEmailFromDashboardHtml(html: string): string {
    const raw = /<script data-page="app" type="application\/json">([\s\S]*?)<\/script>/.exec(html)?.[1];

    if (raw === undefined) {
        throw new Error('Dashboard tidak memuat payload Inertia yang diharapkan.');
    }

    const payload: unknown = JSON.parse(raw);

    if (!isAuthenticatedDashboardPayload(payload)) {
        throw new Error('Payload dashboard tidak memiliki identitas user yang valid.');
    }

    return payload.props.auth.user.email;
}

function isAuthenticatedDashboardPayload(value: unknown): value is IAuthenticatedDashboardPayload {
    if (typeof value !== 'object' || value === null || !('props' in value)) {
        return false;
    }

    const props = value.props;

    if (typeof props !== 'object' || props === null || !('auth' in props)) {
        return false;
    }

    const auth = props.auth;

    if (typeof auth !== 'object' || auth === null || !('user' in auth)) {
        return false;
    }

    const user = auth.user;

    return typeof user === 'object' && user !== null && 'email' in user && typeof user.email === 'string';
}
