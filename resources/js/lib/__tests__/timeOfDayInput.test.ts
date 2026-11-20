import { describe, expect, it } from 'vitest';
import { clampTimePart } from '@/lib/timeOfDayInput';

describe('clampTimePart', () => {
    it('menjepit jam ke 23 dan menit ke 59 saat dua digit', () => {
        expect(clampTimePart('hour', '25')).toBe('23');
        expect(clampTimePart('minute', '60')).toBe('59');
        expect(clampTimePart('minute', '99')).toBe('59');
    });

    it('membiarkan nilai yang masih dalam rentang', () => {
        expect(clampTimePart('hour', '00')).toBe('00');
        expect(clampTimePart('hour', '23')).toBe('23');
        expect(clampTimePart('minute', '59')).toBe('59');
    });

    it('membuang karakter selain angka dan memotong ke dua digit', () => {
        expect(clampTimePart('hour', '1a2')).toBe('12');
        expect(clampTimePart('minute', '1234')).toBe('12');
    });

    it('melengkapi satu digit saat fokus lepas', () => {
        expect(clampTimePart('hour', '5', true)).toBe('05');
        expect(clampTimePart('minute', '7', true)).toBe('07');
    });

    it('mengembalikan string kosong agar kolom bisa dikosongkan', () => {
        expect(clampTimePart('hour', '')).toBe('');
        expect(clampTimePart('minute', 'abc')).toBe('');
    });
});
