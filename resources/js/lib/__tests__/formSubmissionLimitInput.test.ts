import { describe, expect, it } from 'vitest';
import {
    FORM_SUBMISSION_LIMIT_PATTERN,
    MAX_FORM_SUBMISSIONS,
    MIN_FORM_SUBMISSIONS,
    filterFormSubmissionLimitInput,
    hasFormSubmissionLimitFormatError,
    validateFormSubmissionLimitInput,
} from '@/lib/formSubmissionLimitInput';

describe('form submission limit input', () => {
    it('exposes the shared digit-only contract and bounds', () => {
        expect(FORM_SUBMISSION_LIMIT_PATTERN).toBe('^[0-9]+$');
        expect(MIN_FORM_SUBMISSIONS).toBe(2);
        expect(MAX_FORM_SUBMISSIONS).toBe(10000);
    });

    it.each(['2', '10000'])('accepts a valid boundary value: %s', (value: string) => {
        expect(validateFormSubmissionLimitInput(value)).toEqual({
            valid: true,
            value: Number(value),
            message: null,
        });
    });

    it.each([
        ['', 'Masukkan jumlah pengiriman maksimum.'],
        ['1', 'Jumlah pengiriman maksimum minimal 2.'],
        ['10001', 'Jumlah pengiriman maksimum maksimal 10000.'],
        ['2.5', 'Gunakan angka bulat tanpa tanda atau desimal.'],
        ['+2', 'Gunakan angka bulat tanpa tanda atau desimal.'],
        [' 2', 'Gunakan angka bulat tanpa tanda atau desimal.'],
        ['2abc', 'Gunakan angka bulat tanpa tanda atau desimal.'],
    ])('rejects invalid value %j', (value: string, message: string) => {
        expect(validateFormSubmissionLimitInput(value)).toEqual({
            valid: false,
            value: null,
            message,
        });
    });

    it('keeps only ASCII digits when typing or pasting', () => {
        expect(filterFormSubmissionLimitInput('a2.5 +10000 x')).toBe('2510000');
        expect(filterFormSubmissionLimitInput('１２')).toBe('');
    });

    it('retains a format error after filtering malformed input', () => {
        expect(hasFormSubmissionLimitFormatError('2abc')).toBe(true);
        expect(hasFormSubmissionLimitFormatError('2.5')).toBe(true);
        expect(hasFormSubmissionLimitFormatError('2')).toBe(false);
    });
});
