import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { describe, expect, it, vi } from 'vitest';
import BulkFormAnswerReviewDialog from '@/components/modules/dashboard/BulkFormAnswerReviewDialog.vue';

vi.mock('@/components/ui/dialog', async () => {
    const { defineComponent, h } = await import('vue');
    const wrapper = (): ReturnType<typeof defineComponent> =>
        defineComponent({
            inheritAttrs: false,
            setup(_props, { attrs, slots }) {
                return () => h('div', attrs, slots.default?.());
            },
        });

    return {
        Dialog: wrapper(),
        DialogContent: wrapper(),
        DialogDescription: wrapper(),
        DialogFooter: wrapper(),
        DialogHeader: wrapper(),
        DialogTitle: wrapper(),
    };
});

describe('BulkFormAnswerReviewDialog', () => {
    it('renders the exact accept confirmation and selected-only explanation', async () => {
        const html = await renderToString(
            createSSRApp(BulkFormAnswerReviewDialog, {
                open: true,
                selectedCount: 3,
                decision: 'accepted',
                loading: false,
                error: null,
            })
        );

        expect(html).toContain('Terima 3 jawaban terpilih?');
        expect(html).toContain('Hanya jawaban yang dipilih yang akan diproses.');
        expect(html).toContain('Batal');
        expect(html).toContain('Terima jawaban');
    });

    it('renders the exact reject confirmation and an announced loading error state', async () => {
        const html = await renderToString(
            createSSRApp(BulkFormAnswerReviewDialog, {
                open: true,
                selectedCount: 2,
                decision: 'rejected',
                loading: true,
                error: 'Koneksi terputus.',
            })
        );

        expect(html).toContain('Tolak 2 jawaban terpilih?');
        expect(html).toContain('Tolak jawaban');
        expect(html).toContain('Koneksi terputus.');
        expect(html).toContain('role="alert"');
        expect(html).toContain('aria-busy="true"');
        expect(html).toContain('Sedang memproses jawaban terpilih.');
    });
});
