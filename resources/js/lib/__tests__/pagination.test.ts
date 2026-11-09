import { describe, expect, it } from 'vitest';
import { findCursorUrls, formatCompactNumber, pageWindow } from '@/lib/pagination';

describe('formatCompactNumber', () => {
    it('keeps small totals as plain digits', () => {
        expect(formatCompactNumber(0)).toBe('0');
        expect(formatCompactNumber(999)).toBe('999');
    });

    it.each([
        [1_000, '1k'],
        [4_600, '4.6k'],
        [1_000_000, '1m'],
        [1_200_000, '1.2m'],
        [1_000_000_000, '1b'],
    ])('compacts %i to %s', (value: number, expected: string) => {
        expect(formatCompactNumber(value)).toBe(expected);
    });

    it('does not promote a near-million total to the next unit', () => {
        // Current behavior: 999_999 rounds up to "1000k"; promotion to "1m" is not implemented.
        expect(formatCompactNumber(999_999)).toBe('1000k');
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
