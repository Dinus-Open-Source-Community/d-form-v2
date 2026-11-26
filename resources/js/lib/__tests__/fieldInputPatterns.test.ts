import { describe, expect, it } from 'vitest';
import { isFieldInputSubtype, isValidFieldInput, sanitizeFieldInput } from '@/lib/fieldInputPatterns';

describe('sanitizeFieldInput', () => {
    it('membuang semua karakter selain angka pada field angka', () => {
        expect(sanitizeFieldInput('number', '12a3b')).toBe('123');
        expect(sanitizeFieldInput('number', '-5')).toBe('5');
    });

    it('mempertahankan tanda plus di awal nomor WhatsApp', () => {
        expect(sanitizeFieldInput('tel', '+62 812-3456-7890')).toBe('+6281234567890');
        expect(sanitizeFieldInput('tel', '0812 3456 7890')).toBe('081234567890');
    });

    it('memotong NIK dan kode pos di panjang tetapnya', () => {
        expect(sanitizeFieldInput('nik', '123456789012345678')).toBe('1234567890123456');
        expect(sanitizeFieldInput('postal_code', '1234567')).toBe('12345');
    });

    it('tidak mengubah isi email selain membuang spasi tepi', () => {
        expect(sanitizeFieldInput('email', '  nama@domain.com  ')).toBe('nama@domain.com');
    });
});

describe('isValidFieldInput', () => {
    it('menerima angka termasuk nol', () => {
        expect(isValidFieldInput('number', '0')).toBe(true);
        expect(isValidFieldInput('number', '007')).toBe(true);
    });

    it('menolak angka dengan tanda minus atau titik desimal', () => {
        expect(isValidFieldInput('number', '-1')).toBe(false);
        expect(isValidFieldInput('number', '1.5')).toBe(false);
    });

    it('menerima nomor WhatsApp Indonesia dengan dan tanpa kode negara', () => {
        expect(isValidFieldInput('tel', '081234567890')).toBe(true);
        expect(isValidFieldInput('tel', '+6281234567890')).toBe(true);
    });

    it('menolak nomor WhatsApp yang terlalu pendek', () => {
        expect(isValidFieldInput('tel', '0812')).toBe(false);
    });

    it('memvalidasi email', () => {
        expect(isValidFieldInput('email', 'nama@domain.com')).toBe(true);
        expect(isValidFieldInput('email', 'nama@domain')).toBe(false);
        expect(isValidFieldInput('email', 'nama domain@test.com')).toBe(false);
    });

    it('memvalidasi NIK, NIM, dan kode pos', () => {
        expect(isValidFieldInput('nik', '1234567890123456')).toBe(true);
        expect(isValidFieldInput('nik', '12345')).toBe(false);
        expect(isValidFieldInput('nim', '21120120140001')).toBe(true);
        expect(isValidFieldInput('nim', '1234')).toBe(false);
        expect(isValidFieldInput('postal_code', '50275')).toBe(true);
        expect(isValidFieldInput('postal_code', '5027')).toBe(false);
    });

    it('menganggap nilai kosong sah karena required ditangani aturan terpisah', () => {
        expect(isValidFieldInput('number', '')).toBe(true);
        expect(isValidFieldInput('email', '   ')).toBe(true);
    });
});

describe('isFieldInputSubtype', () => {
    it('mengenali subtype ketat dan menolak yang lain', () => {
        expect(isFieldInputSubtype('number')).toBe(true);
        expect(isFieldInputSubtype('text')).toBe(false);
    });
});
