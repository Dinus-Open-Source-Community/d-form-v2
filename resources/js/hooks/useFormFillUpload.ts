import { onBeforeUnmount, reactive } from 'vue';
import type { InertiaForm } from '@inertiajs/vue3';
import { formatFileSize } from '@/lib/fileDisplay';
import {
    DEFAULT_MULTIPLE_UPLOAD_MAX_FILES,
    DEFAULT_MULTIPLE_UPLOAD_MAX_TOTAL_SIZE_KB,
    validateUploadFile,
} from '@/lib/uploadValidation';
import type { TFormFillAnswerMap } from '@/types/form';

export interface IFormFillUploadOptions {
    multiple?: boolean;
    allowedFormats?: readonly string[];
    maxFiles?: number;
    maxTotalSizeKb?: number;
}

export interface IUseFormFillUpload {
    /** Single-file preview URLs; retained for existing fill-page consumers. */
    filePreviewUrls: Record<string, string>;
    /** Multi-file previews in the same order as their answer files. */
    multiFilePreviewUrls: Record<string, string[]>;
    onFileChange: (fieldName: string, event: Event, options?: IFormFillUploadOptions) => Promise<void>;
    applyFileSelection: (
        fieldName: string,
        selection: File | readonly File[] | null,
        options?: IFormFillUploadOptions
    ) => Promise<boolean>;
    removeFileUpload: (fieldName: string, index: number) => void;
    clearFileUpload: (fieldName: string) => void;
}

/** Narrows a change event target to a file input without a type assertion. */
function isFileInputTarget(target: EventTarget | null): target is HTMLInputElement {
    return typeof target === 'object' && target !== null && 'files' in target;
}

function resolvePositiveInteger(value: number | undefined, fallback: number): number {
    if (value === undefined || !Number.isFinite(value) || value <= 0) return fallback;

    return Math.max(1, Math.floor(value));
}

function resolvePositiveNumber(value: number | undefined, fallback: number): number {
    return value !== undefined && Number.isFinite(value) && value > 0 ? value : fallback;
}

/**
 * Manages fill-page file answers and previews. Multiple answers remain ordered and are stored as
 * `File[]`; every object URL is revoked when its file is removed, replaced, cleared, or unmounted.
 */
export function useFormFillUpload(answerForm: InertiaForm<TFormFillAnswerMap>): IUseFormFillUpload {
    const filePreviewUrls = reactive<Record<string, string>>({});
    const multiFilePreviewUrls = reactive<Record<string, string[]>>({});
    const previewUrlsByField = new Map<string, string[]>();

    /** Removes stale indexed errors and carries only retained files' messages to their new indices. */
    function remapIndexedErrors(fieldName: string, previousFiles: readonly File[], nextFiles: readonly File[]): void {
        const prefix = `${fieldName}.`;
        const indexedErrors = Object.entries(answerForm.errors).flatMap(([key, message]) => {
            if (!key.startsWith(prefix)) return [];

            const indexText = key.slice(prefix.length);
            if (!/^\d+$/.test(indexText) || typeof message !== 'string') return [];

            return [{ key, index: Number(indexText), message }];
        });
        if (indexedErrors.length === 0) return;

        answerForm.clearErrors(...indexedErrors.map(({ key }) => key));
        const retainedIndexes = new Set<number>();

        nextFiles.forEach((file, nextIndex) => {
            const previousIndex = previousFiles.findIndex(
                (previousFile, index) => previousFile === file && !retainedIndexes.has(index)
            );
            if (previousIndex < 0) return;

            retainedIndexes.add(previousIndex);
            const message = indexedErrors.find((error) => error.index === previousIndex)?.message;
            if (message) answerForm.setError(`${fieldName}.${nextIndex}`, message);
        });
    }

    function revokePreviews(fieldName: string): void {
        const urls = previewUrlsByField.get(fieldName) ?? [];
        for (const url of urls) URL.revokeObjectURL(url);
        previewUrlsByField.delete(fieldName);
        delete filePreviewUrls[fieldName];
        delete multiFilePreviewUrls[fieldName];
    }

    function syncPreviews(fieldName: string, files: readonly File[], multiple: boolean): void {
        const currentAnswer = answerForm[fieldName];
        const previousFiles =
            typeof File !== 'undefined'
                ? Array.isArray(currentAnswer)
                    ? currentAnswer.filter((value): value is File => value instanceof File)
                    : currentAnswer instanceof File
                      ? [currentAnswer]
                      : []
                : [];
        const previousUrls = previewUrlsByField.get(fieldName) ?? [];
        const retainedIndexes = new Set<number>();
        const urls = files.map((file) => {
            const previousIndex = previousFiles.findIndex(
                (previousFile, index) => previousFile === file && !retainedIndexes.has(index)
            );
            const previousUrl = previousIndex >= 0 ? previousUrls[previousIndex] : undefined;
            if (previousIndex >= 0) retainedIndexes.add(previousIndex);
            return previousUrl ?? URL.createObjectURL(file);
        });

        previousUrls.forEach((url, index) => {
            if (!retainedIndexes.has(index)) URL.revokeObjectURL(url);
        });
        if (urls.length > 0) previewUrlsByField.set(fieldName, urls);
        else previewUrlsByField.delete(fieldName);

        delete filePreviewUrls[fieldName];
        delete multiFilePreviewUrls[fieldName];
        if (multiple) {
            if (urls.length > 0) multiFilePreviewUrls[fieldName] = urls;
        } else {
            const firstUrl = urls[0];
            if (firstUrl) filePreviewUrls[fieldName] = firstUrl;
        }
    }

    /** Validates a whole selection before replacing the current answer and its previews. */
    async function applyFileSelection(
        fieldName: string,
        selection: File | readonly File[] | null,
        options: IFormFillUploadOptions = {}
    ): Promise<boolean> {
        const multiple = options.multiple === true || Array.isArray(selection);
        const files = selection === null ? [] : Array.isArray(selection) ? [...selection] : [selection];
        const maxFiles = resolvePositiveInteger(options.maxFiles, DEFAULT_MULTIPLE_UPLOAD_MAX_FILES);
        const maxTotalSizeKb = resolvePositiveNumber(options.maxTotalSizeKb, DEFAULT_MULTIPLE_UPLOAD_MAX_TOTAL_SIZE_KB);

        if (multiple && files.length > maxFiles) {
            answerForm.setError(fieldName, `Pilih maksimal ${maxFiles} berkas.`);
            return false;
        }
        if (multiple && files.reduce((total, file) => total + file.size, 0) > maxTotalSizeKb * 1024) {
            answerForm.setError(fieldName, `Ukuran total berkas maksimal ${formatFileSize(maxTotalSizeKb * 1024)}.`);
            return false;
        }

        for (const file of files) {
            const message = await validateUploadFile(file, options.allowedFormats);
            if (message) {
                answerForm.setError(fieldName, message);
                return false;
            }
        }

        const previousValue = answerForm[fieldName];
        const previousFiles = Array.isArray(previousValue)
            ? previousValue.filter((value): value is File => typeof File !== 'undefined' && value instanceof File)
            : typeof File !== 'undefined' && previousValue instanceof File
              ? [previousValue]
              : [];
        remapIndexedErrors(fieldName, previousFiles, files);
        syncPreviews(fieldName, files, multiple);
        answerForm[fieldName] = multiple ? files : (files[0] ?? null);
        return true;
    }

    async function onFileChange(fieldName: string, event: Event, options: IFormFillUploadOptions = {}): Promise<void> {
        const target = event.target;
        if (!isFileInputTarget(target)) return;

        const files = Array.from(target.files ?? []);
        const selection = options.multiple ? files : (files[0] ?? null);
        const accepted = await applyFileSelection(fieldName, selection, options);
        if (!accepted) target.value = '';
    }

    function removeFileUpload(fieldName: string, index: number): void {
        const current = answerForm[fieldName];
        if (
            !Array.isArray(current) ||
            typeof File === 'undefined' ||
            !current.every((value) => value instanceof File)
        ) {
            return;
        }

        const files = current;
        if (index < 0 || index >= files.length) return;

        const urls = previewUrlsByField.get(fieldName) ?? [];
        const removedUrl = urls[index];
        if (removedUrl) URL.revokeObjectURL(removedUrl);
        const remainingUrls = urls.filter((_url, currentIndex) => currentIndex !== index);
        previewUrlsByField.set(fieldName, remainingUrls);
        if (remainingUrls.length > 0) {
            multiFilePreviewUrls[fieldName] = remainingUrls;
        } else {
            previewUrlsByField.delete(fieldName);
            delete multiFilePreviewUrls[fieldName];
        }

        const remainingFiles = files.filter((_file, currentIndex) => currentIndex !== index);
        remapIndexedErrors(fieldName, files, remainingFiles);
        answerForm[fieldName] = remainingFiles;
        answerForm.clearErrors(fieldName);
    }

    function clearFileUpload(fieldName: string): void {
        revokePreviews(fieldName);
        const currentValue = answerForm[fieldName];
        const files = Array.isArray(currentValue)
            ? currentValue.filter((value): value is File => typeof File !== 'undefined' && value instanceof File)
            : typeof File !== 'undefined' && currentValue instanceof File
              ? [currentValue]
              : [];
        remapIndexedErrors(fieldName, files, []);
        answerForm[fieldName] = null;
        answerForm.clearErrors(fieldName);
    }

    onBeforeUnmount(() => {
        for (const fieldName of previewUrlsByField.keys()) revokePreviews(fieldName);
    });

    return {
        filePreviewUrls,
        multiFilePreviewUrls,
        onFileChange,
        applyFileSelection,
        removeFileUpload,
        clearFileUpload,
    };
}
