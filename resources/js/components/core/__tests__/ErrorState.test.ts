import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { describe, expect, it } from 'vitest';
import ErrorState from '@/components/core/ErrorState.vue';

function render(status: number): Promise<string> {
    return renderToString(createSSRApp({ render: () => h(ErrorState, { status }) }));
}

describe('ErrorState', () => {
    it('404 memakai judul dan ilustrasi error', async () => {
        const html = await render(404);

        expect(html).toContain('<h1');
        expect(html).toContain('Halaman tidak ditemukan');
        expect(html).toContain('/illustrations/state-error.svg');
        expect(html).toContain('Kembali');
    });

    it('status tak dikenal memakai copy default', async () => {
        const html = await render(418);

        expect(html).toContain('Terjadi kesalahan');
    });
});
