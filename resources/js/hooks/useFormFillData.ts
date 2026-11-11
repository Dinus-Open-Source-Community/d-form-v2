import { computed, type ComputedRef } from 'vue';
import { useForm, type InertiaForm } from '@inertiajs/vue3';
import { pickFormBannerField } from '@/components/modules/builder/formBanner';
import { isCheckboxOptionSelected, toggleCheckboxSelection } from '@/lib/formCheckboxAnswers';
import { getFormFieldOptionRows } from '@/lib/formFieldOptions';
import { readFieldMetadata } from '@/lib/formFieldMetadata';
import { createFormFillInitialValues, resolveFormAccessBlockCopy } from '@/lib/formFillFields';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import type {
    IFormFillBlockCopy,
    IFormFillPageEvent,
    IFormFillPageForm,
    TFormAccessStatus,
    TFormFillAnswerMap,
    TFormFillAnswerValue,
    TFormFillOptionRow,
} from '@/types/form';

export interface IUseFormFillDataOptions {
    event: IFormFillPageEvent;
    form: IFormFillPageForm;
    fields: readonly IFormField[];
    accessStatus: TFormAccessStatus;
    accessMessage: string;
    memberSlots: number;
    registrationMode: string;
}

export interface IUseFormFillData {
    answerForm: InertiaForm<TFormFillAnswerMap>;
    registrationMode: string;
    memberSlots: ComputedRef<number>;
    formBannerImageSrc: ComputedRef<string>;
    formBannerCaption: ComputedRef<string>;
    formHasDescription: ComputedRef<boolean>;
    isBlocked: ComputedRef<boolean>;
    blockCopy: ComputedRef<IFormFillBlockCopy>;
    getOptionRows: (field: IFormField) => TFormFillOptionRow[];
    getSelectedOptionRow: (field: IFormField, storageKey?: string) => TFormFillOptionRow | undefined;
    onCheckboxToggle: (fieldName: string, option: string, checked: boolean) => void;
    isCheckboxSelected: (fieldName: string, option: string) => boolean;
    setAnswer: (fieldName: string, value: TFormFillAnswerValue) => void;
    setTeamMemberEmails: (emails: string[]) => void;
    fileAnswerName: (fieldName: string) => string;
}

/**
 * Owns the fill `useForm` instance plus banner, access-block, and answer-write helpers.
 * Validation, upload, and submit live in their own hooks and receive this form instance.
 */
export function useFormFillData(options: IUseFormFillDataOptions): IUseFormFillData {
    const memberSlots = computed(() => options.memberSlots);
    const formBannerField = computed(() => pickFormBannerField(options.fields));

    const answerForm = useForm<TFormFillAnswerMap>(
        createFormFillInitialValues({
            fields: options.fields,
            memberSlots: options.memberSlots,
            registrationMode: options.registrationMode,
        })
    );

    const formBannerImageSrc = computed(() => {
        const bannerField = formBannerField.value;
        const meta = bannerField ? readFieldMetadata(bannerField) : {};
        const rawUrl = meta.bannerUrl;
        const url = typeof rawUrl === 'string' && rawUrl !== '' ? rawUrl : options.form.banner_url;
        if (!url) return '';
        return normalizePublicStorageUrl(url);
    });

    const formBannerCaption = computed(() => {
        const bannerField = formBannerField.value;
        if (bannerField) {
            const content = readFieldMetadata(bannerField).content;
            if (typeof content === 'string' && content.trim()) return content;
        }
        return options.form.banner_caption ?? '';
    });

    function getOptionRows(field: IFormField): TFormFillOptionRow[] {
        return getFormFieldOptionRows(field);
    }

    function getSelectedOptionRow(field: IFormField, storageKey?: string): TFormFillOptionRow | undefined {
        const value = answerForm[storageKey ?? field.name];
        if (typeof value !== 'string') return undefined;
        return getOptionRows(field).find((row) => row.label === value);
    }

    function onCheckboxToggle(fieldName: string, option: string, checked: boolean): void {
        answerForm[fieldName] = toggleCheckboxSelection(answerForm[fieldName], option, checked);
    }

    function isCheckboxSelected(fieldName: string, option: string): boolean {
        return isCheckboxOptionSelected(answerForm[fieldName], option);
    }

    /** Sets one answer value; field components call this instead of mutating the form prop. */
    function setAnswer(fieldName: string, value: TFormFillAnswerValue): void {
        answerForm[fieldName] = value;
    }

    /** Replaces the team member email list; owned here so child components never mutate the form prop. */
    function setTeamMemberEmails(emails: string[]): void {
        answerForm.team_member_emails = emails;
    }

    /** Uploaded file name for a slot; empty when the answer is not a file. */
    function fileAnswerName(fieldName: string): string {
        const value = answerForm[fieldName];
        if (typeof File === 'undefined' || !(value instanceof File)) return '';
        return value.name;
    }

    return {
        answerForm,
        registrationMode: options.registrationMode,
        memberSlots,
        formBannerImageSrc,
        formBannerCaption,
        formHasDescription: computed(() => Boolean(options.form.description?.trim())),
        isBlocked: computed(() => options.accessStatus !== 'allowed'),
        blockCopy: computed(() => resolveFormAccessBlockCopy(options.accessStatus, options.accessMessage)),
        getOptionRows,
        getSelectedOptionRow,
        onCheckboxToggle,
        isCheckboxSelected,
        setAnswer,
        setTeamMemberEmails,
        fileAnswerName,
    };
}
