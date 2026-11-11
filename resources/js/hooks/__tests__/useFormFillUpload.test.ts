import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useForm } from '@inertiajs/vue3';
import { useFormFillUpload } from '@/hooks/useFormFillUpload';
import { validateUploadFile } from '@/lib/uploadValidation';
import type { TFormFillAnswerMap } from '@/types/form';

const lifecycle = vi.hoisted(() => ({
    beforeUnmount: [] as Array<() => void>,
}));

vi.mock('@/lib/uploadValidation', () => ({
    validateUploadFile: vi.fn(),
}));

vi.mock('vue', async (importOriginal) => {
    const actual = await importOriginal<typeof import('vue')>();

    return {
        ...actual,
        onBeforeUnmount: (callback: () => void): void => {
            lifecycle.beforeUnmount.push(callback);
        },
    };
});

const validateUploadFileMock = vi.mocked(validateUploadFile);

function createUpload(initial: TFormFillAnswerMap = { cv: null }) {
    const answerForm = useForm<TFormFillAnswerMap>(initial);
    const upload = useFormFillUpload(answerForm);

    return { answerForm, upload };
}

function makePdf(): File {
    return new File(['%PDF-1.4'], 'cv.pdf', { type: 'application/pdf' });
}

beforeEach(() => {
    lifecycle.beforeUnmount.length = 0;
});

afterEach(() => {
    vi.clearAllMocks();
});

describe('useFormFillUpload', () => {
    it('rejects an invalid file and keeps the previous answer', async () => {
        validateUploadFileMock.mockResolvedValue('Ukuran file maksimal 5 MB.');
        const { answerForm, upload } = createUpload();

        const accepted = await upload.applyFileSelection('cv', makePdf());

        expect(accepted).toBe(false);
        expect(answerForm.errors.cv).toBe('Ukuran file maksimal 5 MB.');
        expect(answerForm.cv).toBeNull();
        expect(upload.filePreviewUrls.cv).toBeUndefined();
    });

    it('stores a valid file and exposes a preview object URL', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { answerForm, upload } = createUpload();
        const file = makePdf();

        const accepted = await upload.applyFileSelection('cv', file);

        expect(accepted).toBe(true);
        expect(answerForm.cv).toBe(file);
        expect(upload.filePreviewUrls.cv?.startsWith('blob:')).toBe(true);
    });

    it('replaces the previous preview URL instead of leaking it', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { upload } = createUpload();
        const revokeSpy = vi.spyOn(URL, 'revokeObjectURL');

        await upload.applyFileSelection('cv', makePdf());
        const firstPreview = upload.filePreviewUrls.cv;
        await upload.applyFileSelection('cv', makePdf());

        expect(revokeSpy).toHaveBeenCalledWith(firstPreview);
        expect(upload.filePreviewUrls.cv).not.toBe(firstPreview);
    });

    it('clears the file answer and revokes its preview', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { answerForm, upload } = createUpload();
        const revokeSpy = vi.spyOn(URL, 'revokeObjectURL');

        await upload.applyFileSelection('cv', makePdf());
        const preview = upload.filePreviewUrls.cv;
        upload.clearFileUpload('cv');

        expect(answerForm.cv).toBeNull();
        expect(upload.filePreviewUrls.cv).toBeUndefined();
        expect(revokeSpy).toHaveBeenCalledWith(preview);
    });

    it('revokes every preview on unmount', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { upload } = createUpload();
        const revokeSpy = vi.spyOn(URL, 'revokeObjectURL');

        await upload.applyFileSelection('cv', makePdf());
        const preview = upload.filePreviewUrls.cv;

        lifecycle.beforeUnmount.splice(0).forEach((run: () => void): void => run());

        expect(revokeSpy).toHaveBeenCalledWith(preview);
        expect(upload.filePreviewUrls.cv).toBeUndefined();
    });
});
