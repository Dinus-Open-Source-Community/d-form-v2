import { describe, expect, it } from 'vitest';
import {
    hydrateFormBuilderState,
    normalizeClosedAt,
    type IFormBuilderHydrationInput,
} from '@/components/modules/builder/formBuilderHydration';
import { buildFormBannerBuilderField } from '@/components/modules/builder/formBanner';
import type { IBackendField } from '@/types/form-builder';

/** Field backend sesuai kontrak `formFieldToInertia()`. */
function backendField(overrides: Partial<IBackendField> = {}): IBackendField {
    return {
        id: 'f-1',
        type: 'input',
        label: 'Nama',
        description: null,
        name: 'full_name',
        order: 1,
        metadata: { type: 'text', rules: {} },
        is_append: false,
        ...overrides,
    };
}

function input(overrides: Partial<IFormBuilderHydrationInput> = {}): IFormBuilderHydrationInput {
    return {
        form: {
            title: 'Form Pendaftaran',
            description: 'Deskripsi',
            success_content: 'Terima kasih',
            closed_at: '2026-11-21T10:00',
            visible_for: ['public'],
            banner_url: null,
            banner_caption: null,
            metadata: {},
        },
        fields: [backendField()],
        ...overrides,
    };
}

describe('normalizeClosedAt', () => {
    const cases: Array<[string, string | null | undefined, string]> = [
        ['datetime-local apa adanya', '2026-11-21T10:00', '2026-11-21T10:00'],
        ['dengan detik', '2026-11-21T10:00:37', '2026-11-21T10:00'],
        ['dengan zona waktu', '2026-11-21T10:00:00+07:00', '2026-11-21T10:00'],
        ['tanpa jam', '2026-11-21', ''],
        ['null', null, ''],
        ['string kosong', '   ', ''],
        ['bukan tanggal', 'besok pagi', ''],
    ];

    for (const [label, value, expected] of cases) {
        it(`closed_at ${label} → "${expected}"`, () => {
            expect(normalizeClosedAt(value)).toBe(expected);
        });
    }
});

describe('hydrateFormBuilderState', () => {
    it('memetakan header lengkap apa adanya', () => {
        const result = hydrateFormBuilderState(input());

        expect(result.title).toBe('Form Pendaftaran');
        expect(result.description).toBe('Deskripsi');
        expect(result.successContent).toBe('Terima kasih');
        expect(result.closedAt).toBe('2026-11-21T10:00');
        expect(result.visibleFor).toEqual(['public']);
        expect(result.banner).toEqual({ id: null, bannerUrl: '', bannerFileName: '', caption: '' });
    });

    it('mengurutkan field berdasarkan `order` tanpa memutasi input', () => {
        const source = [
            backendField({ id: 'f-3', name: 'c', order: 3 }),
            backendField({ id: 'f-1', name: 'a', order: 1 }),
            backendField({ id: 'f-2', name: 'b', order: 2 }),
        ];

        const result = hydrateFormBuilderState(input({ fields: source }));

        expect(result.fields.map((field) => field.name)).toEqual(['a', 'b', 'c']);
        expect(source.map((field) => field.id)).toEqual(['f-3', 'f-1', 'f-2']);
    });

    it('mempertahankan id field dan metadata builder dari server', () => {
        const result = hydrateFormBuilderState(
            input({ fields: [backendField({ id: 'f-9', name: 'email', metadata: { type: 'email', rules: {} } })] })
        );

        expect(result.fields[0]?.id).toBe('f-9');
        expect(result.fields[0]?.name).toBe('email');
    });

    it('memisahkan field banner sintetis menjadi state banner', () => {
        const bannerField = buildFormBannerBuilderField({
            id: 'b-1',
            bannerUrl: '/storage/form-banners/x.png',
            bannerFileName: 'x.png',
            caption: 'Judul banner',
        });

        if (bannerField === null) throw new Error('banner field tidak terbentuk');

        const result = hydrateFormBuilderState(
            input({
                form: {
                    ...input().form,
                    banner_url: '/storage/form-banners/y.png',
                    banner_caption: 'Caption header',
                },
                fields: [
                    backendField({ id: 'f-1', name: 'a', order: 1 }),
                    {
                        // Backend menyimpan banner sebagai `fileUpload` + metadata.builderType.
                        id: bannerField.id,
                        type: 'fileUpload',
                        label: bannerField.label,
                        description: null,
                        name: bannerField.name,
                        order: 0,
                        metadata: { ...bannerField.metadata, builderType: 'banner' },
                        is_append: false,
                    },
                ],
            })
        );

        // Banner dari header menang atas banner field sintetis.
        expect(result.banner.bannerUrl).toBe('/storage/form-banners/y.png');
        expect(result.banner.caption).toBe('Caption header');
        // Banner tidak ikut sebagai field kanvas.
        expect(result.fields.map((field) => field.name)).toEqual(['a']);
    });

    it('draf kosong tetap menghasilkan state default tanpa error', () => {
        const result = hydrateFormBuilderState({
            form: {
                title: '',
                description: '',
                success_content: null,
                closed_at: null,
                visible_for: ['public'],
                banner_url: null,
                banner_caption: null,
                metadata: {},
            },
            fields: [],
        });

        expect(result.title).toBe('');
        expect(result.successContent).toBe('');
        expect(result.closedAt).toBe('');
        expect(result.visibleFor).toEqual(['public']);
        expect(result.fields).toEqual([]);
    });

    const legacyMetadata: Array<[string, unknown]> = [
        ['metadata null', null],
        ['metadata array kosong', []],
        ['metadata string', 'bukan objek'],
        ['metadata objek registrasi', { purpose: 'registration', requires_form_id: 'form-9' }],
    ];

    for (const [label, metadata] of legacyMetadata) {
        it(`metadata legacy (${label}) tetap menghasilkan metadata builder valid`, () => {
            const result = hydrateFormBuilderState(input({ form: { ...input().form, metadata: metadata as unknown } }));

            expect(result.metadata.purpose === 'registration' || result.metadata.purpose === 'other').toBe(true);
            expect(Array.isArray(result.metadata.sections)).toBe(true);
        });
    }

    it('metadata registrasi valid diteruskan ke model', () => {
        const result = hydrateFormBuilderState(
            input({
                form: {
                    ...input().form,
                    metadata: { purpose: 'registration', registration_mode: 'team', team_size: 3 },
                },
            })
        );

        expect(result.metadata.registration_mode).toBe('team');
        expect(result.metadata.team_size).toBe(3);
    });
});
