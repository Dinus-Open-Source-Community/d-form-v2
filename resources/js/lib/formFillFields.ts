import { formFieldBuilderType } from '@/lib/formFieldOptions';
import { FORM_FILL_DISPLAY_ONLY_TYPES } from '@/lib/formFieldContract';
import { readFieldMetadata, readFieldRules, readMetaBoolean } from '@/lib/formFieldMetadata';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import type {
    IFormFieldParticipationSlot,
    IFormFillBlockCopy,
    TFormAccessStatus,
    TFormFillAnswerMap,
    TFormFillAnswerValue,
} from '@/types/form';

/** Fallback copy when the backend does not provide a specific access message. */
const FORM_ACCESS_FALLBACK_MESSAGE = 'This form is not available right now.';

/** Access-block titles per guard result; `allowed` renders no card at all. */
const FORM_ACCESS_BLOCK_TITLES: Record<TFormAccessStatus, string> = {
    allowed: '',
    already_submitted: 'You have already submitted this form.',
    form_closed: 'Registration is closed.',
    registration_not_open: 'Registration is not currently open.',
    quota_full: 'Registration is full.',
    unsupported_registration_mode: 'This registration type is not available yet.',
    pending_team_confirmation: 'Confirm your team invitation',
    invitation_closed: 'Invitation no longer active',
    event_form_already_chosen: 'Another registration form was already chosen for this event.',
    prerequisite_not_met: 'Required form not completed yet.',
    not_visible: 'You do not have access to this form.',
};

/** Builder type for a fill field; shared with option parsing so both resolve display types identically. */
export function formFillBuilderType(field: IFormField): string {
    const resolved = formFieldBuilderType(field);
    return resolved === '' ? field.type : resolved;
}

/** True for heading/paragraph/divider/banner/image fields, which are not answers. */
export function isFormFillFieldDisplayOnly(field: IFormField): boolean {
    return FORM_FILL_DISPLAY_ONLY_TYPES.includes(formFillBuilderType(field));
}

/** Bundle answer key format `<prefix>__<field>__<slot>`; mirrors `BundleSubmissionRules::answerKey()`. */
export function buildBundleAnswerKey(fieldName: string, slotIndex: number): string {
    return `bundle__${fieldName}__${slotIndex}`;
}

/** Answer-map key for one field slot; `null` keeps the plain field name. */
export function formFieldAnswerKey(field: IFormField, slotIndex: number | null): string {
    return slotIndex === null ? field.name : buildBundleAnswerKey(field.name, slotIndex);
}

/** True when a field repeats per bundle participant and is answerable. */
export function isBundleDuplicatableField(field: IFormField, registrationMode: string): boolean {
    return (
        registrationMode === 'bundle' &&
        readFieldMetadata(field).duplicatable === true &&
        !isFormFillFieldDisplayOnly(field)
    );
}

/** Participation slots rendered for a field: one entry unless the field repeats per bundle participant. */
export function formFieldParticipationSlots(args: {
    field: IFormField;
    registrationMode: string;
    memberSlots: number;
}): IFormFieldParticipationSlot[] {
    if (!isBundleDuplicatableField(args.field, args.registrationMode)) {
        return [{ slotIndex: null, title: '' }];
    }

    const rows: IFormFieldParticipationSlot[] = [{ slotIndex: null, title: 'Lead participant' }];
    for (let i = 0; i < args.memberSlots; i++) {
        rows.push({ slotIndex: i, title: `Participant ${i + 2}` });
    }
    return rows;
}

/** Every answer key a field can produce, across its participation slots. */
export function formFieldAnswerKeys(args: {
    field: IFormField;
    registrationMode: string;
    memberSlots: number;
}): string[] {
    return formFieldParticipationSlots(args).map((slot) => formFieldAnswerKey(args.field, slot.slotIndex));
}

/** Seeds the Inertia form map so every answerable field starts with the type it expects. */
export function createFormFillInitialValues(args: {
    fields: readonly IFormField[];
    memberSlots: number;
    registrationMode: string;
}): TFormFillAnswerMap {
    const values: TFormFillAnswerMap = {};

    function seed(key: string, field: IFormField): void {
        if (
            field.type === 'checkbox' ||
            (field.type === 'select' && readMetaBoolean(readFieldMetadata(field), 'is_multiple'))
        ) {
            values[key] = [];
        } else if (field.type === 'fileUpload') {
            values[key] = null;
        } else {
            values[key] = '';
        }
    }

    for (const field of args.fields) {
        if (isFormFillFieldDisplayOnly(field)) continue;
        seed(field.name, field);
    }

    if (args.memberSlots > 0) {
        values.team_member_emails = Array.from({ length: args.memberSlots }, () => '');
    }

    if (args.registrationMode === 'bundle' && args.memberSlots > 0) {
        for (const field of args.fields) {
            if (!isBundleDuplicatableField(field, args.registrationMode)) continue;
            for (let i = 0; i < args.memberSlots; i++) {
                seed(buildBundleAnswerKey(field.name, i), field);
            }
        }
    }

    return values;
}

/** Card copy for a blocked access status. */
export function resolveFormAccessBlockCopy(status: TFormAccessStatus, accessMessage: string): IFormFillBlockCopy {
    return {
        title: FORM_ACCESS_BLOCK_TITLES[status],
        body: accessMessage || FORM_ACCESS_FALLBACK_MESSAGE,
        success: status === 'already_submitted' ? true : undefined,
    };
}

/** HTML input subtype for the fill page text field. */
export function formFieldInputSubtype(field: IFormField): string {
    const type = readFieldMetadata(field).type;
    if (typeof type === 'string') {
        if (type === 'short_text') return 'text';
        if (type === 'phone') return 'tel';
        return type;
    }
    return formFillBuilderType(field) === 'url' ? 'url' : 'text';
}

/** Placeholder from field metadata; empty when absent. */
export function formFieldPlaceholder(field: IFormField): string {
    const placeholder = readFieldMetadata(field).placeholder;
    return typeof placeholder === 'string' ? placeholder : '';
}

/** Required flag from `metadata.rules.required`. */
export function isFormFieldRequired(field: IFormField): boolean {
    return Boolean(readFieldRules(field).required);
}

/** Multi-select flag from `metadata.is_multiple`. */
export function isFormFieldMultipleSelect(field: IFormField): boolean {
    return readMetaBoolean(readFieldMetadata(field), 'is_multiple');
}

/** Radio-like fields: API `radio`, builder `radio`, or builder `yes_no`. */
export function isFormFieldRadioLike(field: IFormField): boolean {
    const builderType = formFillBuilderType(field);
    return field.type === 'radio' || builderType === 'radio' || builderType === 'yes_no';
}

/** Checkbox-like fields: API `checkbox`, builder `checkbox`, or any multi-select. */
export function isFormFieldCheckboxLike(field: IFormField): boolean {
    const builderType = formFillBuilderType(field);
    return field.type === 'checkbox' || builderType === 'checkbox' || isFormFieldMultipleSelect(field);
}

/** Character limit for short/long text: `rules.max` wins, then `metadata.maxLength`. */
export function formFieldMaxLength(field: IFormField): number | undefined {
    const builderType = formFillBuilderType(field);
    if (builderType !== 'short_text' && builderType !== 'long_text') return undefined;

    const ruleMax = readFieldRules(field).max;
    const fromRule = positiveInteger(ruleMax);
    if (fromRule !== undefined) return fromRule;

    return positiveInteger(readFieldMetadata(field).maxLength);
}

/** Normalized positive integer from number or numeric string; `undefined` otherwise. */
function positiveInteger(value: unknown): number | undefined {
    if (typeof value === 'number') return Number.isFinite(value) && value > 0 ? value : undefined;
    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = parseInt(value, 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : undefined;
    }
    return undefined;
}

/** Upload helper line: aspect hint for images, allowed mimes, and max size. */
export function formFieldFileHint(field: IFormField): string {
    const parts: string[] = [];
    const fieldRules = readFieldRules(field);
    if (formFillBuilderType(field) === 'image_upload') parts.push('Recommended: 1200 x 900 px (4:3)');
    if (fieldRules.mimes) parts.push(`Allowed: ${String(fieldRules.mimes)}`);
    if (fieldRules.max_size) parts.push(`Max size: ${String(fieldRules.max_size)} KB`);
    return parts.join(' · ');
}

/** `accept` attribute from comma-separated mimes; `undefined` when not declared. */
export function formFieldAcceptAttribute(field: IFormField): string | undefined {
    const mimes = readFieldRules(field).mimes;
    if (typeof mimes !== 'string') return undefined;
    return mimes
        .split(',')
        .map((extension) => `.${extension.trim().replace(/^\./, '')}`)
        .join(',');
}

/** True when an answer holds user input; empty strings, arrays, and zero-byte files are empty. */
export function hasFormAnswerValue(value: TFormFillAnswerValue | undefined): boolean {
    if (value === null || value === undefined) return false;
    if (Array.isArray(value)) return value.length > 0;
    if (typeof File !== 'undefined' && value instanceof File) return value.size > 0;
    return typeof value === 'string' && value.trim() !== '';
}

/** True when a team/bundle member email list contains the signed-in account email. */
export function containsAccountEmail(emails: readonly string[] | undefined, accountEmail: string): boolean {
    const normalizedAccount = accountEmail.trim().toLowerCase();
    if (!normalizedAccount) return false;
    return (emails ?? []).some((email) => email.trim().toLowerCase() === normalizedAccount);
}

/** Display-only image block src from `metadata.imageUrl`, normalized for public storage. */
export function formFieldImageSrc(field: IFormField): string {
    const raw = readFieldMetadata(field).imageUrl;
    return typeof raw === 'string' ? normalizePublicStorageUrl(raw) : '';
}

/** String metadata value for a display-only block (e.g. `caption`, `alt`); empty when absent. */
export function formFieldMetadataText(field: IFormField, key: string): string {
    const raw = readFieldMetadata(field)[key];
    return typeof raw === 'string' ? raw : '';
}
