import { readFieldMetadata } from '@/lib/formFieldMetadata';
import type { IFormBuilderSection } from '@/types/form-builder';
import type { TFormFillSegment } from '@/types/form';

/** Default section id when a field has no valid `metadata.sectionId`. */
const DEFAULT_SECTION_ID = 'section-1';

/** Section a field belongs to; invalid or missing ids fall back to the first section. */
export function resolveFormFillSectionId(field: IFormField, sections: readonly IFormBuilderSection[]): string {
    const raw = readFieldMetadata(field).sectionId;
    if (typeof raw === 'string' && raw !== '' && sections.some((section) => section.id === raw)) {
        return raw;
    }
    return sections[0]?.id ?? DEFAULT_SECTION_ID;
}

/** Fields belonging to one section, preserving builder order. */
export function formFillSectionFields(
    fields: readonly IFormField[],
    sections: readonly IFormBuilderSection[],
    sectionId: string
): IFormField[] {
    return fields.filter((field) => resolveFormFillSectionId(field, sections) === sectionId);
}

/** Groups consecutive bundle-participant fields so each group renders one participant card set. */
export function buildFormFillSegments(
    fields: readonly IFormField[],
    isBundleDuplicatable: (field: IFormField) => boolean
): TFormFillSegment[] {
    const segments: TFormFillSegment[] = [];
    let index = 0;

    while (index < fields.length) {
        const groupIsBundle = isBundleDuplicatable(fields[index]);
        const group: IFormField[] = [];

        while (index < fields.length && isBundleDuplicatable(fields[index]) === groupIsBundle) {
            group.push(fields[index]);
            index++;
        }

        segments.push({ type: groupIsBundle ? 'bundleGroup' : 'linear', fields: group });
    }

    return segments;
}
