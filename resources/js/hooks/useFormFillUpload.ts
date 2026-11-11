import { onBeforeUnmount, reactive } from 'vue';
import type { InertiaForm } from '@inertiajs/vue3';
import { validateUploadFile } from '@/lib/uploadValidation';
import type { TFormFillAnswerMap } from '@/types/form';

export interface IUseFormFillUpload {
    /** Object URLs for local file previews; revoked on clear/unmount. */
    filePreviewUrls: Record<string, string>;
    onFileChange: (fieldName: string, event: Event) => Promise<void>;
    applyFileSelection: (fieldName: string, file: File | null) => Promise<boolean>;
    clearFileUpload: (fieldName: string) => void;
}

/** Narrows a change event target to a file input without a type assertion. */
function isFileInputTarget(target: EventTarget | null): target is HTMLInputElement {
    return typeof target === 'object' && target !== null && 'files' in target;
}

/**
 * File upload state for the fill page: validates the selection, keeps a preview object URL,
 * and writes the file into the Inertia form. Preview URLs are revoked on clear and unmount.
 */
export function useFormFillUpload(answerForm: InertiaForm<TFormFillAnswerMap>): IUseFormFillUpload {
    const filePreviewUrls = reactive<Record<string, string>>({});

    function revokeFilePreview(fieldName: string): void {
        const url = filePreviewUrls[fieldName];
        if (url) {
            URL.revokeObjectURL(url);
            delete filePreviewUrls[fieldName];
        }
    }

    /**
     * Applies a selected file: invalid files only set a field error, valid files replace the
     * preview URL and the answer. Returns false when validation rejected the file.
     */
    async function applyFileSelection(fieldName: string, file: File | null): Promise<boolean> {
        if (file) {
            const message = await validateUploadFile(file);
            if (message) {
                answerForm.setError(fieldName, message);
                return false;
            }
        }

        revokeFilePreview(fieldName);
        if (file) {
            filePreviewUrls[fieldName] = URL.createObjectURL(file);
        }
        answerForm[fieldName] = file;
        return true;
    }

    async function onFileChange(fieldName: string, event: Event): Promise<void> {
        const target = event.target;
        if (!isFileInputTarget(target)) return;

        const accepted = await applyFileSelection(fieldName, target.files?.[0] ?? null);
        if (!accepted) target.value = '';
    }

    function clearFileUpload(fieldName: string): void {
        revokeFilePreview(fieldName);
        answerForm[fieldName] = null;
        answerForm.clearErrors(fieldName);
    }

    onBeforeUnmount(() => {
        for (const key of Object.keys(filePreviewUrls)) {
            revokeFilePreview(key);
        }
    });

    return { filePreviewUrls, onFileChange, applyFileSelection, clearFileUpload };
}
