import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { describe, expect, it } from 'vitest';
import FormPriceBlock from '@/components/core/FormPriceBlock.vue';
import type { IFormPriceQuote } from '@/types/form';

function renderBlock(quote: IFormPriceQuote | null): Promise<string> {
    return renderToString(
        createSSRApp({
            render: () => h(FormPriceBlock, { label: 'Payment', base: 95_000, quote }),
        })
    );
}

describe('FormPriceBlock', () => {
    it('shows only the base price without a quote', async () => {
        const html = await renderBlock(null);

        expect(html).toContain('Payment');
        expect(html).toContain('Rp 95.000');
        expect(html).not.toContain('Hemat');
    });

    it('can hide the label when the surrounding card already shows it', async () => {
        const html = await renderToString(
            createSSRApp({
                render: () => h(FormPriceBlock, { label: 'Payment', base: 95_000, quote: null, showLabel: false }),
            })
        );

        expect(html).not.toContain('Payment');
        expect(html).toContain('Rp 95.000');
    });

    it('breaks the price into rows when a discount applies', async () => {
        const html = await renderBlock({
            base: 95_000,
            discount_type: 'percentage',
            discount_value: 20,
            discount: 19_000,
            final: 76_000,
        });

        expect(html).toContain('Diskon 20%');
        expect(html).toContain('Rp 95.000');
        expect(html).toContain('-Rp 19.000');
        expect(html).toContain('Total');
        expect(html).toContain('Rp 76.000');
        expect(html).not.toContain('line-through');
    });

    it('keeps the base price when the quote carries no discount', async () => {
        const html = await renderBlock({
            base: 95_000,
            discount_type: null,
            discount_value: null,
            discount: 0,
            final: 95_000,
        });

        expect(html).toContain('Rp 95.000');
        expect(html).not.toContain('line-through');
        expect(html).not.toContain('Hemat');
    });
});
