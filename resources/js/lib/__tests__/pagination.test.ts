import { describe, expect, it } from 'vitest';
import {
    buildPaginationTargetUrl,
    findCursorUrls,
    formatCompactNumber,
    pageWindow,
    parsePerPageValue,
    resolvePageRequest,
} from '@/lib/pagination';

describe('formatCompactNumber', () => {
    it('keeps small totals as plain digits', () => {
        expect(formatCompactNumber(0)).toBe('0');
        expect(formatCompactNumber(999)).toBe('999');
    });

    it.each([
        [999, '999'],
        [1_000, '1k'],
        [4_600, '4.6k'],
        [1_000_000, '1m'],
        [1_200_000, '1.2m'],
        [1_000_000_000, '1b'],
    ])('compacts %i to %s', (value: number, expected: string) => {
        expect(formatCompactNumber(value)).toBe(expected);
    });

    it('promotes values whose rounding crosses the next unit boundary', () => {
        expect(formatCompactNumber(999_999)).toBe('1m');
        expect(formatCompactNumber(999_999_999)).toBe('1b');
    });

    it('keeps near-boundary values that do not round up', () => {
        expect(formatCompactNumber(999_400)).toBe('999.4k');
        expect(formatCompactNumber(999_949)).toBe('999.9k');
    });
});

describe('pageWindow', () => {
    it('returns an empty window when there is no page', () => {
        expect(pageWindow(1, 0)).toEqual([]);
        expect(pageWindow(3, -2)).toEqual([]);
    });

    it('clamps a current page below one', () => {
        expect(pageWindow(-4, 5)).toEqual([1, 2, 3, 4, 5]);
    });

    it('clamps a current page above the last page', () => {
        expect(pageWindow(99, 4)).toEqual([1, 2, 3, 4]);
    });

    it('lists every page for a short range', () => {
        expect(pageWindow(3, 7)).toEqual([1, 2, 3, 4, 5, 6, 7]);
    });

    it('collapses a long range into a boundary/sibling window', () => {
        expect(pageWindow(14, 125_000)).toEqual([1, 'ellipsis', 13, 14, 15, 'ellipsis', 20]);
    });
});

describe('findCursorUrls', () => {
    it('reads Indonesian paginator labels', () => {
        const urls = findCursorUrls([
            { url: 'https://api.test?page=1', label: '&laquo; Sebelumnya' },
            { url: 'https://api.test?page=3', label: 'Berikutnya &raquo;' },
        ]);

        expect(urls).toEqual({ previous: 'https://api.test?page=1', next: 'https://api.test?page=3' });
    });

    it('reads English paginator labels', () => {
        const urls = findCursorUrls([
            { url: '/page/1', label: 'Previous' },
            { url: null, label: 'Next' },
        ]);

        expect(urls).toEqual({ previous: '/page/1', next: null });
    });

    it('keeps the disabled side null while the other side still matches', () => {
        const urls = findCursorUrls([
            { url: null, label: 'Previous' },
            { url: '/page/3', label: 'Next' },
        ]);

        expect(urls).toEqual({ previous: null, next: '/page/3' });
    });

    it('returns nulls when no label matches', () => {
        expect(findCursorUrls([{ url: '/1', label: '1' }])).toEqual({ previous: null, next: null });
    });
});

describe('buildPaginationTargetUrl', () => {
    it('keeps the active query, replaces the page, and drops the cursor marker', () => {
        expect(
            buildPaginationTargetUrl({
                pathname: '/admin/events/1/report',
                search: '?tab=attendance&page=1&per_page=20&cursor=abc',
                page: 3,
                perPage: 20,
                pageParam: 'page',
                perPageParam: 'per_page',
                includePerPage: true,
            })
        ).toBe('/admin/events/1/report?tab=attendance&page=3&per_page=20');
    });

    it('omits per_page when the per-page selector is hidden', () => {
        expect(
            buildPaginationTargetUrl({
                pathname: '/admin/users',
                search: '?search=ada',
                page: 2,
                perPage: 25,
                pageParam: 'page',
                perPageParam: 'per_page',
                includePerPage: false,
            })
        ).toBe('/admin/users?search=ada&page=2');
    });

    it('works with an empty search and honours custom parameter names', () => {
        expect(
            buildPaginationTargetUrl({
                pathname: '/recruitment',
                search: '',
                page: 2,
                perPage: 10,
                pageParam: 'halaman',
                perPageParam: 'jumlah',
                includePerPage: true,
            })
        ).toBe('/recruitment?halaman=2&jumlah=10');
    });
});

describe('parsePerPageValue', () => {
    it('accepts positive integers', () => {
        expect(parsePerPageValue('8')).toBe(8);
        expect(parsePerPageValue('100')).toBe(100);
    });

    it.each(['0', '-5', '2.5', 'abc', ''])('rejects %s', (raw: string) => {
        expect(parsePerPageValue(raw)).toBeNull();
    });
});

describe('resolvePageRequest', () => {
    const base = { currentPage: 2, lastPage: 5, perPage: 20, isCursorMode: false, includePerPage: true };

    it('accepts an in-range page change and carries the active per-page value', () => {
        expect(resolvePageRequest({ ...base, requestedPage: 3 })).toEqual({
            page: 3,
            perPage: 20,
            includePerPage: true,
        });
    });

    it('carries the visibility of the per-page selector instead of page config', () => {
        expect(resolvePageRequest({ ...base, requestedPage: 3, includePerPage: false })).toEqual({
            page: 3,
            perPage: 20,
            includePerPage: false,
        });
    });

    it('ignores the current page, out-of-range pages, and cursor mode', () => {
        expect(resolvePageRequest({ ...base, requestedPage: 2 })).toBeNull();
        expect(resolvePageRequest({ ...base, requestedPage: 0 })).toBeNull();
        expect(resolvePageRequest({ ...base, requestedPage: 6 })).toBeNull();
        expect(resolvePageRequest({ ...base, requestedPage: 3, isCursorMode: true })).toBeNull();
    });

    it('treats a null lastPage as unbounded', () => {
        expect(resolvePageRequest({ ...base, lastPage: null, requestedPage: 12 })).toEqual({
            page: 12,
            perPage: 20,
            includePerPage: true,
        });
    });

    it('rejects non-integer pages', () => {
        expect(resolvePageRequest({ ...base, requestedPage: 2.5 })).toBeNull();
    });
});
