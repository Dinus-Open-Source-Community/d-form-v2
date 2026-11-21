export const FORM_SUBMISSION_LIMIT_PATTERN = '^[0-9]+$';
export const MIN_FORM_SUBMISSIONS = 2;
export const MAX_FORM_SUBMISSIONS = 10000;

export interface IFormSubmissionLimitValidation {
    valid: boolean;
    value: number | null;
    message: string | null;
}

/** Removes every character outside the ASCII digit range from user input. */
export function filterFormSubmissionLimitInput(value: string): string {
    return value.replace(/[^0-9]/g, '');
}

/** Reports whether input contains anything outside ASCII digits. */
export function hasFormSubmissionLimitFormatError(value: string): boolean {
    return value !== '' && !new RegExp(FORM_SUBMISSION_LIMIT_PATTERN).test(value);
}

/** Validates the raw submission-limit text before it is serialized as metadata. */
export function validateFormSubmissionLimitInput(value: string): IFormSubmissionLimitValidation {
    if (value === '') {
        return { valid: false, value: null, message: 'Masukkan jumlah pengiriman maksimum.' };
    }

    if (!new RegExp(FORM_SUBMISSION_LIMIT_PATTERN).test(value)) {
        return { valid: false, value: null, message: 'Gunakan angka bulat tanpa tanda atau desimal.' };
    }

    const parsedValue: number = Number(value);
    if (parsedValue < MIN_FORM_SUBMISSIONS) {
        return { valid: false, value: null, message: 'Jumlah pengiriman maksimum minimal 2.' };
    }

    if (parsedValue > MAX_FORM_SUBMISSIONS) {
        return { valid: false, value: null, message: 'Jumlah pengiriman maksimum maksimal 10000.' };
    }

    return { valid: true, value: parsedValue, message: null };
}
