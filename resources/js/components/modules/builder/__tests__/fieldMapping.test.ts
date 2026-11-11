import { describe, expect, it } from 'vitest';
import { toBackendFields } from '@/components/modules/builder/fieldMapping';
import { prependFormBannerToBackendPayload, defaultFormBannerState } from '@/components/modules/builder/formBanner';
import type { IBuilderField } from '@/types/form-builder';

/** Field builder minimal; hanya properti yang diuji yang diisi. */
function builderField(partial: Partial<IBuilderField> & Pick<IBuilderField, 'type'>): IBuilderField {
    return {
        id: 'f-1',
        label: 'Field',
        description: '',
        name: 'field_1',
        placeholder: '',
        required: false,
        options: [],
        metadata: {},
        ...partial,
    };
}

/** Cari satu field backend berdasarkan nama; gagal keras bila tidak ada. */
function backendMetaOf(fields: ReturnType<typeof toBackendFields>, name: string): Record<string, unknown> {
    const found = fields.find((field) => field.name === name);

    if (found === undefined) throw new Error(`field backend ${name} tidak ditemukan`);

    return found.metadata;
}

describe('toBackendFields — URL gambar yang dipersist', () => {
    it('banner: URL preview lokal tidak pernah masuk payload backend', () => {
        const canvas = [
            builderField({
                type: 'banner',
                name: 'form_banner',
                metadata: { bannerUrl: 'blob:https://dform.test/1', formBanner: true },
            }),
        ];

        expect(backendMetaOf(toBackendFields(canvas), 'form_banner').bannerUrl).toBe('');
    });

    it('banner: URL server yang valid tetap dipertahankan', () => {
        const canvas = [
            builderField({
                type: 'banner',
                name: 'form_banner',
                metadata: { bannerUrl: 'https://dform.test/storage/banner.png', formBanner: true },
            }),
        ];

        expect(backendMetaOf(toBackendFields(canvas), 'form_banner').bannerUrl).toBe(
            'https://dform.test/storage/banner.png'
        );
    });

    it('blok image: `data:`/`blob:` diganti string kosong', () => {
        const canvas = [
            builderField({ type: 'image', metadata: { imageUrl: 'data:image/png;base64,AAAA', caption: 'A' } }),
            builderField({ id: 'f-2', name: 'field_2', type: 'image', metadata: { imageUrl: 'blob:x' } }),
        ];

        const backend = toBackendFields(canvas);

        expect(backendMetaOf(backend, 'field_1').imageUrl).toBe('');
        expect(backendMetaOf(backend, 'field_2').imageUrl).toBe('');
    });

    it('opsi image: imageUrl lokal dibuang, URL server dipertahankan', () => {
        const field = builderField({
            type: 'radio',
            options: [
                { id: 'o1', type: 'image', label: 'A', imageUrl: 'blob:https://dform.test/2' },
                { id: 'o2', type: 'image', label: 'B', imageUrl: 'https://dform.test/storage/b.png' },
            ],
        });

        const meta = backendMetaOf(toBackendFields([field]), 'field_1');
        const choices = meta.optionChoices;

        expect(Array.isArray(choices)).toBe(true);

        const images = (choices as { imageUrl: string }[]).map((choice) => choice.imageUrl);

        expect(images).toEqual(['', 'https://dform.test/storage/b.png']);
    });

    it('metadata yang hanya diteruskan (preserved) juga dibersihkan secara rekursif', () => {
        const field = builderField({
            type: 'short_text',
            metadata: {
                placeholder: '',
                bannerUrl: 'blob:https://dform.test/3',
                imageUrl: 'data:image/png;base64,BBBB',
                nested: { imageUrl: 'blob:https://dform.test/4', keep: 'teks' },
                serverUrl: 'https://dform.test/storage/ok.png',
            },
        });

        const meta = backendMetaOf(toBackendFields([field]), 'field_1');

        expect(meta.bannerUrl).toBe('');
        expect(meta.imageUrl).toBe('');
        expect(meta.nested).toEqual({ imageUrl: '', keep: 'teks' });
        expect(meta.serverUrl).toBe('https://dform.test/storage/ok.png');
    });

    it('prepend banner + mapping: payload final bebas base64/blob', () => {
        const canvas = [
            builderField({
                type: 'image',
                metadata: { imageUrl: 'blob:https://dform.test/5' },
            }),
        ];
        const banner = { ...defaultFormBannerState(), bannerUrl: 'blob:https://dform.test/6', caption: 'Cap' };

        const serialized = JSON.stringify(toBackendFields(prependFormBannerToBackendPayload(canvas, banner)));

        expect(serialized).not.toContain('blob:');
        expect(serialized).not.toContain('data:');
    });
});
