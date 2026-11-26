import { describe, expect, it } from 'vitest';
import { formatDateTime } from '@/lib/dateTime';

describe('formatDateTime', () => {
    it('membaca nilai tanpa zona sebagai waktu dinding Jakarta', () => {
        // Input `datetime-local` di 23.59 WIB tetap 31 Desember walau TZ proses UTC.
        expect(formatDateTime({ value: '2026-12-31T23:59', variant: 'date-medium' })).toBe('31 Des 2026');
        expect(formatDateTime({ value: '2026-12-01T08:00', variant: 'date-medium' })).toBe('1 Des 2026');
    });

    it('mengubah nilai ISO berzona ke kalender Jakarta', () => {
        expect(formatDateTime({ value: '2026-12-31T16:59:00.000000Z', variant: 'date-medium' })).toBe('31 Des 2026');
        expect(formatDateTime({ value: '2026-12-31T00:00:00+07:00', variant: 'date-medium' })).toBe('31 Des 2026');
    });

    it('mempertahankan tanggal kalender untuk nilai tanpa waktu', () => {
        expect(formatDateTime({ value: '2026-12-31', variant: 'date-long' })).toBe('31 Desember 2026');
    });

    it('mengembalikan string kosong untuk nilai tidak valid', () => {
        expect(formatDateTime({ value: '' })).toBe('');
        expect(formatDateTime({ value: null })).toBe('');
        expect(formatDateTime({ value: 'bukan-tanggal' })).toBe('');
    });

    it('memformat rentang dengan zona yang sama', () => {
        expect(formatDateTime({ value: '2026-12-01T00:00', end: '2026-12-01T23:59', variant: 'date-range' })).toBe(
            'Dec 1, 2026'
        );
    });
});
