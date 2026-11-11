import { describe, expect, it, vi } from 'vitest';
import { useUploadedFileState, type IUploadedFileResult, type TUploader } from '@/hooks/useUploadedFileState';

function makeFile(name = 'a.pdf', size = 2048, type = 'application/pdf'): File {
    return new File([new Uint8Array(size)], name, { type });
}

const OK_RESULT: IUploadedFileResult = {
    fileName: 'a.pdf',
    fileSize: 2048,
    mime: 'application/pdf',
    previewUrl: '',
    storedValue: 'storage/a.pdf',
};

describe('useUploadedFileState', () => {
    it('sukses: idle → uploading → completed dengan progress akhir 100', async () => {
        const upload: TUploader = async (_file, { onProgress }) => {
            onProgress(40);
            onProgress(100);

            return OK_RESULT;
        };
        const completed = vi.fn();
        const state = useUploadedFileState({ upload, onCompleted: completed });

        const promise = state.start(makeFile());

        expect(state.phase.value).toBe('uploading');

        await promise;

        expect(state.phase.value).toBe('completed');
        expect(state.progress.value).toBe(100);
        expect(state.fileName.value).toBe('a.pdf');
        expect(state.previewUrl.value).toBe('');
        expect(completed).toHaveBeenCalledWith(OK_RESULT);
    });

    it('gagal: error dengan pesan dari uploader', async () => {
        const state = useUploadedFileState({
            upload: async (): Promise<IUploadedFileResult> => {
                throw new Error('Ukuran melebihi batas server.');
            },
        });

        await state.start(makeFile());

        expect(state.phase.value).toBe('error');
        expect(state.errorMessage.value).toBe('Ukuran melebihi batas server.');
    });

    it('batal: AbortError mengembalikan state ke idle', async () => {
        const upload: TUploader = (_file, { signal }) =>
            new Promise((_resolve, reject) => {
                signal.addEventListener('abort', () => {
                    const error = new Error('aborted');
                    error.name = 'AbortError';
                    reject(error);
                });
            });
        const state = useUploadedFileState({ upload });
        const promise = state.start(makeFile());

        state.cancel();
        await promise;

        expect(state.phase.value).toBe('idle');
        expect(state.fileName.value).toBe('');
        expect(state.errorMessage.value).toBe('');
    });

    it('file diganti saat upload berjalan: hasil lama tidak menimpa state', async () => {
        const resolvers: Array<(value: IUploadedFileResult) => void> = [];
        const upload: TUploader = (file) =>
            new Promise<IUploadedFileResult>((resolve) => {
                resolvers.push(resolve);
                void file;
            });
        const state = useUploadedFileState({ upload });

        const first = state.start(makeFile('lama.pdf'));
        const second = state.start(makeFile('baru.pdf'));

        // Selesaikan upload lama setelah yang baru dimulai.
        resolvers[0]?.({ ...OK_RESULT, fileName: 'lama.pdf' });
        await Promise.resolve();
        await Promise.resolve();

        expect(state.fileName.value).toBe('baru.pdf');
        expect(state.phase.value).toBe('uploading');

        resolvers[1]?.({ ...OK_RESULT, fileName: 'baru.pdf' });
        await second;
        await first;

        expect(state.phase.value).toBe('completed');
        expect(state.fileName.value).toBe('baru.pdf');
    });

    it('file melebihi batas: validasi sebelum request dibuat', async () => {
        const upload = vi.fn<TUploader>();
        const state = useUploadedFileState({ upload, maxSizeKb: 1 });

        await state.start(makeFile('besar.pdf', 4096));

        expect(upload).not.toHaveBeenCalled();
        expect(state.phase.value).toBe('error');
        expect(state.errorMessage.value).toContain('melebihi batas');
    });

    it('reset menghapus state tanpa memanggil upload', () => {
        const upload = vi.fn<TUploader>();
        const state = useUploadedFileState({ upload });

        state.reset();

        expect(upload).not.toHaveBeenCalled();
        expect(state.phase.value).toBe('idle');
    });
});
