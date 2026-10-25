import { expect, test } from '@playwright/test';
import { formatDateTime } from '../../resources/js/lib/dateTime';

type TCase = [Parameters<typeof formatDateTime>[0], string];

const CASES: TCase[] = [
    [{ value: '2026-01-02' }, 'Jan 2, 2026'],
    [{ value: '2026-01-02', variant: 'date-medium' }, '2 Jan 2026'],
    [{ value: '2026-01-02', variant: 'date-long' }, '2 Januari 2026'],
    [{ value: '2026-01-02', variant: 'date-numeric' }, '2/1/2026'],
    [{ value: '2026-01-02', variant: 'date-weekday' }, 'Jum, 2 Jan'],
    [{ value: '2026-01-02T06:05:09Z', variant: 'time' }, '13.05.09'],
    [{ value: '2026-01-02T06:05:09Z', variant: 'time-short' }, '13.05'],
    [{ value: '2026-01-02T06:05:00Z', variant: 'date-time' }, 'Jan 2, 2026, 01:05 PM'],
    [{ value: '2026-01-02T06:05:00Z', variant: 'date-time-medium' }, '2 Jan 2026, 13.05'],
    [{ value: '2026-01-02T06:05:00Z', variant: 'date-time-weekday' }, 'Jum, 2 Jan, 13.05'],
    [{ value: '2026-01-02T06:05:09Z', variant: 'date-time-numeric' }, '2/1/2026, 13.05.09'],
    [{ value: '2026-01-02', variant: 'date-range', end: '2026-01-02' }, 'Jan 2, 2026'],
    [{ value: '2026-01-02', variant: 'date-range', end: '2026-01-03' }, 'Jan 2–3, 2026'],
    [{ value: '2026-01-02', variant: 'date-range', end: '2026-02-15' }, 'Jan 2–Feb 15, 2026'],
    [{ value: '2026-01-02', variant: 'date-range', end: '2027-03-10' }, 'Jan 2, 2026 — Mar 10, 2027'],
    [{ value: '2026-01-02', variant: 'date-range', end: null }, 'Jan 2, 2026'],
    [{ value: '2026-01-02', variant: 'date-range', end: '2025-12-01' }, 'Jan 2, 2026'],
    [{ value: null }, ''],
    [{ value: '' }, ''],
    [{ value: 'bukan-tanggal' }, ''],
];

test.describe('formatDateTime', () => {
    test('semua varian menghasilkan format yang disepakati', () => {
        const mismatches = CASES.filter(([options, expected]) => formatDateTime(options) !== expected).map(
            ([options, expected]) => `${JSON.stringify(options)} → ${JSON.stringify(formatDateTime(options))} (harap ${expected})`,
        );

        expect(mismatches).toEqual([]);
    });

    test('waktu dipin ke Asia/Jakarta', () => {
        expect(formatDateTime({ value: '2026-01-01T17:30:00Z' })).toBe('Jan 2, 2026');
    });
});
