import { describe, expect, it } from 'vitest';
import contract from '../../../../tests/fixtures/form-field-contract.json';
import { isFormBuilderFieldType } from '@/lib/formBuilderFieldType';
import {
    DISPLAY_ONLY_BUILDER_TYPES,
    FORM_FILL_DISPLAY_ONLY_TYPES,
    isDisplayOnlyBuilderType,
} from '@/lib/formFieldContract';

interface IContractRow {
    displayOnly: boolean;
    apiType: string;
}

const builderTypes: Record<string, IContractRow> = contract.builderTypes;

describe('form field contract', () => {
    it('recognizes every builder type declared in the shared fixture', () => {
        for (const builderType of Object.keys(builderTypes)) {
            expect(isFormBuilderFieldType(builderType)).toBe(true);
        }

        expect(isFormBuilderFieldType('not_a_builder_type')).toBe(false);
        expect(isFormBuilderFieldType(null)).toBe(false);
    });

    it('keeps the client display-only lists equal to the shared fixture', () => {
        const expected = Object.keys(builderTypes).filter((builderType) => builderTypes[builderType].displayOnly);

        expect([...FORM_FILL_DISPLAY_ONLY_TYPES].sort()).toEqual([...expected].sort());
        expect([...DISPLAY_ONLY_BUILDER_TYPES].sort()).toEqual([...expected].sort());

        for (const builderType of Object.keys(builderTypes)) {
            expect(isDisplayOnlyBuilderType(builderType)).toBe(builderTypes[builderType].displayOnly);
        }
    });

    it('maps every builder type to an API type declared by the contract', () => {
        for (const builderType of Object.keys(builderTypes)) {
            expect(contract.apiTypes).toContain(builderTypes[builderType].apiType);
        }
    });
});
