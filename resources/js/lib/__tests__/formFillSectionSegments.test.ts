import { describe, expect, it } from 'vitest';
import { makeFormField } from '@/lib/__tests__/formFillFieldFixtures';
import { buildFormFillSegments, formFillSectionFields, resolveFormFillSectionId } from '@/lib/formFillSectionSegments';
import type { IFormBuilderSection } from '@/types/form-builder';

const SECTIONS: IFormBuilderSection[] = [
    { id: 'personal', title: 'Personal' },
    { id: 'experience', title: 'Experience' },
];

describe('formFillSectionSegments', () => {
    it('resolves a declared section id only when it exists', () => {
        expect(
            resolveFormFillSectionId(makeFormField({ name: 'a', metadata: { sectionId: 'experience' } }), SECTIONS)
        ).toBe('experience');
        expect(
            resolveFormFillSectionId(makeFormField({ name: 'b', metadata: { sectionId: 'missing' } }), SECTIONS)
        ).toBe('personal');
        expect(resolveFormFillSectionId(makeFormField({ name: 'c' }), [])).toBe('section-1');
    });

    it('filters section fields without changing builder order', () => {
        const fields = [
            makeFormField({ name: 'first', order: 0, metadata: { sectionId: 'experience' } }),
            makeFormField({ name: 'second', order: 1 }),
            makeFormField({ name: 'third', order: 2, metadata: { sectionId: 'experience' } }),
        ];

        expect(formFillSectionFields(fields, SECTIONS, 'personal').map((field) => field.name)).toEqual(['second']);
        expect(formFillSectionFields(fields, SECTIONS, 'experience').map((field) => field.name)).toEqual([
            'first',
            'third',
        ]);
    });

    it('groups consecutive bundle fields into a segment and keeps the rest linear', () => {
        const fields = [
            makeFormField({ name: 'a' }),
            makeFormField({ name: 'b' }),
            makeFormField({ name: 'c' }),
            makeFormField({ name: 'd' }),
        ];
        const bundleNames = new Set(['b', 'c']);

        const segments = buildFormFillSegments(fields, (field) => bundleNames.has(field.name));

        expect(segments).toEqual([
            { type: 'linear', fields: [fields[0]] },
            { type: 'bundleGroup', fields: [fields[1], fields[2]] },
            { type: 'linear', fields: [fields[3]] },
        ]);
    });

    it('returns no segments for an empty section', () => {
        expect(buildFormFillSegments([], () => true)).toEqual([]);
    });

    it('keeps a single bundle group when every field is duplicatable', () => {
        const fields = [makeFormField({ name: 'a' }), makeFormField({ name: 'b' })];

        expect(buildFormFillSegments(fields, () => true)).toEqual([{ type: 'bundleGroup', fields }]);
    });
});
