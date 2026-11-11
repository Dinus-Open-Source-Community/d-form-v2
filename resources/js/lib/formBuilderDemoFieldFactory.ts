import { createShortId, createUuid } from '@/lib/uuid';

/**
 * Default field baru untuk entry point demo (`pages/FormBuilder.vue`).
 * Berbeda dari `createFormBuilderField` (production): demo menyimpan opsi sebagai
 * string metadata karena tidak pernah dipetakan ke backend. Perbedaan ini sengaja
 * diisolasi di adapter, bukan diduplikasi di operasi daftar.
 */
export function createDemoBuilderField(type: TFormBuilderType, label: string): IFormField {
    const metadataDefaults: Partial<Record<TFormBuilderType, Record<string, unknown>>> = {
        short_text: { placeholder: '' },
        long_text: { placeholder: '' },
        email: { placeholder: '' },
        phone: { placeholder: '' },
        number: { placeholder: '' },
        dropdown: { options: 'Option 1, Option 2' },
        checkbox: { options: 'Choice A, Choice B, Choice C' },
        radio: { options: 'Option 1, Option 2, Option 3' },
        image_upload: { accepts: 'png, jpg, jpeg' },
        file_upload: { accepts: 'pdf, doc, xls' },
        rating: { maxStars: 5 },
        heading: { content: 'Section Heading' },
        paragraph: { content: '' },
    };

    return {
        id: createUuid(),
        type,
        label: label || 'Untitled Field',
        name: `field_${createShortId(12)}`,
        description: '',
        order: 0,
        metadata: metadataDefaults[type] ?? {},
    };
}
