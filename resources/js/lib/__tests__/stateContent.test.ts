import { describe, expect, it } from 'vitest';
import { resolveEmptyStateContent, resolveErrorStateContent } from '@/lib/stateContent';

describe('resolveErrorStateContent', () => {
    it.each([
        [403, 'Akses ditolak'],
        [404, 'Halaman tidak ditemukan'],
        [409, 'Data sudah berubah'],
        [429, 'Terlalu banyak permintaan'],
        [500, 'Terjadi kesalahan'],
        [502, 'Layanan tidak tersedia'],
        [503, 'Layanan tidak tersedia'],
    ])('status %i memakai judul %s', (status: number, title: string) => {
        expect(resolveErrorStateContent(status).title).toBe(title);
    });

    it('status di luar daftar memakai copy default', () => {
        const content = resolveErrorStateContent(418);

        expect(content.title).toBe('Terjadi kesalahan');
        expect(content.description.length).toBeGreaterThan(20);
    });
});

describe('resolveEmptyStateContent', () => {
    it('kind empty menyebut data belum ada', () => {
        expect(resolveEmptyStateContent({ kind: 'empty' }).title).toBe('Belum ada data');
    });

    it('kind search menyertakan query yang sudah di-trim', () => {
        expect(resolveEmptyStateContent({ kind: 'search', query: '  qr code  ' }).title).toBe(
            'Tidak ada hasil untuk "qr code"'
        );
    });

    it('kind search tanpa query jatuh ke judul generik', () => {
        expect(resolveEmptyStateContent({ kind: 'search', query: '   ' }).title).toBe('Tidak ada hasil');
    });

    it('query berisi karakter HTML tetap berupa teks biasa', () => {
        expect(resolveEmptyStateContent({ kind: 'search', query: '<b>x</b>' }).title).toBe(
            'Tidak ada hasil untuk "<b>x</b>"'
        );
    });

    it.each([
        ['filter', 'Tidak ada data yang cocok'],
        ['not-found', 'Data tidak ditemukan'],
    ] as const)('kind %s memakai judul %s', (kind, title) => {
        expect(resolveEmptyStateContent({ kind }).title).toBe(title);
    });
});
