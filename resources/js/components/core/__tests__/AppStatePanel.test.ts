import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { describe, expect, it } from 'vitest';
import AppStatePanel from '@/components/core/AppStatePanel.vue';

function renderPanel(props: Record<string, unknown>, slot?: () => unknown): Promise<string> {
    return renderToString(
        createSSRApp({
            render: () => h(AppStatePanel, props, slot ? { default: slot } : undefined),
        })
    );
}

describe('AppStatePanel', () => {
    it('varian page memakai h1, eager, dan ilustrasi error', async () => {
        const html = await renderPanel({
            illustration: 'error',
            title: 'Terjadi kesalahan',
            description: 'Coba lagi.',
            variant: 'page',
            headingTag: 'h1',
        });

        expect(html).toContain('<h1');
        expect(html).toContain('Terjadi kesalahan');
        expect(html).toContain('src="/illustrations/state-error.svg"');
        expect(html).toContain('fetchpriority="high"');
        expect(html).toContain('width="500"');
        expect(html).toContain('height="500"');
        expect(html).toContain('alt=""');
        expect(html).toContain('aria-hidden="true"');
    });

    it('varian inline memakai p, lazy, dan ilustrasi empty', async () => {
        const html = await renderPanel({
            illustration: 'empty',
            title: 'Belum ada data',
            variant: 'inline',
        });

        expect(html).toContain('<p');
        expect(html).not.toContain('<h1');
        expect(html).toContain('src="/illustrations/state-empty.svg"');
        expect(html).toContain('loading="lazy"');
        expect(html).toContain('width="500"');
        expect(html).toContain('height="500"');
    });

    it('slot aksi dirender setelah deskripsi', async () => {
        const html = await renderPanel({ illustration: 'empty', title: 'Kosong' }, () => 'AKSI');

        expect(html).toContain('AKSI');
    });

    it('surface default memakai latar kartu', async () => {
        const html = await renderPanel({ illustration: 'empty', title: 'Kosong' });

        expect(html).toContain('app-surface');
    });

    it('surface false menghilangkan latar kartu', async () => {
        const html = await renderPanel({ illustration: 'empty', title: 'Kosong', surface: false });

        expect(html).not.toContain('app-surface');
    });
});
