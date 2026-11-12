import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import { useEventSearch, type IEventSearchFilters, type IEventSearchState } from '@/hooks/useEventSearch';

interface IHttpExceptionStub {
    status: number;
    headers: Record<string, string>;
}

type THttpExceptionListener = (event: { detail: { response: IHttpExceptionStub } }) => void;

/**
 * Registry listener `router.on()` + capture lifecycle hook. `onMounted` dijalankan
 * manual lewat `mountState()` karena hook diuji tanpa instance komponen (node env).
 */
const inertiaMock = vi.hoisted(() => {
    const listeners = new Map<string, THttpExceptionListener>();

    return {
        get: vi.fn(),
        on: vi.fn((type: string, listener: THttpExceptionListener): (() => void) => {
            listeners.set(type, listener);

            return (): void => {
                listeners.delete(type);
            };
        }),
        listeners,
    };
});

const lifecycle = vi.hoisted(() => ({
    mounted: [] as Array<() => void>,
    beforeUnmount: [] as Array<() => void>,
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        get: inertiaMock.get,
        on: inertiaMock.on,
    },
}));

vi.mock('vue', async (importOriginal) => {
    const actual = await importOriginal<typeof import('vue')>();

    return {
        ...actual,
        onMounted: (callback: () => void): void => {
            lifecycle.mounted.push(callback);
        },
        onBeforeUnmount: (callback: () => void): void => {
            lifecycle.beforeUnmount.push(callback);
        },
    };
});

const routerGetMock = vi.mocked(router.get);

/** Sama dengan `SEARCH_DEBOUNCE_MS` di hook; test memakai nilai ini untuk memajukan timer. */
const SEARCH_DEBOUNCE_MS = 350;
/** Jeda setelah debounce agar timer yang seharusnya dibatalkan ikut terlihat bila masih hidup. */
const AFTER_DEBOUNCE_MS = 500;

/** Baca `q` dari payload visit tanpa melebar ke `any`; `null` berarti payload tanpa filter. */
function readQ(payload: unknown): string | null {
    if (typeof payload !== 'object' || payload === null || !('q' in payload)) return null;

    const value: unknown = payload.q;

    return typeof value === 'string' ? value : null;
}

/** Nilai `q` pada call ke-`index`; `null` bila visit tidak membawa `q`. */
function sentQueryAt(index: number): string | null {
    const call = routerGetMock.mock.calls[index];

    return call === undefined ? null : readQ(call[1]);
}

/** Buat hook lalu jalankan `onMounted` yang tertunda, sehingga listener 429 terdaftar. */
function mountState(filters: IEventSearchFilters | null = null): IEventSearchState {
    const state = useEventSearch(filters);

    lifecycle.mounted.splice(0).forEach((run: () => void): void => {
        run();
    });

    return state;
}

/** Jalankan `onBeforeUnmount` milik hook seperti komponen benar-benar dilepas. */
function unmount(): void {
    lifecycle.beforeUnmount.splice(0).forEach((run: () => void): void => {
        run();
    });
}

/** Picu listener `httpException` global seperti respons 4xx/5xx dari Inertia. */
function triggerHttpException(status: number): void {
    inertiaMock.listeners.get('httpException')?.({ detail: { response: { status, headers: {} } } });
}

beforeEach(() => {
    vi.useFakeTimers();
    routerGetMock.mockClear();
    inertiaMock.listeners.clear();
    lifecycle.mounted.length = 0;
    lifecycle.beforeUnmount.length = 0;
});

afterEach(() => {
    vi.clearAllTimers();
    vi.useRealTimers();
});

describe('useEventSearch', () => {
    it('dua ketikan dalam satu window debounce hanya mengirim satu request dengan term terakhir', async () => {
        const state = mountState();

        state.query.value = 'even';
        state.requestDebouncedSearch();
        state.query.value = 'event';
        state.requestDebouncedSearch();
        await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS + 1);

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(sentQueryAt(0)).toBe('event');
    });

    it('reset saat debounce masih pending tidak menggandakan request', async () => {
        const state = mountState();

        state.query.value = 'event';
        state.requestDebouncedSearch();
        expect(vi.getTimerCount()).toBe(1);

        state.resetSearch();

        // Timer debounce benar-benar dibatalkan, bukan sekadar ditekan oleh guard term.
        expect(vi.getTimerCount()).toBe(0);

        await vi.advanceTimersByTimeAsync(AFTER_DEBOUNCE_MS);

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(sentQueryAt(0)).toBeNull();
    });

    it('reset dua kali berurutan hanya mengirim satu request', () => {
        const state = mountState();

        state.resetSearch();
        state.resetSearch();

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(sentQueryAt(0)).toBeNull();
    });

    it('kata kunci di bawah tiga karakter ditahan, tiga karakter dikirim', async () => {
        const state = mountState();

        state.query.value = 'ab';
        state.requestDebouncedSearch();
        await vi.advanceTimersByTimeAsync(AFTER_DEBOUNCE_MS);

        expect(routerGetMock).not.toHaveBeenCalled();

        state.query.value = 'abc';
        state.requestDebouncedSearch();
        await vi.advanceTimersByTimeAsync(AFTER_DEBOUNCE_MS);

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(sentQueryAt(0)).toBe('abc');
    });

    it('input hanya spasi diperlakukan sebagai reset tanpa `q`', async () => {
        // Filter awal non-kosong supaya kasus ini menguji spasi, bukan guard term yang sama.
        const state = mountState({ q: 'event' });

        state.query.value = '   ';
        state.requestDebouncedSearch();
        await vi.advanceTimersByTimeAsync(AFTER_DEBOUNCE_MS);

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(sentQueryAt(0)).toBeNull();
    });

    it('tidak mengulang filter yang sudah aktif di server', async () => {
        const state = mountState({ q: 'abc' });

        expect(state.query.value).toBe('abc');

        state.requestDebouncedSearch();
        await vi.advanceTimersByTimeAsync(AFTER_DEBOUNCE_MS);

        expect(routerGetMock).not.toHaveBeenCalled();
    });

    it('term yang sama boleh dikirim ulang setelah validasi gagal', async () => {
        const state = mountState();

        state.query.value = 'abc';
        state.requestDebouncedSearch();
        await vi.advanceTimersByTimeAsync(AFTER_DEBOUNCE_MS);

        expect(routerGetMock).toHaveBeenCalledTimes(1);

        const failedVisit = routerGetMock.mock.calls[0]?.[2];
        failedVisit?.onError?.({});

        state.requestDebouncedSearch();
        await vi.advanceTimersByTimeAsync(AFTER_DEBOUNCE_MS);

        expect(routerGetMock).toHaveBeenCalledTimes(2);
        expect(sentQueryAt(1)).toBe('abc');
    });

    it('term yang sama boleh dikirim ulang setelah gangguan jaringan', async () => {
        const state = mountState();

        state.query.value = 'abc';
        state.requestDebouncedSearch();
        await vi.advanceTimersByTimeAsync(AFTER_DEBOUNCE_MS);

        const failedVisit = routerGetMock.mock.calls[0]?.[2];
        failedVisit?.onNetworkError?.(new Error('offline'));

        state.requestDebouncedSearch();
        await vi.advanceTimersByTimeAsync(AFTER_DEBOUNCE_MS);

        expect(routerGetMock).toHaveBeenCalledTimes(2);
    });

    it('term yang sama boleh dikirim ulang setelah respons 5xx', async () => {
        const state = mountState();

        state.query.value = 'abc';
        state.requestDebouncedSearch();
        await vi.advanceTimersByTimeAsync(AFTER_DEBOUNCE_MS);

        triggerHttpException(500);

        state.requestDebouncedSearch();
        await vi.advanceTimersByTimeAsync(AFTER_DEBOUNCE_MS);

        expect(routerGetMock).toHaveBeenCalledTimes(2);
        expect(sentQueryAt(1)).toBe('abc');
    });

    it('debounce dibatalkan saat komponen dilepas', async () => {
        const state = mountState();

        state.query.value = 'abc';
        state.requestDebouncedSearch();

        unmount();
        await vi.advanceTimersByTimeAsync(AFTER_DEBOUNCE_MS);

        expect(routerGetMock).not.toHaveBeenCalled();
    });
});
