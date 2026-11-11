import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import { usePaginationNavigation } from '@/hooks/usePaginationNavigation';

/**
 * Kontrak emit/navigasi pagination: komponen hanya mengirim niat, hook membangun
 * URL dari query aktif (setara `withQueryString`) lalu memanggil `router.get`.
 * Environment `node`, jadi adapter browser di-mock dan router Inertia di-stub.
 */
const navigationMock = vi.hoisted(() => ({
    pathname: vi.fn<() => string>(),
    search: vi.fn<() => string>(),
}));

vi.mock('@/adapters/browser/navigation', () => ({
    currentPathname: (): string => navigationMock.pathname(),
    currentSearch: (): string => navigationMock.search(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        get: vi.fn(),
    },
}));

const routerGetMock = vi.mocked(router.get);

const EXPECTED_OPTIONS = { preserveState: true, preserveScroll: true, replace: true };

describe('usePaginationNavigation', () => {
    beforeEach(() => {
        routerGetMock.mockReset();
        navigationMock.pathname.mockReturnValue('/admin/events/1/report');
        navigationMock.search.mockReturnValue('?tab=attendance&page=1&cursor=old');
    });

    it('navigates to the emitted page while preserving the active query and dropping the cursor', () => {
        const { goToPage } = usePaginationNavigation();

        goToPage({ page: 3, perPage: 20, includePerPage: false });

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/events/1/report?tab=attendance&page=3',
            {},
            EXPECTED_OPTIONS
        );
    });

    it('writes per_page only when the payload says the selector is visible', () => {
        const { goToPage } = usePaginationNavigation();

        goToPage({ page: 2, perPage: 25, includePerPage: true });

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/events/1/report?tab=attendance&page=2&per_page=25',
            {},
            EXPECTED_OPTIONS
        );

        routerGetMock.mockReset();

        goToPage({ page: 2, perPage: 25, includePerPage: false });

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/events/1/report?tab=attendance&page=2',
            {},
            EXPECTED_OPTIONS
        );
    });

    it('returns to the first page on per-page change and ignores invalid values', () => {
        const { changePerPage } = usePaginationNavigation();

        changePerPage('50');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/events/1/report?tab=attendance&page=1&per_page=50',
            {},
            EXPECTED_OPTIONS
        );

        routerGetMock.mockReset();

        changePerPage('0');
        changePerPage('abc');

        expect(routerGetMock).not.toHaveBeenCalled();
    });

    it('follows the server cursor URL verbatim in cursor mode', () => {
        const { goToCursorUrl } = usePaginationNavigation();

        goToCursorUrl('/admin/events?cursor=next-cursor');

        expect(routerGetMock).toHaveBeenCalledWith('/admin/events?cursor=next-cursor', {}, EXPECTED_OPTIONS);
    });

    it('honours custom page parameter names', () => {
        navigationMock.search.mockReturnValue('');
        const { goToPage } = usePaginationNavigation({ pageParam: 'halaman', perPageParam: 'jumlah' });

        goToPage({ page: 4, perPage: 8, includePerPage: false });

        expect(routerGetMock).toHaveBeenCalledWith('/admin/events/1/report?halaman=4', {}, EXPECTED_OPTIONS);
    });
});
