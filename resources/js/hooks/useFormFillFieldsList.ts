import { computed, ref, watch, type ComputedRef, type Ref } from 'vue';
import type { InertiaForm } from '@inertiajs/vue3';
import { buildFormFillSegments, formFillSectionFields } from '@/lib/formFillSectionSegments';
import { formFieldAnswerKey, formFillBuilderType } from '@/lib/formFillFields';
import type { IFormBuilderSection } from '@/types/form-builder';
import type { IFormFieldParticipationSlot, TFormFillAnswerMap, TFormFillSegment } from '@/types/form';

export interface IUseFormFillFieldsListOptions {
    /** Getter, bukan array: sumbernya prop Inertia yang bisa diganti saat render ulang. */
    fields: () => readonly IFormField[];
    sections: () => readonly IFormBuilderSection[];
    registrationMode: string;
    answerForm: InertiaForm<TFormFillAnswerMap>;
    filePreviewUrls: Record<string, string>;
    multiFilePreviewUrls?: Record<string, readonly string[]>;
    validateSection: (fields: IFormField[]) => boolean;
    isBundleDuplicatable: (field: IFormField) => boolean;
    isBlocked: () => boolean;
}

export interface IUseFormFillFieldsList {
    currentSectionIndex: Ref<number>;
    currentSectionFields: ComputedRef<IFormField[]>;
    formSegments: ComputedRef<TFormFillSegment[]>;
    previousSection: () => void;
    nextSection: () => void;
    storageKeyFor: (field: IFormField, slot: IFormFieldParticipationSlot) => string;
    imageUploadFillReady: (field: IFormField, storageKey: string) => boolean;
    uploadLightboxOpen: Ref<boolean>;
    uploadLightboxSrc: Ref<string | null>;
    uploadLightboxTitle: Ref<string>;
    openUploadLightbox: (src: string | undefined, title: string) => void;
    submitConfirmOpen: Ref<boolean>;
    submitConfirmDescription: ComputedRef<string>;
    requestSubmitConfirm: () => void;
    closeSubmitConfirm: () => void;
}

/**
 * Section wizard, segment grouping, image lightbox, and submit-confirmation state for the
 * fill fields list. The component only renders the returned state and emits `submit`.
 */
export function useFormFillFieldsList(options: IUseFormFillFieldsListOptions): IUseFormFillFieldsList {
    const currentSectionIndex = ref(0);

    const currentSectionId = computed(() => options.sections()[currentSectionIndex.value]?.id ?? 'section-1');

    const currentSectionFields = computed(() =>
        formFillSectionFields(options.fields(), options.sections(), currentSectionId.value)
    );

    const formSegments = computed(() =>
        buildFormFillSegments(currentSectionFields.value, options.isBundleDuplicatable)
    );

    function previousSection(): void {
        currentSectionIndex.value = Math.max(0, currentSectionIndex.value - 1);
    }

    function nextSection(): void {
        if (!options.validateSection(currentSectionFields.value)) return;
        currentSectionIndex.value = Math.min(options.sections().length - 1, currentSectionIndex.value + 1);
    }

    function storageKeyFor(field: IFormField, slot: IFormFieldParticipationSlot): string {
        return formFieldAnswerKey(field, slot.slotIndex);
    }

    function imageUploadFillReady(field: IFormField, storageKey: string): boolean {
        return (
            formFillBuilderType(field) === 'image_upload' &&
            Boolean(options.answerForm[storageKey]) &&
            Boolean(options.filePreviewUrls[storageKey] || options.multiFilePreviewUrls?.[storageKey]?.length)
        );
    }

    const uploadLightboxOpen = ref(false);
    const uploadLightboxSrc = ref<string | null>(null);
    const uploadLightboxTitle = ref('');

    function openUploadLightbox(src: string | undefined, title: string): void {
        if (!src) return;
        uploadLightboxSrc.value = src;
        uploadLightboxTitle.value = title;
        uploadLightboxOpen.value = true;
    }

    watch(
        uploadLightboxOpen,
        (open) => {
            if (!open) {
                uploadLightboxSrc.value = null;
                uploadLightboxTitle.value = '';
            }
        },
        { flush: 'sync' }
    );

    const submitConfirmOpen = ref(false);

    const submitConfirmDescription = computed(() => {
        if (options.registrationMode === 'team') {
            return 'Your answers will be sent. Invitations will be emailed to the team members you listed where required.';
        }
        if (options.registrationMode === 'bundle') {
            return 'All bundled entries in this registration will be submitted together.';
        }
        return 'Please review your answers. After submitting, changes may not be possible.';
    });

    function requestSubmitConfirm(): void {
        if (options.isBlocked()) return;
        submitConfirmOpen.value = true;
    }

    function closeSubmitConfirm(): void {
        submitConfirmOpen.value = false;
    }

    return {
        currentSectionIndex,
        currentSectionFields,
        formSegments,
        previousSection,
        nextSection,
        storageKeyFor,
        imageUploadFillReady,
        uploadLightboxOpen,
        uploadLightboxSrc,
        uploadLightboxTitle,
        openUploadLightbox,
        submitConfirmOpen,
        submitConfirmDescription,
        requestSubmitConfirm,
        closeSubmitConfirm,
    };
}
