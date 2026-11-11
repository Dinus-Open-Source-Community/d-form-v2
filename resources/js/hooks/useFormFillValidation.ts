import { computed, type ComputedRef } from 'vue';
import type { InertiaForm } from '@inertiajs/vue3';
import { buildFieldLabelMap, getFieldError, type TErrorMessageContext } from '@/lib/error-message';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import {
    formFieldAnswerKey,
    formFieldAnswerKeys,
    hasFormAnswerValue,
    isFormFieldRequired,
    isFormFillFieldDisplayOnly,
} from '@/lib/formFillFields';
import type { TFormFillAnswerMap } from '@/types/form';

export interface IUseFormFillValidationOptions {
    answerForm: InertiaForm<TFormFillAnswerMap>;
    fields: readonly IFormField[];
    registrationMode: string;
    memberSlots: number;
}

export interface IUseFormFillValidation {
    errorContext: ComputedRef<TErrorMessageContext>;
    fieldError: (name: string) => string | undefined;
    validateSection: (fieldsInSection: IFormField[]) => boolean;
    cardErrorsForFields: (fields: IFormField[]) => string[];
    cardErrorsForBundleSlot: (fields: IFormField[], slotIndex: number | null) => string[];
}

/**
 * Required-answer validation and server-error lookup for the fill page.
 * `validateSection` also writes field errors and shows the "section incomplete" toast.
 */
export function useFormFillValidation(options: IUseFormFillValidationOptions): IUseFormFillValidation {
    const { showErrorToast } = useToastNotifications();
    const errorContext = computed<TErrorMessageContext>(() => ({
        fields: [...options.fields],
        fieldLabels: buildFieldLabelMap([...options.fields]),
    }));

    function fieldError(name: string): string | undefined {
        return getFieldError(options.answerForm.errors, name, errorContext.value);
    }

    function answerKeysFor(field: IFormField): string[] {
        return formFieldAnswerKeys({
            field,
            registrationMode: options.registrationMode,
            memberSlots: options.memberSlots,
        });
    }

    /** Validates required answers before a respondent moves to the next form section. */
    function validateSection(fieldsInSection: IFormField[]): boolean {
        const missingKeys: string[] = [];
        for (const field of fieldsInSection) {
            if (isFormFillFieldDisplayOnly(field) || !isFormFieldRequired(field)) continue;
            for (const key of answerKeysFor(field)) {
                if (!hasFormAnswerValue(options.answerForm[key])) missingKeys.push(key);
            }
        }
        if (missingKeys.length === 0) return true;

        for (const key of missingKeys) options.answerForm.setError(key, 'This field is required.');
        showErrorToast('Lengkapi field wajib sebelum melanjutkan.', { title: 'Bagian belum lengkap' });
        return false;
    }

    function cardErrorsForFields(fields: IFormField[]): string[] {
        const messages = new Set<string>();
        for (const field of fields) {
            for (const key of answerKeysFor(field)) {
                const message = fieldError(key);
                if (message) messages.add(message);
            }
        }
        return [...messages];
    }

    function cardErrorsForBundleSlot(fields: IFormField[], slotIndex: number | null): string[] {
        const messages = new Set<string>();
        for (const field of fields) {
            const message = fieldError(formFieldAnswerKey(field, slotIndex));
            if (message) messages.add(message);
        }
        return [...messages];
    }

    return { errorContext, fieldError, validateSection, cardErrorsForFields, cardErrorsForBundleSlot };
}
