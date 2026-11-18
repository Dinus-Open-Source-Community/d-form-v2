import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import { useUserDirectoryFilters } from '@/hooks/useUserDirectoryFilters';
import type { IUserIndexQuery } from '@/types/user-management';

vi.mock('@inertiajs/vue3', () => ({
    router: {
        get: vi.fn(),
    },
}));

const routerGetMock = vi.mocked(router.get);

const INDEX_QUERY: IUserIndexQuery = { search: '', role: null, per_page: 50, edit: null, create: false };

/** URL yang dikirim pada panggilan ke-`callIndex`; gagal keras bila tidak ada. */
function requestedUrl(callIndex: number): string {
    const call = routerGetMock.mock.calls[callIndex];
    if (call === undefined) {
        throw new Error(`router.get hanya dipanggil ${routerGetMock.mock.calls.length} kali`);
    }

    const url = call[0];
    if (typeof url !== 'string') {
        throw new Error('router.get harus menerima URL string pada direktori pengguna');
    }

    return url;
}

describe('useUserDirectoryFilters', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('sends one request for rapid typing', () => {
        const filters = useUserDirectoryFilters(INDEX_QUERY);

        for (const term of ['b', 'bu', 'bud', 'budi']) {
            filters.search.value = term;
        }

        vi.advanceTimersByTime(350);

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(requestedUrl(0)).toBe('/admin/users?search=budi&per_page=50');
    });

    it('sends immediately when the role chip changes', () => {
        const filters = useUserDirectoryFilters(INDEX_QUERY);

        filters.role.value = 'admin';

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(requestedUrl(0)).toBe('/admin/users?role=admin&per_page=50');
    });

    it('drops page when a filter changes', () => {
        const queryWithPage: IUserIndexQuery & { page: number } = { ...INDEX_QUERY, page: 3 };
        const filters = useUserDirectoryFilters(queryWithPage);

        filters.role.value = 'member';

        const requested = new URL(requestedUrl(0), 'https://d-form.test');
        expect(requested.searchParams.has('page')).toBe(false);
        expect(requested.searchParams.get('per_page')).toBe('50');
    });

    it('keeps per_page from the current query', () => {
        const filters = useUserDirectoryFilters(INDEX_QUERY);

        filters.search.value = 'budi';
        vi.advanceTimersByTime(350);

        expect(requestedUrl(0)).toContain('per_page=50');
    });

    it('resetFilters clears search and role and requests once', () => {
        const filters = useUserDirectoryFilters(INDEX_QUERY);

        filters.search.value = 'budi';
        vi.advanceTimersByTime(350);
        expect(routerGetMock).toHaveBeenCalledTimes(1);

        filters.resetFilters();

        expect(filters.search.value).toBe('');
        expect(filters.role.value).toBe('');
        expect(routerGetMock).toHaveBeenCalledTimes(2);
        expect(requestedUrl(1)).toBe('/admin/users?per_page=50');

        // Debounce yang tertunda tidak boleh menambah request setelah reset.
        vi.advanceTimersByTime(350);
        expect(routerGetMock).toHaveBeenCalledTimes(2);
    });

    it('hasActiveFilters is false when both are empty', () => {
        const filters = useUserDirectoryFilters(INDEX_QUERY);

        expect(filters.hasActiveFilters.value).toBe(false);

        filters.role.value = 'admin';
        expect(filters.hasActiveFilters.value).toBe(true);

        filters.resetFilters();
        expect(filters.hasActiveFilters.value).toBe(false);
    });
});
