import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { describe, expect, it } from 'vitest';
import EmptyState from '@/components/core/EmptyState.vue';

function render(props: Record<string, unknown>, slot?: () => unknown): Promise<string> {
    return renderToString(createSSRApp({ render: () => h(EmptyState, props, slot ? { default: slot } : undefined) }));
}

describe('EmptyState', () => {
    it('kind filter memakai copy filter dan ilustrasi empty', async () => {
        const html = await render({ kind: 'filter' });

        expect(html).toContain('Tidak ada data yang cocok');
        expect(html).toContain('/illustrations/state-empty.svg');
        expect(html).not.toContain('<h1');
    });

    it('query dirender sebagai teks, bukan HTML', async () => {
        const html = await render({ kind: 'search', query: '<script>alert(1)</script>' });

        expect(html).toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
        expect(html).not.toContain('<script>alert(1)</script>');
    });

    it('title dan description dari pemakai lama menimpa copy default', async () => {
        const html = await render({ kind: 'empty', title: 'Tidak ada acara', description: 'Sesuaikan filter.' });

        expect(html).toContain('Tidak ada acara');
        expect(html).toContain('Sesuaikan filter.');
    });

    it('title kosong jatuh ke copy default, bukan heading kosong', async () => {
        const html = await render({ kind: 'empty', title: '   ' });

        expect(html).toContain('Belum ada data');
    });
});
