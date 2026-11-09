import type { IBuilderField } from '@/types/form-builder';

/**
 * New canvas fields (create + edit form) — matches the builder palette / toBackendField mapping.
 */
export function createFormBuilderField(type: TFormBuilderType, label: string): IBuilderField {
    const defaults: Partial<Record<TFormBuilderType, Partial<IBuilderField>>> = {
        dropdown: { options: [{ id: crypto.randomUUID(), type: 'text', label: 'Option 1' }] },
        checkbox: { options: [{ id: crypto.randomUUID(), type: 'text', label: 'Choice A' }] },
        radio: { options: [{ id: crypto.randomUUID(), type: 'text', label: 'Radio 1' }] },
        rating: { metadata: { maxStars: 5 } },
        heading: { metadata: { content: 'Section Heading' } },
        image: { metadata: { imageUrl: '', caption: '', alt: '' } },
        paragraph: { metadata: { content: 'Description text goes here...' } },
    };
    const base: IBuilderField = {
        id: crypto.randomUUID(),
        type,
        label: label || 'Untitled Field',
        name: `field_${crypto.randomUUID().replace(/-/g, '').slice(0, 12)}`,
        description: '',
        placeholder: '',
        required: false,
        options: [],
        metadata: {},
        is_append: false,
    };
    return { ...base, ...(defaults[type] || {}) };
}
