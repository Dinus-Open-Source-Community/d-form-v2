import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useForm } from '@inertiajs/vue3';
import { useFormFillUpload } from '@/hooks/useFormFillUpload';
import { validateUploadFile } from '@/lib/uploadValidation';
import type { TFormFillAnswerMap } from '@/types/form';

const lifecycle = vi.hoisted(() => ({
    beforeUnmount: [] as Array<() => void>,
}));

vi.mock('@/lib/uploadValidation', () => ({
    DEFAULT_MULTIPLE_UPLOAD_MAX_FILES: 5,
    DEFAULT_MULTIPLE_UPLOAD_MAX_TOTAL_SIZE_KB: 20 * 1024,
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

    it('keeps multiple selections in order and writes them as an array', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { answerForm, upload } = createUpload();
        const first = new File(['%PDF-1.4'], 'first.pdf', { type: 'application/pdf' });
        const second = new File(['%PDF-1.4'], 'second.pdf', { type: 'application/pdf' });

        const accepted = await upload.applyFileSelection('cv', [first, second], { multiple: true });

        expect(accepted).toBe(true);
        expect(answerForm.cv).toEqual([first, second]);
        expect(upload.multiFilePreviewUrls.cv).toHaveLength(2);
        expect(upload.multiFilePreviewUrls.cv?.[0]).toMatch(/^blob:/);
    });

    it('removes one selected file while preserving the order of the rest', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { answerForm, upload } = createUpload();
        const first = new File(['a'], 'first.pdf', { type: 'application/pdf' });
        const second = new File(['b'], 'second.pdf', { type: 'application/pdf' });
        const third = new File(['c'], 'third.pdf', { type: 'application/pdf' });
        const revokeSpy = vi.spyOn(URL, 'revokeObjectURL');

        await upload.applyFileSelection('cv', [first, second, third], { multiple: true });
        const removedUrl = upload.multiFilePreviewUrls.cv?.[1];
        upload.removeFileUpload('cv', 1);

        expect(answerForm.cv).toEqual([first, third]);
        expect(upload.multiFilePreviewUrls.cv).toHaveLength(2);
        expect(revokeSpy).toHaveBeenCalledWith(removedUrl);
    });

    it('does not carry a removed row error onto the healthy row', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { answerForm, upload } = createUpload();
        const failedFile = new File(['failed'], 'failed.pdf', { type: 'application/pdf' });
        const healthyFile = new File(['healthy'], 'healthy.pdf', { type: 'application/pdf' });

        await upload.applyFileSelection('cv', [failedFile, healthyFile], { multiple: true });
        answerForm.setError('cv.0', 'Berkas gagal divalidasi.');
        upload.removeFileUpload('cv', 0);

        expect(answerForm.cv).toEqual([healthyFile]);
        expect(answerForm.errors['cv.0']).toBeUndefined();
        expect(answerForm.errors['cv.1']).toBeUndefined();
    });

    it('remaps a retained row error when an earlier healthy row is removed', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { answerForm, upload } = createUpload();
        const healthyFile = new File(['healthy'], 'healthy.pdf', { type: 'application/pdf' });
        const failedFile = new File(['failed'], 'failed.pdf', { type: 'application/pdf' });

        await upload.applyFileSelection('cv', [healthyFile, failedFile], { multiple: true });
        answerForm.setError('cv.1', 'Berkas gagal divalidasi.');
        upload.removeFileUpload('cv', 0);

        expect(answerForm.cv).toEqual([failedFile]);
        expect(answerForm.errors['cv.0']).toBe('Berkas gagal divalidasi.');
        expect(answerForm.errors['cv.1']).toBeUndefined();
    });

    it('clears indexed errors when a successful selection replaces the rows', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { answerForm, upload } = createUpload();
        const previousFile = new File(['previous'], 'previous.pdf', { type: 'application/pdf' });
        const replacementFile = new File(['replacement'], 'replacement.pdf', { type: 'application/pdf' });

        await upload.applyFileSelection('cv', [previousFile], { multiple: true });
        answerForm.setError('cv.0', 'Berkas gagal divalidasi.');
        await upload.applyFileSelection('cv', [replacementFile], { multiple: true });

        expect(answerForm.errors['cv.0']).toBeUndefined();
    });

    it('keeps the same preview URL for files retained in an updated selection', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { upload } = createUpload();
        const first = new File(['a'], 'first.pdf', { type: 'application/pdf' });
        const second = new File(['b'], 'second.pdf', { type: 'application/pdf' });

        await upload.applyFileSelection('cv', [first], { multiple: true });
        const firstPreview = upload.multiFilePreviewUrls.cv?.[0];
        await upload.applyFileSelection('cv', [first, second], { multiple: true });

        expect(upload.multiFilePreviewUrls.cv?.[0]).toBe(firstPreview);
    });

    it('rejects over-limit selections and passes the field allowlist to validation', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { answerForm, upload } = createUpload();
        const files = Array.from({ length: 6 }, (_value, index) => new File(['a'], `${index}.pdf`));

        const accepted = await upload.applyFileSelection('cv', files, {
            multiple: true,
            allowedFormats: ['pdf'],
        });

        expect(accepted).toBe(false);
        expect(answerForm.cv).toBeNull();
        expect(answerForm.errors.cv).toBe('Pilih maksimal 5 berkas.');
        expect(validateUploadFileMock).not.toHaveBeenCalled();
    });

    it('rejects a multiple selection above the aggregate 20 MB limit', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { answerForm, upload } = createUpload();
        const files = Array.from(
            { length: 3 },
            (_value, index) => new File([new Uint8Array(7 * 1024 * 1024)], `${index}.pdf`)
        );

        const accepted = await upload.applyFileSelection('cv', files, { multiple: true });

        expect(accepted).toBe(false);
        expect(answerForm.errors.cv).toBe('Ukuran total berkas maksimal 20 MB.');
        expect(validateUploadFileMock).not.toHaveBeenCalled();
    });

    it('enforces the field-specific file count and aggregate size limits', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { answerForm, upload } = createUpload();
        const files = [new File(['a'], 'one.pdf'), new File(['b'], 'two.pdf'), new File(['c'], 'three.pdf')];

        const countAccepted = await upload.applyFileSelection('cv', files, {
            multiple: true,
            maxFiles: 2,
            maxTotalSizeKb: 1024,
        });

        expect(countAccepted).toBe(false);
        expect(answerForm.errors.cv).toBe('Pilih maksimal 2 berkas.');
        expect(validateUploadFileMock).not.toHaveBeenCalled();

        const totalSizeFiles = [
            new File([new Uint8Array(600 * 1024)], 'one.pdf'),
            new File([new Uint8Array(600 * 1024)], 'two.pdf'),
        ];
        const totalAccepted = await upload.applyFileSelection('cv', totalSizeFiles, {
            multiple: true,
            maxFiles: 3,
            maxTotalSizeKb: 1024,
        });

        expect(totalAccepted).toBe(false);
        expect(answerForm.errors.cv).toBe('Ukuran total berkas maksimal 1,0 MB.');
        expect(validateUploadFileMock).not.toHaveBeenCalled();
    });

    it('rejects a file when the field allowlist excludes its format', async () => {
        validateUploadFileMock.mockResolvedValue('Format file tidak didukung.');
        const { answerForm, upload } = createUpload();

        const file = makePdf();
        const accepted = await upload.applyFileSelection('cv', file, {
            allowedFormats: ['png'],
        });

        expect(accepted).toBe(false);
        expect(validateUploadFileMock).toHaveBeenCalledWith(file, ['png']);
        expect(answerForm.errors.cv).toBe('Format file tidak didukung.');
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

    it('revokes every multiple-file preview on unmount', async () => {
        validateUploadFileMock.mockResolvedValue(null);
        const { upload } = createUpload();
        const revokeSpy = vi.spyOn(URL, 'revokeObjectURL');

        await upload.applyFileSelection('cv', [makePdf(), makePdf()], { multiple: true });
        const previews = [...(upload.multiFilePreviewUrls.cv ?? [])];
        lifecycle.beforeUnmount.splice(0).forEach((run: () => void): void => run());

        expect(revokeSpy).toHaveBeenCalledWith(previews[0]);
        expect(revokeSpy).toHaveBeenCalledWith(previews[1]);
        expect(upload.multiFilePreviewUrls.cv).toBeUndefined();
    });
});
