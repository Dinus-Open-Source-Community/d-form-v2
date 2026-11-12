import { describe, expect, it } from 'vitest';
import {
    FIELD_MAX_LENGTH_LIMITS,
    fieldMaxLengthLimits,
    formatMaxLengthNumber,
    isMaxLengthOutOfRange,
    maxLengthHint,
    readMaxLengthState,
    sanitizeMaxLengthInput,
    stripNonDigits,
} from '@/lib/fieldMaxLength';

describe('batas per tipe', () => {
    it('short_text 1..1000, long_text 1..10000', () => {
        expect(FIELD_MAX_LENGTH_LIMITS.short_text).toEqual({ min: 1, max: 1000 });
        expect(FIELD_MAX_LENGTH_LIMITS.long_text).toEqual({ min: 1, max: 10_000 });
    });

    it('tipe teks lain memakai batas short_text', () => {
        expect(fieldMaxLengthLimits('email')).toEqual({ min: 1, max: 1000 });
        expect(fieldMaxLengthLimits('long_text')).toEqual({ min: 1, max: 10_000 });
    });

    it('hint memakai pemisah ribuan Indonesia', () => {
        expect(maxLengthHint(FIELD_MAX_LENGTH_LIMITS.short_text)).toBe('Minimal 1, maksimal 1.000 karakter');
        expect(maxLengthHint(FIELD_MAX_LENGTH_LIMITS.long_text)).toBe('Minimal 1, maksimal 10.000 karakter');
        expect(formatMaxLengthNumber(10_000)).toBe('10.000');
    });
});

describe('sanitizeMaxLengthInput', () => {
    const shortLimits = FIELD_MAX_LENGTH_LIMITS.short_text;
    const longLimits = FIELD_MAX_LENGTH_LIMITS.long_text;

    const cases: Array<[string, { min: number; max: number }, number | null]> = [
        ['1234567', shortLimits, 1000],
        ['1234567', longLimits, 10_000],
        ['99999999', longLimits, 10_000],
        ['12a3', shortLimits, 123],
        ['abc', shortLimits, null],
        ['', shortLimits, null],
        ['0', shortLimits, 1],
        ['-9', shortLimits, 9],
        ['1000', shortLimits, 1000],
        ['1001', shortLimits, 1000],
    ];

    for (const [raw, limits, expected] of cases) {
        it(`"${raw}" (${limits.min}..${limits.max}) → ${String(expected)}`, () => {
            expect(sanitizeMaxLengthInput(raw, limits)).toBe(expected);
        });
    }

    it('tidak pernah menghasilkan 100000 dari input kecil', () => {
        expect(sanitizeMaxLengthInput('5', shortLimits)).toBe(5);
        expect(sanitizeMaxLengthInput('0', shortLimits)).toBe(1);
    });
});

describe('stripNonDigits', () => {
    it('hanya menyisakan digit', () => {
        expect(stripNonDigits('12a3')).toBe('123');
        expect(stripNonDigits(' 50 ')).toBe('50');
        expect(stripNonDigits('-4')).toBe('4');
        expect(stripNonDigits('abc')).toBe('');
    });
});

describe('isMaxLengthOutOfRange', () => {
    it('menandai nilai lama di luar batas tipe', () => {
        expect(isMaxLengthOutOfRange(50_000, FIELD_MAX_LENGTH_LIMITS.short_text)).toBe(true);
        expect(isMaxLengthOutOfRange(1_001, FIELD_MAX_LENGTH_LIMITS.short_text)).toBe(true);
        expect(isMaxLengthOutOfRange(10_001, FIELD_MAX_LENGTH_LIMITS.long_text)).toBe(true);
        expect(isMaxLengthOutOfRange(1_000, FIELD_MAX_LENGTH_LIMITS.short_text)).toBe(false);
        expect(isMaxLengthOutOfRange(null, FIELD_MAX_LENGTH_LIMITS.short_text)).toBe(false);
    });
});

describe('readMaxLengthState (tampilan tanpa mengubah metadata)', () => {
    it('menampilkan nilai lama apa adanya walau di luar batas', () => {
        const state = readMaxLengthState(50_000, FIELD_MAX_LENGTH_LIMITS.short_text);

        expect(state).toMatchObject({ text: '50000', value: 50_000, outOfRange: true });
    });

    it('kosong/tidak valid → teks kosong, tanpa error', () => {
        expect(readMaxLengthState(null, FIELD_MAX_LENGTH_LIMITS.short_text)).toMatchObject({
            text: '',
            value: null,
            outOfRange: false,
        });
        expect(readMaxLengthState(0, FIELD_MAX_LENGTH_LIMITS.short_text).value).toBeNull();
        expect(readMaxLengthState('abc', FIELD_MAX_LENGTH_LIMITS.short_text).value).toBeNull();
    });

    it('nilai string legacy "80" dibaca sebagai 80', () => {
        expect(readMaxLengthState('80', FIELD_MAX_LENGTH_LIMITS.short_text)).toMatchObject({
            text: '80',
            value: 80,
            outOfRange: false,
        });
    });
});
