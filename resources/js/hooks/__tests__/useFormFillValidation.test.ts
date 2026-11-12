import { describe, expect, it } from 'vitest';
import { useForm } from '@inertiajs/vue3';
import { makeFormField } from '@/lib/__tests__/formFillFieldFixtures';
import { useFormFillValidation } from '@/hooks/useFormFillValidation';
import type { TFormFillAnswerMap } from '@/types/form';

function createValidation(
    fields: IFormField[],
    initial: TFormFillAnswerMap,
    options: { registrationMode?: string; memberSlots?: number } = {}
) {
    const answerForm = useForm<TFormFillAnswerMap>(initial);
    const validation = useFormFillValidation({
        answerForm,
        fields,
        registrationMode: options.registrationMode ?? 'single',
        memberSlots: options.memberSlots ?? 0,
    });

    return { answerForm, validation };
}

describe('useFormFillValidation', () => {
    it('accepts a section when every required answer is filled', () => {
        const { validation } = createValidation(
            [makeFormField({ name: 'full_name', metadata: { rules: { required: true } } })],
            { full_name: 'Ada' }
        );

        expect(
            validation.validateSection([makeFormField({ name: 'full_name', metadata: { rules: { required: true } } })])
        ).toBe(true);
    });

    it('rejects a missing required answer and writes a field error', () => {
        const field = makeFormField({ name: 'full_name', metadata: { rules: { required: true } } });
        const { answerForm, validation } = createValidation([field], { full_name: '' });

        expect(validation.validateSection([field])).toBe(false);
        expect(answerForm.errors.full_name).toBeTruthy();
        expect(validation.fieldError('full_name')).toBeTruthy();
    });

    it('ignores empty optional and display-only fields', () => {
        const optional = makeFormField({ name: 'nickname' });
        const heading = makeFormField({ name: 'intro', type: 'heading', metadata: { rules: { required: true } } });
        const { validation } = createValidation([optional, heading], { nickname: '' });

        expect(validation.validateSection([optional, heading])).toBe(true);
    });

    it('treats whitespace and empty arrays as empty answers', () => {
        const text = makeFormField({ name: 'full_name', metadata: { rules: { required: true } } });
        const topics = makeFormField({ name: 'topics', type: 'checkbox', metadata: { rules: { required: true } } });
        const { validation } = createValidation([text, topics], { full_name: '   ', topics: [] });

        expect(validation.validateSection([text, topics])).toBe(false);
    });

    it('validates every bundle participant key of a duplicatable field', () => {
        const field = makeFormField({ name: 'shirt', metadata: { duplicatable: true, rules: { required: true } } });
        const { answerForm, validation } = createValidation(
            [field],
            { shirt: 'L', bundle__shirt__0: '' },
            { registrationMode: 'bundle', memberSlots: 1 }
        );

        expect(validation.validateSection([field])).toBe(false);
        expect(answerForm.errors.shirt).toBeUndefined();
        expect(answerForm.errors['bundle__shirt__0']).toBeTruthy();
        expect(validation.cardErrorsForBundleSlot([field], 0)).toHaveLength(1);
        expect(validation.cardErrorsForBundleSlot([field], null)).toEqual([]);
    });

    it('de-duplicates identical messages across a field card', () => {
        const field = makeFormField({ name: 'shirt', metadata: { duplicatable: true, rules: { required: true } } });
        const { answerForm, validation } = createValidation(
            [field],
            { shirt: '', bundle__shirt__0: '' },
            { registrationMode: 'bundle', memberSlots: 1 }
        );

        answerForm.setError('shirt', 'This field is required.');
        answerForm.setError('bundle__shirt__0', 'This field is required.');

        expect(validation.cardErrorsForFields([field])).toEqual(['This field is required.']);
    });

    it('returns no errors for unanswered optional fields', () => {
        const field = makeFormField({ name: 'note' });
        const { validation } = createValidation([field], { note: '' });

        expect(validation.cardErrorsForFields([field])).toEqual([]);
        expect(validation.fieldError('note')).toBeUndefined();
    });
});
