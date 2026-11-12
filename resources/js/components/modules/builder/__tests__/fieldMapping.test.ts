import { describe, expect, it } from 'vitest';
import {
    DEFAULT_ALLOWED_MIME_TYPES,
    DEFAULT_UPLOAD_LIMITS,
    fromBackendField,
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
