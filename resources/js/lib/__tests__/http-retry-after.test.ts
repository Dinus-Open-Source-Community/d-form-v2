import { describe, expect, it } from 'vitest';
import { formatRetryAfterMessage, parseRetryAfterSeconds } from '@/lib/http-retry-after';

describe('parseRetryAfterSeconds', () => {
    it('returns null for absent headers', () => {
        expect(parseRetryAfterSeconds(null)).toBeNull();
        expect(parseRetryAfterSeconds(undefined)).toBeNull();
    });

    it.each([
        ['30', 30],
        [' 7 ', 7],
        ['0', 0],
    ])('parses %j as %i seconds', (value: string, expected: number) => {
        expect(parseRetryAfterSeconds(value)).toBe(expected);
    });

    it.each(['-5', '1.5', 'abc', ''])('rejects %j', (value: string) => {
        expect(parseRetryAfterSeconds(value)).toBeNull();
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
