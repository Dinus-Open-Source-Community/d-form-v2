import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { describe, expect, it } from 'vitest';
import FileTypeTile from '@/components/core/FileTypeTile.vue';

/**
 * Rendering diuji lewat SSR (tanpa jsdom/test-utils): cukup untuk memastikan
 * `src` ikon, atribut aksesibilitas, dan ukuran tetap sesuai kontrak baris.
 */
async function renderTile(mime: string, fileName: string): Promise<string> {
    return renderToString(createSSRApp({ render: () => h(FileTypeTile, { mime, fileName }) }));
}

describe('FileTypeTile', () => {
    const cases: Array<[string, string, string]> = [
        ['application/pdf', 'laporan.pdf', 'pdf'],
        ['', 'rekap.xlsx', 'excel'],
        ['application/vnd.ms-excel', 'tanpa-ekstensi', 'excel'],
        ['', 'surat.docx', 'word'],
        ['application/msword', 'tanpa-ekstensi', 'word'],
        ['', 'slide.pptx', 'powerpoint'],
        ['text/csv', 'data.csv', 'csv'],
        ['application/zip', 'arsip.zip', 'zip'],
        ['image/jpeg', 'foto.jpg', 'image'],
        ['text/plain', 'catatan.txt', 'text'],
        ['application/octet-stream', 'tanpa-ekstensi', 'generic'],
    ];

    for (const [mime, fileName, key] of cases) {
        it(`${fileName} → ikon ${key}`, async () => {
            const html = await renderTile(mime, fileName);

            expect(html).toContain(`file-types/${key}.svg`);
            expect(html).toContain('aria-hidden="true"');
            expect(html).toContain(' alt width="40"');
            expect(html).not.toContain('alt="');
            expect(html).toContain('h-[40px] w-[40px]');
            expect(html).toContain('draggable="false"');
        });
    }

    it('tanpa props tetap merender ikon generic', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(FileTypeTile) }));

        expect(html).toContain('file-types/generic.svg');
    });
});
