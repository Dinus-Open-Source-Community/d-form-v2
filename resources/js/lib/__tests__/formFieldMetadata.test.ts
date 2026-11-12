import { describe, expect, it } from 'vitest';
import type { TFormFieldMetadataBag } from '@/types/form';
import {
    readFieldMetadata,
    readFieldRules,
    readMetaBoolean,
    readMetaNumber,
    readMetaText,
    readMetadataBag,
    readRuleSet,
} from '@/lib/formFieldMetadata';

function makeField(overrides: Partial<IFormField> = {}): IFormField {
    return { id: 'f1', type: 'input', label: 'Field', name: 'field', order: 1, metadata: {}, ...overrides };
}

/**
 * The parser exists to tolerate non-contract metadata (JSON string, array) returned by the API, so this
 * fixture intentionally bypasses the static bag type at the test boundary.
 */
function makeFieldWithRuntimeMetadata(metadata: unknown): IFormField {
    return {
        id: 'f1',
        type: 'input',
        label: 'Field',
        name: 'field',
        order: 1,
        metadata: metadata as Record<string, unknown>,
    };
}

function meta(value: unknown): TFormFieldMetadataBag {
    return { flag: value };
}

describe('readMetaBoolean', () => {
    it.each([true, 1, 'true', '1', 'yes', 'on', ' TRUE '])('reads %j as true', (value: unknown) => {
        expect(readMetaBoolean(meta(value), 'flag')).toBe(true);
    });

    it.each([false, 0, null, undefined, '', 'false', '0', 'no', 'off', 'FALSE'])(
        'reads %j as false',
        (value: unknown) => {
            expect(readMetaBoolean(meta(value), 'flag')).toBe(false);
        }
    );

    it('treats a missing key as false', () => {
        expect(readMetaBoolean({}, 'flag')).toBe(false);
    });
});

describe('readFieldMetadata', () => {
    it('returns an object bag untouched', () => {
        const bag: TFormFieldMetadataBag = { placeholder: 'Nama' };

        expect(readFieldMetadata(makeField({ metadata: bag }))).toBe(bag);
    });

    it('parses a JSON string bag', () => {
        expect(readFieldMetadata(makeFieldWithRuntimeMetadata('{"a":1}'))).toEqual({ a: 1 });
    });

    it('returns an empty bag for an invalid JSON string', () => {
        expect(readFieldMetadata(makeFieldWithRuntimeMetadata('{oops'))).toEqual({});
    });

    it('takes the first plain object from an array bag', () => {
        expect(readFieldMetadata(makeFieldWithRuntimeMetadata([null, 'x', { x: 1 }]))).toEqual({ x: 1 });
        expect(readFieldMetadata(makeFieldWithRuntimeMetadata([1, 2]))).toEqual({});
    });

    it('returns an empty bag for a non-object bag', () => {
        expect(readFieldMetadata(makeFieldWithRuntimeMetadata(null))).toEqual({});
        expect(readFieldMetadata(makeFieldWithRuntimeMetadata(42))).toEqual({});
    });
});

describe('readFieldRules', () => {
    it('returns a plain rules object', () => {
        const rules = { required: true, max: 5 };

        expect(readFieldRules(makeField({ metadata: { rules } }))).toEqual(rules);
    });

    it('returns empty rules when absent or not an object', () => {
        expect(readFieldRules(makeField())).toEqual({});
        expect(readFieldRules(makeField({ metadata: { rules: 'required' } }))).toEqual({});
    });

    it('reads rules from a JSON string metadata bag', () => {
        expect(readFieldRules(makeFieldWithRuntimeMetadata('{"rules":{"required":true}}'))).toEqual({ required: true });
    });
});

describe('readMetadataBag / readRuleSet', () => {
    it('normalizes non-record values to an empty bag', () => {
        expect(readMetadataBag(null)).toEqual({});
        expect(readMetadataBag(42)).toEqual({});
        expect(readMetadataBag(['x'])).toEqual({});
    });

    it('keeps a record bag untouched and reads rules only when they are a record', () => {
        const bag = readMetadataBag({ rules: { required: true } });

        expect(bag).toEqual({ rules: { required: true } });
        expect(readRuleSet(bag)).toEqual({ required: true });
        expect(readRuleSet(readMetadataBag({ rules: 'required' }))).toEqual({});
    });
});

describe('readMetaText / readMetaNumber', () => {
    it('reads typed values and falls back for malformed ones', () => {
        const bag = readMetadataBag({ content: 'Heading', maxStars: 4, caption: 42, maxLength: '80' });

        expect(readMetaText(bag, 'content', 'Default')).toBe('Heading');
        expect(readMetaText(bag, 'caption', 'Default')).toBe('Default');
        expect(readMetaNumber(bag, 'maxStars', 5)).toBe(4);
        expect(readMetaNumber(bag, 'maxLength', 5)).toBe(5);
    });
});
