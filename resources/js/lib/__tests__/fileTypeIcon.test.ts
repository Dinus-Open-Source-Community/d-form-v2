import { describe, expect, it } from 'vitest';
import { fileTypeIconKey, type TFileTypeIconKey } from '@/lib/fileTypeIcon';

describe('fileTypeIconKey — ekstensi', () => {
    const cases: Array<[string, TFileTypeIconKey]> = [
        ['laporan.pdf', 'pdf'],
        ['rekap.xls', 'excel'],
        ['rekap.xlsx', 'excel'],
        ['rekap.xlsm', 'excel'],
        ['rekap.ods', 'excel'],
        ['surat.doc', 'word'],
        ['surat.docx', 'word'],
        ['surat.odt', 'word'],
        ['slide.ppt', 'powerpoint'],
        ['slide.pptx', 'powerpoint'],
        ['slide.odp', 'powerpoint'],
        ['data.csv', 'csv'],
        ['data.tsv', 'csv'],
        ['arsip.zip', 'zip'],
        ['arsip.rar', 'zip'],
        ['arsip.7z', 'zip'],
        ['arsip.gz', 'zip'],
        ['arsip.tar', 'zip'],
        ['foto.jpg', 'image'],
        ['foto.jpeg', 'image'],
        ['foto.png', 'image'],
        ['foto.gif', 'image'],
        ['foto.webp', 'image'],
        ['foto.avif', 'image'],
        ['foto.bmp', 'image'],
        ['logo.svg', 'image'],
        ['catatan.txt', 'text'],
        ['catatan.md', 'text'],
        ['server.log', 'text'],
        ['catatan.rtf', 'text'],
        ['berkas.tanpa-ekstensi-terkenal', 'generic'],
    ];

    for (const [name, expected] of cases) {
        it(`${name} → ${expected}`, () => {
            expect(fileTypeIconKey('', name)).toBe(expected);
        });
    }

    it('ekstensi tidak peka huruf besar/kecil', () => {
        expect(fileTypeIconKey('', 'FOTO.JPG')).toBe('image');
        expect(fileTypeIconKey('', 'Laporan.PDF')).toBe('pdf');
    });
});

describe('fileTypeIconKey — MIME fallback', () => {
    const cases: Array<[string, string, TFileTypeIconKey]> = [
        ['application/pdf', 'tanpa-ekstensi', 'pdf'],
        ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'tanpa-ekstensi', 'excel'],
        ['application/vnd.ms-excel', 'tanpa-ekstensi', 'excel'],
        ['application/vnd.oasis.opendocument.spreadsheet', 'tanpa-ekstensi', 'excel'],
        ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'tanpa-ekstensi', 'word'],
        ['application/msword', 'tanpa-ekstensi', 'word'],
        ['application/vnd.oasis.opendocument.text', 'tanpa-ekstensi', 'word'],
        ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'tanpa-ekstensi', 'powerpoint'],
        ['application/vnd.ms-powerpoint', 'tanpa-ekstensi', 'powerpoint'],
        ['text/csv', 'tanpa-ekstensi', 'csv'],
        ['text/tab-separated-values', 'tanpa-ekstensi', 'csv'],
        ['application/zip', 'tanpa-ekstensi', 'zip'],
        ['application/gzip', 'tanpa-ekstensi', 'zip'],
        ['application/x-tar', 'tanpa-ekstensi', 'zip'],
        ['image/webp', 'tanpa-ekstensi', 'image'],
        ['text/plain', 'tanpa-ekstensi', 'text'],
        ['application/octet-stream', 'tanpa-ekstensi', 'generic'],
        ['', '', 'generic'],
    ];

    for (const [mime, name, expected] of cases) {
        it(`${mime || '(kosong)'} → ${expected}`, () => {
            expect(fileTypeIconKey(mime, name)).toBe(expected);
        });
    }
});

describe('fileTypeIconKey — urutan sinyal', () => {
    it('ekstensi menang atas MIME yang bertentangan', () => {
        expect(fileTypeIconKey('application/pdf', 'rekap.xlsx')).toBe('excel');
        expect(fileTypeIconKey('image/png', 'laporan.pdf')).toBe('pdf');
    });

    it('nama tanpa ekstensi jatuh ke MIME, bukan generic', () => {
        expect(fileTypeIconKey('image/jpeg', 'foto')).toBe('image');
    });
});
