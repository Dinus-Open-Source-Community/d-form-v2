import { describe, expect, it } from 'vitest';
import { formatRetryAfterMessage, parseRetryAfterSeconds } from '@/lib/http-retry-after';

/**
 * Tabel tunggal untuk satu-satunya parser `Retry-After`. Baik hook pra-adapter
 * (`useEventSearch`, `useGlobalHttpStatusToast`) maupun adapter HTTP memakai
 * fungsi ini, jadi setiap kasus cukup diuji sekali di sini.
 */
describe('parseRetryAfterSeconds', () => {
    it.each<[string, unknown, number | null]>([
        ['digit string', '30', 30],
        ['trimmed digit string', ' 7 ', 7],
        ['zero means retry now', '0', 0],
        ['integer number', 12, 12],
        ['zero number', 0, 0],
        ['fractional number', 1.2, null],
        ['negative string', '-5', null],
        ['negative number', -1, null],
        ['decimal string', '1.5', null],
        ['text', 'abc', null],
        ['empty string', '', null],
        ['whitespace only', '   ', null],
        ['http date form', 'Wed, 21 Oct 2015 07:28:00 GMT', null],
        ['null', null, null],
        ['undefined', undefined, null],
        ['boolean', true, null],
        ['object', {}, null],
        ['array', ['30'], null],
    ])('parses %s as %j', (_label: string, value: unknown, expected: number | null) => {
        expect(parseRetryAfterSeconds(value)).toBe(expected);
    });

    it('keeps the adapter contract: zero and invalid values are not a positive cooldown', () => {
        const positive = (value: unknown): number | null => {
            const seconds = parseRetryAfterSeconds(value);
            return seconds !== null && seconds > 0 ? seconds : null;
        };

        expect(positive('30')).toBe(30);
        expect(positive('0')).toBeNull();
        expect(positive(1.2)).toBeNull();
    });
});

describe('formatRetryAfterMessage', () => {
    it('ceils a positive countdown', () => {
        expect(formatRetryAfterMessage(1.2)).toBe('Terlalu banyak percobaan. Coba lagi dalam 2 detik.');
        expect(formatRetryAfterMessage(30)).toBe('Terlalu banyak percobaan. Coba lagi dalam 30 detik.');
    });

    it.each([0, -5, Number.NaN])('uses the generic message for %d', (seconds: number) => {
        expect(formatRetryAfterMessage(seconds)).toBe('Terlalu banyak percobaan. Coba lagi sebentar lagi.');
    });
});
