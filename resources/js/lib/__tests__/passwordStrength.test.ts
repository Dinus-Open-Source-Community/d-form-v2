import { describe, expect, it } from 'vitest';
import {
    PASSWORD_STRENGTH_HINT,
    PASSWORD_STRENGTH_MAX_SEGMENTS,
    passwordStrengthRules,
    passwordStrengthSegmentCount,
    passwordSubmitBlockerMessage,
} from '@/lib/passwordStrength';

describe('passwordStrengthRules', () => {
    it('menandai semua kriteria belum terpenuhi saat kosong', () => {
        expect(passwordStrengthRules('').map((rule) => rule.met)).toEqual([false, false, false]);
    });

    it('mempertahankan label dan urutan kriteria halaman daftar', () => {
        expect(passwordStrengthRules('').map((rule) => rule.label)).toEqual([
            'Minimal 8 karakter',
            'Huruf besar',
            'Angka',
        ]);
    });

    it('memenuhi kriteria panjang saja untuk delapan huruf kecil', () => {
        expect(passwordStrengthRules('rahasiaa')).toEqual([
            { label: 'Minimal 8 karakter', met: true },
            { label: 'Huruf besar', met: false },
            { label: 'Angka', met: false },
        ]);
    });

    it('meminta huruf besar dan angka selain panjang minimum', () => {
        expect(passwordStrengthRules('rahasi').filter((rule) => rule.met)).toHaveLength(0);
        expect(passwordStrengthRules('rahasiaku').map((rule) => rule.met)).toEqual([true, false, false]);
        expect(passwordStrengthRules('Rahasiaku').map((rule) => rule.met)).toEqual([true, true, false]);
        expect(passwordStrengthRules('Rahasia123').every((rule) => rule.met)).toBe(true);
    });
});

describe('passwordStrengthSegmentCount', () => {
    it('kosong berarti tidak ada segmen terisi', () => {
        expect(passwordStrengthSegmentCount('')).toBe(0);
    });

    it('mulai mengetik langsung mengisi satu segmen', () => {
        expect(passwordStrengthSegmentCount('a')).toBe(1);
    });

    it('bertambah satu segmen untuk tiap kriteria yang terpenuhi', () => {
        expect(passwordStrengthSegmentCount('rahasi')).toBe(1);
        expect(passwordStrengthSegmentCount('rahasiaku')).toBe(2);
        expect(passwordStrengthSegmentCount('Rahasiaku')).toBe(3);
        expect(passwordStrengthSegmentCount('Rahasia123')).toBe(PASSWORD_STRENGTH_MAX_SEGMENTS);
    });

    it('tidak pernah melewati batas segmen', () => {
        expect(passwordStrengthSegmentCount('Rahasia123456789')).toBe(PASSWORD_STRENGTH_MAX_SEGMENTS);
        expect(PASSWORD_STRENGTH_MAX_SEGMENTS).toBe(4);
    });
});

describe('passwordStrengthHint', () => {
    it('memakai kalimat hint halaman daftar tanpa perubahan', () => {
        expect(PASSWORD_STRENGTH_HINT).toBe('Minimal 8 karakter dengan huruf besar dan angka.');
    });
});

describe('passwordSubmitBlockerMessage', () => {
    it('menolak kata sandi di bawah delapan karakter dengan pesan halaman daftar', () => {
        expect(passwordSubmitBlockerMessage('Rahasia', 'Rahasia')).toBe('Password must be at least 8 characters.');
        expect(passwordSubmitBlockerMessage('', '')).toBe('Password must be at least 8 characters.');
    });

    it('menolak konfirmasi yang berbeda dengan pesan halaman daftar', () => {
        expect(passwordSubmitBlockerMessage('Rahasia123', 'Rahasia124')).toBe('Password does not match');
        expect(passwordSubmitBlockerMessage('Rahasia123', '')).toBe('Password does not match');
    });

    it('mengutamakan pesan panjang minimum sebelum pesan kecocokan', () => {
        expect(passwordSubmitBlockerMessage('Rahasia', 'Rahasia124')).toBe('Password must be at least 8 characters.');
    });

    it('lolos saat panjang cukup dan konfirmasi sama', () => {
        expect(passwordSubmitBlockerMessage('Rahasia123', 'Rahasia123')).toBeNull();
    });
});
