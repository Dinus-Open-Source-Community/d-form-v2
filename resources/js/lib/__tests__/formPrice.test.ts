import { describe, expect, it } from 'vitest';
import { formFieldPrice } from '@/lib/formPrice';

describe('formFieldPrice', () => {
    it('reads a positive rupiah price', () => {
        expect(formFieldPrice({ price: 95_000 })).toBe(95_000);
        expect(formFieldPrice({ price: '95' })).toBe(95);
    });

    it('treats zero, missing, and unusable values as no price', () => {
        expect(formFieldPrice({ price: 0 })).toBe(0);
        expect(formFieldPrice({})).toBe(0);
        expect(formFieldPrice(undefined)).toBe(0);
        expect(formFieldPrice({ price: 'abc' })).toBe(0);
        expect(formFieldPrice({ price: -5 })).toBe(0);
    });

    it('floors fractional values', () => {
        expect(formFieldPrice({ price: 1_500.9 })).toBe(1_500);
    });
});
