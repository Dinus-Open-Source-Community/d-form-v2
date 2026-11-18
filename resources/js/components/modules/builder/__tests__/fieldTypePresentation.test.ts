import { describe, expect, it } from 'vitest';
import {
    FIELD_TONE_CHIP_CLASS,
    FIELD_TYPE_META,
    resolveFieldTypeMeta,
    type TFieldTone,
} from '@/components/modules/builder/fieldTypePresentation';

describe('fieldTypePresentation', () => {
    it('covers every builder field type exactly once', () => {
        expect(Object.keys(FIELD_TYPE_META)).toHaveLength(17);
    });

    it('resolves a known type with its canonical label and tone', () => {
        expect(resolveFieldTypeMeta('email')).toMatchObject({ label: 'Email', tone: 'primary' });
        expect(resolveFieldTypeMeta('rating')).toMatchObject({ label: 'Rating', tone: 'warning' });
        expect(resolveFieldTypeMeta('image_upload')).toMatchObject({ label: 'Unggah gambar', tone: 'success' });
    });

    it('falls back to the short text meta for unknown types', () => {
        expect(resolveFieldTypeMeta('legacy_unknown_type')).toBe(resolveFieldTypeMeta('short_text'));
    });

    it('maps every tone to a chip class pair', () => {
        const tones = new Set<TFieldTone>(Object.values(FIELD_TYPE_META).map((meta) => meta.tone));
        for (const tone of tones) {
            expect(FIELD_TONE_CHIP_CLASS[tone]).toMatch(/^bg-/);
            expect(FIELD_TONE_CHIP_CLASS[tone]).toMatch(/ text-/);
        }
        expect(tones).not.toContain<TFieldTone>('info');
    });
});
