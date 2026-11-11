import { readFieldMetadata } from '@/lib/formFieldMetadata';
import {
    formFieldAcceptAttribute,
    formFieldAnswerKey,
    formFieldFileHint,
    formFieldInputSubtype,
    formFieldMaxLength,
    formFieldParticipationSlots,
    formFieldPlaceholder,
    formFillBuilderType,
    isBundleDuplicatableField,
    isFormFieldCheckboxLike,
    isFormFieldMultipleSelect,
    isFormFieldRadioLike,
    isFormFieldRequired,
} from '@/lib/formFillFields';
import { useFormFillData } from '@/hooks/useFormFillData';
import { useFormFillSubmit } from '@/hooks/useFormFillSubmit';
import { useFormFillUpload } from '@/hooks/useFormFillUpload';
import { useFormFillValidation } from '@/hooks/useFormFillValidation';
import type { IFormFillPageEvent, IFormFillPageForm, TFormAccessStatus } from '@/types/form';

export interface IFormFillPageOptions {
    event: IFormFillPageEvent;
    form: IFormFillPageForm;
    fields: IFormField[];
    submitUrl: string;
    accessStatus: TFormAccessStatus;
    accessMessage: string;
    memberSlots: number;
    registrationMode: string;
}

/**
 * Thin facade for the public form fill page: composes data, validation, upload, submit, and
 * fields-list hooks and exposes one context object. Pages import only this function.
 */
export function useFormFillPage(props: IFormFillPageOptions) {
    const fields = props.fields ?? [];

    const data = useFormFillData({
        event: props.event,
        form: props.form,
        fields,
        accessStatus: props.accessStatus,
        accessMessage: props.accessMessage,
        memberSlots: props.memberSlots,
        registrationMode: props.registrationMode,
    });

    const validation = useFormFillValidation({
        answerForm: data.answerForm,
        fields,
        registrationMode: props.registrationMode,
        memberSlots: props.memberSlots,
    });

    const upload = useFormFillUpload(data.answerForm);

    const submitter = useFormFillSubmit({
        answerForm: data.answerForm,
        fields,
        submitUrl: props.submitUrl,
        memberSlots: props.memberSlots,
        isBlocked: data.isBlocked,
    });

    function isBundleDuplicatable(field: IFormField): boolean {
        return isBundleDuplicatableField(field, props.registrationMode);
    }

    function participationSlotsForField(field: IFormField) {
        return formFieldParticipationSlots({
            field,
            registrationMode: props.registrationMode,
            memberSlots: props.memberSlots,
        });
    }

    return {
        answerForm: data.answerForm,
        metadata: readFieldMetadata,
        builderType: formFillBuilderType,
        registrationMode: data.registrationMode,
        formBannerImageSrc: data.formBannerImageSrc,
        formBannerCaption: data.formBannerCaption,
        formHasDescription: data.formHasDescription,
        isBlocked: data.isBlocked,
        blockCopy: data.blockCopy,
        memberSlots: data.memberSlots,
        getOptionRows: data.getOptionRows,
        getSelectedOptionRow: data.getSelectedOptionRow,
        isBundleDuplicatableField: isBundleDuplicatable,
        participationSlotsForField,
        answerKeyForSlot: formFieldAnswerKey,
        getInputSubtype: formFieldInputSubtype,
        getPlaceholder: formFieldPlaceholder,
        isRequired: isFormFieldRequired,
        isMultipleSelect: isFormFieldMultipleSelect,
        isRadioLike: isFormFieldRadioLike,
        isCheckboxLike: isFormFieldCheckboxLike,
        maxLengthForField: formFieldMaxLength,
        fileHint: formFieldFileHint,
        acceptValue: formFieldAcceptAttribute,
        onCheckboxToggle: data.onCheckboxToggle,
        isCheckboxSelected: data.isCheckboxSelected,
        onFileChange: upload.onFileChange,
        applyFileSelection: upload.applyFileSelection,
        clearFileUpload: upload.clearFileUpload,
        setAnswer: data.setAnswer,
        setTeamMemberEmails: data.setTeamMemberEmails,
        validateSection: validation.validateSection,
        filePreviewUrls: upload.filePreviewUrls,
        fileAnswerName: data.fileAnswerName,
        submit: submitter.submit,
        submitProgress: submitter.progress,
        cancelSubmit: submitter.cancel,
        fieldError: validation.fieldError,
        cardErrorsForFields: validation.cardErrorsForFields,
        cardErrorsForBundleSlot: validation.cardErrorsForBundleSlot,
    };
}

/** Return type of `useFormFillPage`; handed to fill-page field components so they never touch the form prop directly. */
export type TFormFillPageContext = ReturnType<typeof useFormFillPage>;
