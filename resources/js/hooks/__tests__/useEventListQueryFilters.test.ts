import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    useEventListQueryFilters,
    type IEventListQueryFilters,
    type IEventListQueryFilterInput,
} from '@/hooks/useEventListQueryFilters';

vi.mock('@inertiajs/vue3', () => ({
    router: {
        get: vi.fn(),
    },
}));

const routerGetMock = vi.mocked(router.get);

const TARGET_URL = '/dashboard/events';
const PER_PAGE = 8;

/** Guard sempit untuk membaca payload visit tanpa melebar ke `any`. */
function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

/** Payload data pada call ke-`index`; objek kosong bila visit tanpa data. */
function sentPayloadAt(index: number): Record<string, unknown> {
    const payload: unknown = routerGetMock.mock.calls[index]?.[1];

    return isRecord(payload) ? payload : {};
}

/** Opsi visit pada call ke-`index`; objek kosong bila tidak terbaca. */
function sentOptionsAt(index: number): Record<string, unknown> {
    const options: unknown = routerGetMock.mock.calls[index]?.[2];

    return isRecord(options) ? options : {};
}

/** Hook dengan query reaktif supaya test bisa menyimulasikan sinkronisasi props Inertia. */
function createFilters(initialQuery: IEventListQueryFilterInput): {
    query: Ref<IEventListQueryFilterInput>;
    filters: IEventListQueryFilters;
} {
    const query = ref<IEventListQueryFilterInput>(initialQuery);
    const filters = useEventListQueryFilters({
        query: (): IEventListQueryFilterInput => query.value,
        perPage: (): number => PER_PAGE,
        targetUrl: TARGET_URL,
    });

    return { query, filters };
}

beforeEach(() => {
    routerGetMock.mockClear();
});

describe('useEventListQueryFilters', () => {
    it('membaca state filter awal dari query props', () => {
        const { filters } = createFilters({
            search: 'seminar',
            filter: { categories: ['rkt'], sessions: ['pagi'] },
        });

        expect(filters.searchQuery.value).toBe('seminar');
        expect(filters.filterCategory.value).toBe('rkt');
        expect(filters.filterSession.value).toBe('pagi');
    });

    it('applyFilters mengirim per_page, filter, dan opsi visit yang sama seperti halaman acara', async () => {
        const { filters } = createFilters({ filter: { categories: ['rkt'] } });

        // Sinkronisasi awal melepas guard setelah tick pertama, sama seperti saat halaman terpasang.
        await nextTick();
        filters.applyFilters();

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(routerGetMock.mock.calls[0]?.[0]).toBe(TARGET_URL);
        expect(sentPayloadAt(0)).toEqual({ per_page: PER_PAGE, filter: { categories: ['rkt'] } });
        expect(sentOptionsAt(0)).toEqual({
            preserveState: true,
            preserveScroll: true,
            only: ['events', 'query'],
        });
    });

    it("nilai 'all' dan pencarian kosong tidak dikirim", () => {
        const { filters } = createFilters({ search: '   ', filter: { categories: ['all'], sessions: ['all'] } });

        const params = filters.buildQueryParams();

        expect(params).toEqual({ per_page: PER_PAGE });
        expect('search' in params).toBe(false);
        expect('filter' in params).toBe(false);
    });

    it('pencarian di-trim dan sesi non-aktif tetap dikirim sebagai filter', () => {
        const { filters } = createFilters({ search: '  seminar  ', filter: { sessions: ['pagi'] } });

        expect(filters.buildQueryParams()).toEqual({
            search: 'seminar',
            per_page: PER_PAGE,
            filter: { sessions: ['pagi'] },
        });
    });

    it('sinkronisasi query props tidak memicu request, perubahan filter setelahnya memicu satu request', async () => {
        const { query, filters } = createFilters({
            search: 'seminar',
            filter: { categories: ['rkt'] },
        });

        query.value = { search: 'workshop', filter: { categories: ['pelatihan'] } };
        await nextTick();

        expect(routerGetMock).not.toHaveBeenCalled();
        expect(filters.filterCategory.value).toBe('pelatihan');
        expect(filters.searchQuery.value).toBe('workshop');

        filters.filterCategory.value = 'lain';
        await nextTick();

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(sentPayloadAt(0)).toEqual({
            search: 'workshop',
            per_page: PER_PAGE,
            filter: { categories: ['lain'] },
        });
    });
});
