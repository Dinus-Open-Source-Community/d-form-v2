import { router } from '@inertiajs/vue3';
import { currentPathname, currentSearch } from '@/adapters/browser/navigation';
import { buildPaginationTargetUrl, parsePerPageValue } from '@/lib/pagination';
import type { IPaginationPageChange } from '@/types/pagination';

/** Navigasi pagination tidak mereset state halaman dan tidak menumpuk history. */
const NAVIGATION_OPTIONS = { preserveState: true, preserveScroll: true, replace: true } as const;

export interface IPaginationNavigationOptions {
    /** Nama query parameter halaman; default `page`. */
    pageParam?: string;
    /** Nama query parameter jumlah baris; default `per_page`. */
    perPageParam?: string;
}

export interface IPaginationNavigation {
    /** Pindah ke halaman nomor dengan seluruh query aktif dipertahankan. */
    goToPage: (change: IPaginationPageChange) => void;
    /** Ganti jumlah baris per halaman lalu kembali ke halaman pertama. */
    changePerPage: (rawPerPage: string) => void;
    /** Mode cursor: ikuti URL prev/next dari server apa adanya. */
    goToCursorUrl: (url: string) => void;
}

/**
 * Pemilik keputusan navigasi pagination. Komponen `AppPagination` hanya emit
 * niat; hook ini membangun URL dari query aktif (setara `withQueryString`) dan
 * menjalankan `router.get` dengan opsi yang sama seperti sebelumnya.
 */
export function usePaginationNavigation(options: IPaginationNavigationOptions = {}): IPaginationNavigation {
    const pageParam = options.pageParam ?? 'page';
    const perPageParam = options.perPageParam ?? 'per_page';

    function navigateToUrl(url: string): void {
        router.get(url, {}, NAVIGATION_OPTIONS);
    }

    function goToPage(change: IPaginationPageChange): void {
        navigateToUrl(
            buildPaginationTargetUrl({
                pathname: currentPathname(),
                search: currentSearch(),
                page: change.page,
                perPage: change.perPage,
                pageParam,
                perPageParam,
                includePerPage: change.includePerPage,
            })
        );
    }

    function changePerPage(rawPerPage: string): void {
        const perPage = parsePerPageValue(rawPerPage);
        if (perPage === null) return;

        navigateToUrl(
            buildPaginationTargetUrl({
                pathname: currentPathname(),
                search: currentSearch(),
                page: 1,
                perPage,
                pageParam,
                perPageParam,
                includePerPage: true,
            })
        );
    }

    function goToCursorUrl(url: string): void {
        navigateToUrl(url);
    }

    return { goToPage, changePerPage, goToCursorUrl };
}
