import { describe, expect, it } from 'vitest';
import { formatRupiahPrice } from '@/lib/formatRupiahPrice';

describe('formatRupiahPrice', () => {
    it('formats whole rupiah with dot thousands separators', () => {
        expect(formatRupiahPrice(95_000)).toBe('Rp 95.000');
        expect(formatRupiahPrice(1_000_000)).toBe('Rp 1.000.000');
    });

    it('keeps zero readable', () => {
        expect(formatRupiahPrice(0)).toBe('Rp 0');
    });

    it('falls back to zero for unusable input', () => {
        expect(formatRupiahPrice(Number.NaN)).toBe('Rp 0');
        expect(formatRupiahPrice(Number.POSITIVE_INFINITY)).toBe('Rp 0');
        expect(formatRupiahPrice(-5)).toBe('Rp 0');
    });
});
