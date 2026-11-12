import { describe, expect, it, vi, beforeEach } from 'vitest';
import { downloadWithProgress } from '@/adapters/browser/downloadProgress';
import type { IDownloadProgress, TDownloadWithProgressResult } from '@/adapters/browser/downloadProgress';
import { useExportDownload } from '@/hooks/useExportDownload';

vi.mock('@/adapters/browser/downloadProgress', () => ({ downloadWithProgress: vi.fn() }));

const downloadMock = vi.mocked(downloadWithProgress);
const STREAM_RESULT: TDownloadWithProgressResult = {
    mode: 'stream',
    bytes: 1024,
    fileName: 'registrations-event-1.csv',
    indeterminate: false,
};

function abortError(): DOMException {
    return new DOMException('Unduhan dibatalkan.', 'AbortError');
}

describe('useExportDownload', () => {
    beforeEach(() => {
        downloadMock.mockReset();
    });

    it('daftar kosong tidak membuat request', async () => {
        const state = useExportDownload();

        await expect(state.start(['', '  '])).resolves.toBeNull();
        expect(downloadMock).not.toHaveBeenCalled();
        expect(state.phase.value).toBe('idle');
    });

    it('progres diketahui: persentase dari loaded/total dan selesai', async () => {
        downloadMock.mockImplementation(async (_url: string, options): Promise<TDownloadWithProgressResult> => {
            options?.onProgress?.({ loaded: 50, total: 100 });
            options?.onProgress?.({ loaded: 100, total: 100 });

            return STREAM_RESULT;
        });
        const state = useExportDownload();

        const summary = await state.start(['/export/registrations']);

        expect(summary).toEqual({ streamCount: 1, directCount: 0 });
        expect(state.phase.value).toBe('completed');
        expect(state.indeterminate.value).toBe(false);
        expect(state.progress.value).toBe(100);
        expect(state.loadedBytes.value).toBe(100);
    });

    it('tanpa Content-Length: indeterminate dengan byte terkirim', async () => {
        let reported: IDownloadProgress | null = null;

        downloadMock.mockImplementation(async (_url: string, options): Promise<TDownloadWithProgressResult> => {
            options?.onProgress?.({ loaded: 1536, total: null });
            reported = { loaded: 1536, total: null };

            return { mode: 'stream', bytes: 1536, fileName: 'attendance.xlsx', indeterminate: true };
        });
        const state = useExportDownload();

        const summary = await state.start(['/export/attendance']);

        expect(reported).toEqual({ loaded: 1536, total: null });
        expect(summary).toEqual({ streamCount: 1, directCount: 0 });
        expect(state.phase.value).toBe('completed');
        expect(state.indeterminate.value).toBe(true);
        expect(state.loadedBytes.value).toBe(1536);
    });

    it('fallback direct dihitung terpisah dari stream', async () => {
        downloadMock.mockResolvedValue({ mode: 'direct', fileName: 'laporan.csv' });
        const state = useExportDownload();

        const summary = await state.start(['/export/laporan']);

        expect(summary).toEqual({ streamCount: 0, directCount: 1 });
        expect(state.directCount.value).toBe(1);
        expect(state.phase.value).toBe('completed');
    });

    it('batal saat berjalan: AbortError mengembalikan state ke idle tanpa error', async () => {
        downloadMock.mockImplementation(
            (_url: string, options): Promise<TDownloadWithProgressResult> =>
                new Promise((_resolve, reject) => {
                    options?.signal?.addEventListener('abort', (): void => reject(abortError()), { once: true });
                })
        );
        const state = useExportDownload();

        const pending = state.start(['/export/lambat']);
        expect(state.phase.value).toBe('running');

        state.cancel();
        expect(state.phase.value).toBe('idle');

        await expect(pending).resolves.toBeNull();
        expect(state.errorMessage.value).toBe('');
    });

    it('gagal: phase error membawa pesan adapter', async () => {
        downloadMock.mockRejectedValue(new Error('Unduhan gagal (kode 500).'));
        const state = useExportDownload();

        await expect(state.start(['/export/gagal'])).resolves.toBeNull();
        expect(state.phase.value).toBe('error');
        expect(state.errorMessage.value).toBe('Unduhan gagal (kode 500).');
    });
});
