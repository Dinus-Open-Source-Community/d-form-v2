import { describe, expect, it } from 'vitest';
import { fileExtension, formatFileSize, isPreviewableImage, uploadPercent } from '@/lib/fileDisplay';

describe('formatFileSize', () => {
    const cases: Array<[number, string]> = [
        [0, '0 B'],
        [512, '512 B'],
        [1024, '1 KB'],
        [716_800, '700 KB'],
        [2_411_724, '2,3 MB'],
        [52_428_800, '50 MB'],
        [12 * 1024 * 1024, '12 MB'],
        [1024 * 1024 * 1024, '1,0 GB'],
        [1.5 * 1024 * 1024 * 1024, '1,5 GB'],
        [3 * 1024 * 1024 * 1024, '3,0 GB'],
        [12 * 1024 * 1024 * 1024, '12 GB'],
    ];

    for (const [bytes, expected] of cases) {
        it(`${bytes} byte → ${expected}`, () => {
            expect(formatFileSize(bytes)).toBe(expected);
        });
    }

    it('nilai tidak valid tidak menghasilkan NaN', () => {
        expect(formatFileSize(Number.NaN)).toBe('0 B');
        expect(formatFileSize(-5)).toBe('0 B');
    });
});

describe('fileExtension', () => {
    it('mengembalikan ekstensi huruf kecil tanpa titik', () => {
        expect(fileExtension('foto.JPG')).toBe('jpg');
        expect(fileExtension('arsip.tar.gz')).toBe('gz');
        expect(fileExtension('tanpa-ekstensi')).toBe('');
        expect(fileExtension('')).toBe('');
    });
});

describe('uploadPercent', () => {
    const cases: Array<[number, number, number]> = [
        [0, 100, 0],
        [50, 100, 50],
        [100, 100, 100],
        [90, 0, 0],
        [150, 100, 100],
        [716_800, 2_411_724, 30],
    ];

    for (const [loaded, total, expected] of cases) {
        it(`${loaded}/${total} → ${expected}%`, () => {
            expect(uploadPercent(loaded, total)).toBe(expected);
        });
    }
});

describe('pratinjau gambar', () => {
    it('hanya gambar raster yang dipratinjau', () => {
        expect(isPreviewableImage('image/png', 'a.png')).toBe(true);
        expect(isPreviewableImage('', 'a.webp')).toBe(true);
        expect(isPreviewableImage('application/pdf', 'a.pdf')).toBe(false);
        expect(isPreviewableImage('image/svg+xml', 'a.svg')).toBe(false);
    });
});
