import { describe, expect, it } from 'vitest';
import {
    DEFAULT_ALLOWED_MIME_TYPES,
    DEFAULT_UPLOAD_LIMITS,
    fromBackendField,
    isFieldDeletionBlocked,
    normalizeUploadLimit,
    setUploadMultipleMetadata,
    toggleAllowedMimeType,
    toBackendFields,
} from '@/components/modules/builder/fieldMapping';
import { prependFormBannerToBackendPayload, defaultFormBannerState } from '@/components/modules/builder/formBanner';
import type { IBackendField, IBuilderField } from '@/types/form-builder';

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

describe('DU system field mapping', () => {
    it('preserves locked metadata and does not expose editable static options', () => {
        const field = builderField({
            type: 'dropdown',
            name: 'du_course_uid',
            metadata: { locked: true, system: true, options_source: 'du_catalog' },
            options: [],
        });
        const backend = toBackendFields([field])[0];
        expect(backend.name).toBe('du_course_uid');
        expect(backend.metadata.locked).toBe(true);
        expect(backend.metadata.options).toBeUndefined();
    });

    it('does not persist dropdown options for the DU system field', () => {
        const field = builderField({
            type: 'dropdown',
            name: 'du_course_uid',
            metadata: { locked: true, system: true, options_source: 'du_catalog' },
            options: [{ id: 'course-a', type: 'text', label: 'Kelas A', imageUrl: '' }],
        });

        const backend = toBackendFields([field])[0];

        expect(backend.metadata.optionChoices).toBeUndefined();
        expect((backend.metadata.rules as Record<string, unknown>).in).toBeUndefined();
        expect(backend.metadata.locked).toBe(true);
    });

    it('strips a stale optionChoices snapshot copied from existing metadata', () => {
        const field = builderField({
            type: 'dropdown',
            name: 'du_course_uid',
            metadata: {
                locked: true,
                system: true,
                options_source: 'du_catalog',
                optionChoices: [{ id: 'stale', type: 'text', label: 'Kelas Lama', imageUrl: '' }],
                options: 'legacy-options',
            },
            options: [],
        });

        const backend = toBackendFields([field])[0];

        expect(backend.metadata).not.toHaveProperty('optionChoices');
        expect(backend.metadata).not.toHaveProperty('options');
        expect((backend.metadata.rules as Record<string, unknown>).in).toBeUndefined();
    });
});

describe('selected_course_uids pada field sistem DU', () => {
    it('mempertahankan pilihan kelas eksplisit saat serialize ke backend', () => {
        const field = builderField({
            type: 'dropdown',
            name: 'du_course_uid',
            metadata: { locked: true, system: true, options_source: 'du_catalog', selected_course_uids: [] },
        });

        const backend = toBackendFields([field])[0];

        expect(backend.metadata.selected_course_uids).toEqual([]);
    });

    it('mempertahankan selected_course_uids saat hydration dari backend', () => {
        const field = fromBackendField({
            id: 'bf-1',
            type: 'select',
            label: 'Kelas',
            description: null,
            name: 'du_course_uid',
            order: 1,
            metadata: {
                builderType: 'dropdown',
                locked: true,
                system: true,
                options_source: 'du_catalog',
                selected_course_uids: ['course-a', 'course-b'],
                optionChoices: [
                    { type: 'text', label: 'Alpha', value: 'course-a' },
                    { type: 'text', label: 'Beta', value: 'course-b' },
                ],
            },
        });

        expect(field.metadata.selected_course_uids).toEqual(['course-a', 'course-b']);
    });

    it('memakai value katalog sebagai id opsi agar UID kelas tidak hilang', () => {
        const field = fromBackendField({
            id: 'bf-1',
            type: 'select',
            label: 'Kelas',
            description: null,
            name: 'du_course_uid',
            order: 1,
            metadata: {
                builderType: 'dropdown',
                locked: true,
                options_source: 'du_catalog',
                selected_course_uids: ['course-a'],
                optionChoices: [{ type: 'text', label: 'Alpha', value: 'course-a' }],
            },
        });

        expect(field.options).toEqual([{ id: 'course-a', type: 'text', label: 'Alpha', imageUrl: '' }]);
        expect(toBackendFields([field])[0].metadata.selected_course_uids).toEqual(['course-a']);
    });

    it('tidak memakai value katalog sebagai id untuk field non-sistem', () => {
        const field = fromBackendField({
            id: 'bf-2',
            type: 'radio',
            label: 'Class',
            description: null,
            name: 'class',
            order: 1,
            metadata: {
                builderType: 'radio',
                optionChoices: [{ type: 'text', label: 'AI Engineer', value: 'course-ai' }],
            },
        });

        const [option] = field.options;

        if (option === undefined) throw new Error('opsi tidak terhidrasi');

        expect(option.id).not.toBe('course-ai');
        expect(option.id).toMatch(/^[0-9a-f-]{36}$/);
    });
});

describe('DU redeem metadata field kustom', () => {
    it('memblokir hapus untuk field sistem dan field sumber redeem DU saja', () => {
        expect(isFieldDeletionBlocked(builderField({ type: 'dropdown', metadata: { locked: true } }))).toBe(true);
        expect(isFieldDeletionBlocked(builderField({ type: 'dropdown', metadata: { du_redeem_enabled: true } }))).toBe(
            true
        );
        expect(isFieldDeletionBlocked(builderField({ type: 'dropdown', metadata: { du_redeem_enabled: false } }))).toBe(
            false
        );
        expect(isFieldDeletionBlocked(builderField({ type: 'dropdown' }))).toBe(false);
    });

    it('mempertahankan du_redeem_enabled dan du_option_map pada field normal', () => {
        const optionMap = {
            'AI Engineer': { course_uid: 'course-ai', course_title: 'AI Engineering' },
        };
        const field = builderField({
            type: 'dropdown',
            name: 'class',
            metadata: { du_redeem_enabled: true, du_option_map: optionMap },
            options: [{ id: 'o1', type: 'text', label: 'AI Engineer', imageUrl: '' }],
        });

        const backend = toBackendFields([field])[0];

        expect(backend?.metadata.du_redeem_enabled).toBe(true);
        expect(backend?.metadata.du_option_map).toEqual(optionMap);
    });

    it('field sistem DU tetap membuang optionChoices dan rules.in', () => {
        const field = builderField({
            type: 'dropdown',
            name: 'du_course_uid',
            metadata: { locked: true, system: true, options_source: 'du_catalog' },
            options: [{ id: 'course-a', type: 'text', label: 'Kelas A', imageUrl: '' }],
        });

        const backend = toBackendFields([field])[0];

        expect(backend?.metadata.optionChoices).toBeUndefined();
        expect((backend?.metadata.rules as Record<string, unknown>).in).toBeUndefined();
    });
});

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

describe('fromBackendField — metadata Inertia', () => {
    function backendField(metadata: Record<string, unknown>): IBackendField {
        return {
            id: 'bf-1',
            type: 'input',
            label: 'Field',
            description: null,
            name: 'field_1',
            order: 1,
            metadata,
        };
    }

    it('maps a valid metadata/rules payload', () => {
        const field = fromBackendField(
            backendField({
                builderType: 'short_text',
                placeholder: 'Nama',
                sectionId: 'section-2',
                maxLength: 80,
                rules: { required: true, min: 0, max: 80 },
            })
        );

        expect(field.type).toBe('short_text');
        expect(field.placeholder).toBe('Nama');
        expect(field.required).toBe(true);
        expect(field.metadata.sectionId).toBe('section-2');
        expect(field.metadata.maxLength).toBe(80);
    });

    it('falls back to defaults for a malformed metadata payload instead of throwing', () => {
        const field = fromBackendField(
            backendField({
                builderType: 42,
                placeholder: { nested: true },
                sectionId: '   ',
                maxStars: 'lima',
                optionChoices: 'not-an-array',
                rules: 'required',
            })
        );

        expect(field.type).toBe('short_text');
        expect(field.placeholder).toBe('');
        expect(field.required).toBe(false);
        expect(field.metadata.sectionId).toBe('section-1');
        expect(field.metadata.maxStars).toBe(5);
        expect(field.options).toEqual([]);
    });
});

describe('toBackendFields — heading content', () => {
    it('falls back to the section heading when content is cleared or whitespace', () => {
        const canvas = [
            builderField({ type: 'heading', metadata: { content: '' } }),
            builderField({ id: 'f-2', name: 'field_2', type: 'heading', metadata: { content: '   ' } }),
            builderField({ id: 'f-3', name: 'field_3', type: 'heading', metadata: { content: 'Judul' } }),
        ];
        const backend = toBackendFields(canvas);

        expect(backendMetaOf(backend, 'field_1').content).toBe('Section Heading');
        expect(backendMetaOf(backend, 'field_2').content).toBe('Section Heading');
        expect(backendMetaOf(backend, 'field_3').content).toBe('Judul');
    });
});

describe('upload field metadata', () => {
    it('hydrates image and file allowlists with their format defaults', () => {
        const image = fromBackendField({ ...backendUploadField(), metadata: { builderType: 'image_upload' } });
        const file = fromBackendField({ ...backendUploadField(), metadata: { builderType: 'file_upload' } });

        expect(image.metadata.allowed_mime_types).toEqual(DEFAULT_ALLOWED_MIME_TYPES.image_upload);
        expect(file.metadata.allowed_mime_types).toEqual(DEFAULT_ALLOWED_MIME_TYPES.file_upload);
        expect(DEFAULT_ALLOWED_MIME_TYPES.file_upload).not.toEqual(expect.arrayContaining(['application/zip']));
        expect(DEFAULT_ALLOWED_MIME_TYPES.file_upload).not.toEqual(
            expect.arrayContaining(['application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint'])
        );
    });

    it('preserves independent multiple settings and upload limits across hydration and mapping', () => {
        const hydrated = fromBackendField({
            ...backendUploadField(),
            metadata: {
                builderType: 'file_upload',
                multiple: true,
                max_files: 4,
                max_total_size_kb: 20480,
                allowed_mime_types: ['application/pdf'],
            },
        });

        expect(hydrated.metadata).toMatchObject({ multiple: true, max_files: 4, max_total_size_mb: 20 });
        expect(hydrated.metadata.max_total_size_kb).toBe(20480);
        const serialized = toBackendFields([hydrated])[0];
        expect(serialized?.metadata).toMatchObject({
            multiple: true,
            max_files: 4,
            max_total_size_kb: 20480,
            allowed_mime_types: ['application/pdf'],
        });
        expect(serialized?.metadata.max_total_size_mb).toBeUndefined();

        const single = fromBackendField({
            ...backendUploadField(),
            metadata: { builderType: 'file_upload', multiple: false, allowed_mime_types: ['text/plain'] },
        });
        expect(single.metadata.multiple).toBe(false);
        expect(toBackendFields([single])[0]?.metadata.multiple).toBe(false);
    });

    it('maps legacy accepts into the typed allowlist while retaining the legacy value', () => {
        const hydrated = fromBackendField({
            ...backendUploadField(),
            metadata: { builderType: 'image_upload', accepts: 'png, jpg, gif' },
        });

        expect(hydrated.metadata.allowed_mime_types).toEqual(['image/jpeg', 'image/png', 'image/gif']);
        expect(hydrated.metadata.accepts).toBe('png, jpg, gif');
        const serialized = toBackendFields([hydrated])[0];
        expect(serialized?.metadata.accepts).toBe('jpg,jpeg,png,gif');
        expect(serialized?.metadata.rules).toMatchObject({ mimes: 'jpg,jpeg,png,gif' });
    });

    it('uses typed MIME allowlists over stale legacy accepts when serializing', () => {
        const hydrated = fromBackendField({
            ...backendUploadField(),
            metadata: {
                builderType: 'file_upload',
                allowed_mime_types: ['application/pdf'],
                accepts: 'doc, xls',
                rules: { mimes: 'doc,xls' },
            },
        });

        const serialized = toBackendFields([hydrated])[0];

        expect(serialized?.metadata.allowed_mime_types).toEqual(['application/pdf']);
        expect(serialized?.metadata.accepts).toBe('pdf');
        expect(serialized?.metadata.rules).toMatchObject({ mimes: 'pdf' });
    });

    it('defaults, clamps, serializes and resets multiple-upload limits', () => {
        const hydrated = fromBackendField({
            ...backendUploadField(),
            metadata: { builderType: 'file_upload', multiple: true },
        });

        expect(hydrated.metadata).toMatchObject({
            multiple: true,
            max_files: DEFAULT_UPLOAD_LIMITS.max_files,
            max_total_size_mb: DEFAULT_UPLOAD_LIMITS.max_total_size_mb,
        });
        expect(normalizeUploadLimit('max_files', 99)).toBe(5);
        expect(normalizeUploadLimit('max_total_size_mb', 50)).toBe(20);
        expect(normalizeUploadLimit('max_files', '')).toBe(DEFAULT_UPLOAD_LIMITS.max_files);
        expect(normalizeUploadLimit('max_total_size_mb', '')).toBe(DEFAULT_UPLOAD_LIMITS.max_total_size_mb);

        const reset = {
            ...hydrated,
            metadata: {
                ...hydrated.metadata,
                max_files: normalizeUploadLimit('max_files', ''),
                max_total_size_mb: normalizeUploadLimit('max_total_size_mb', ''),
            },
        };
        expect(toBackendFields([reset])[0]?.metadata).toMatchObject({
            max_files: DEFAULT_UPLOAD_LIMITS.max_files,
            max_total_size_kb: DEFAULT_UPLOAD_LIMITS.max_total_size_kb,
        });

        const outOfRange = {
            ...hydrated,
            metadata: { ...hydrated.metadata, max_files: 99, max_total_size_mb: 50 },
        };
        const serialized = toBackendFields([outOfRange])[0];
        expect(serialized?.metadata).toMatchObject({
            max_files: 5,
            max_total_size_kb: 20480,
        });
    });

    it('hydrates legacy kilobyte limits as megabytes and serializes only the legacy KB key', () => {
        const hydrated = fromBackendField({
            ...backendUploadField(),
            metadata: { builderType: 'file_upload', multiple: true, max_total_size_kb: 5120 },
        });

        expect(hydrated.metadata.max_total_size_mb).toBe(5);

        const serialized = toBackendFields([hydrated])[0];

        expect(serialized?.metadata.max_total_size_kb).toBe(5120);
        expect(serialized?.metadata.max_total_size_mb).toBeUndefined();
    });

    it('clears multiple-file limits after disabling and preserves legacy single-file metadata', () => {
        const enabledMetadata = setUploadMultipleMetadata({}, true);
        const configuredMetadata = { ...enabledMetadata, max_files: 3, max_total_size_mb: 8 };
        const singleMetadata = setUploadMultipleMetadata(configuredMetadata, false);

        expect(singleMetadata).toMatchObject({ multiple: false });
        expect(singleMetadata.max_files).toBeUndefined();
        expect(singleMetadata.max_total_size_mb).toBeUndefined();
        expect(singleMetadata.max_total_size_kb).toBeUndefined();

        const legacySingle = fromBackendField({
            ...backendUploadField(),
            metadata: {
                builderType: 'file_upload',
                multiple: false,
                accepts: 'pdf',
                max_total_size_kb: 1024,
            },
        });
        const serialized = toBackendFields([
            { ...legacySingle, metadata: { ...legacySingle.metadata, ...singleMetadata, accepts: 'pdf' } },
        ])[0];

        expect(serialized?.metadata.multiple).toBe(false);
        expect(serialized?.metadata.accepts).toBe('pdf');
        expect(serialized?.metadata.max_total_size_kb).toBeUndefined();
        expect(serialized?.metadata.max_files).toBeUndefined();
    });

    it('toggles a format off and refuses to turn off the final enabled format', () => {
        expect(toggleAllowedMimeType(['image/jpeg', 'image/png'], 'image/png', false)).toEqual(['image/jpeg']);
        expect(toggleAllowedMimeType(['image/jpeg'], 'image/jpeg', false)).toEqual(['image/jpeg']);
        expect(toggleAllowedMimeType(['image/jpeg'], 'image/png', true)).toEqual(['image/jpeg', 'image/png']);
    });
});

describe('subtype identitas: nik, nim, kode pos', () => {
    const IDENTITY_TYPES = ['nik', 'nim', 'postal_code'] as const;

    it('maps each subtype to an input field with matching metadata type and builderType', () => {
        for (const type of IDENTITY_TYPES) {
            const [backend] = toBackendFields([builderField({ type })]);

            expect(backend?.type).toBe('input');
            expect(backend?.metadata).toMatchObject({ type, builderType: type });
        }
    });

    it('hydrates a saved metadata type back into the matching builder type', () => {
        for (const type of IDENTITY_TYPES) {
            const field = fromBackendField({
                id: 'bf-identity',
                type: 'input',
                label: 'Field',
                description: null,
                name: 'field_1',
                order: 1,
                metadata: { type },
            });

            expect(field.type).toBe(type);
        }
    });

    it('keeps the explicit input format when serializing a text field', () => {
        const [backend] = toBackendFields([
            builderField({ type: 'short_text', required: true, metadata: { rules: { format: 'tel' } } }),
        ]);

        expect(backend?.metadata).toMatchObject({ rules: { format: 'tel', required: true } });
    });

    it('keeps the explicit input format when serializing a number field', () => {
        const [backend] = toBackendFields([
            builderField({ type: 'number', metadata: { rules: { format: 'number' } } }),
        ]);

        expect(backend?.metadata).toMatchObject({ rules: { format: 'number' } });
    });

    it('keeps the required rule when serializing an identity subtype', () => {
        const [backend] = toBackendFields([builderField({ type: 'nik', required: true })]);

        expect(backend?.metadata).toMatchObject({ type: 'nik', rules: { required: true } });
    });
});

describe('price field mapping', () => {
    it('keeps the price metadata through a backend round trip', () => {
        const backend = toBackendFields([builderField({ type: 'price', metadata: { price: 95_000 } })]);
        const first = backend[0];

        if (first === undefined) {
            throw new Error('price field was not serialized');
        }

        expect(first.type).toBe('input');
        expect(backendMetaOf(backend, 'field_1')).toMatchObject({ builderType: 'price', price: 95_000 });

        const restored = fromBackendField(first);

        expect(restored.type).toBe('price');
        expect(restored.metadata.price).toBe(95_000);
    });

    it('drops a stale price from a heading in both directions', () => {
        const backend = toBackendFields([
            builderField({ type: 'heading', metadata: { content: 'Payment', price: 95_000 } }),
        ]);
        const first = backend[0];

        if (first === undefined) {
            throw new Error('heading field was not serialized');
        }

        expect(backendMetaOf(backend, 'field_1').price).toBeUndefined();

        const restored = fromBackendField({
            ...first,
            metadata: { ...first.metadata, builderType: 'heading', price: 95_000 },
        });

        expect(restored.type).toBe('heading');
        expect(restored.metadata.price).toBeUndefined();
    });
});

function backendUploadField(): IBackendField {
    return {
        id: 'upload-field',
        type: 'fileUpload',
        label: 'Upload',
        description: null,
        name: 'upload',
        order: 1,
        metadata: {},
    };
}
