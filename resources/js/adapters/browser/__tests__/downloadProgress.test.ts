import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { downloadWithProgress } from '@/adapters/browser/downloadProgress';
import type { IDownloadProgress } from '@/adapters/browser/downloadProgress';

interface IFakeAnchor {
    href: string;
    download: string;
    rel: string;
    click: ReturnType<typeof vi.fn>;
    remove: ReturnType<typeof vi.fn>;
}

interface IFakeReader {
    read: ReturnType<typeof vi.fn>;
    releaseLock: ReturnType<typeof vi.fn>;
    cancel: ReturnType<typeof vi.fn>;
}

interface IBrowserSpies {
    anchor: IFakeAnchor;
    createObjectURL: ReturnType<typeof vi.fn>;
    revokeObjectURL: ReturnType<typeof vi.fn>;
    blobConstructor: ReturnType<typeof vi.fn>;
    fetchMock: ReturnType<typeof vi.fn>;
}

/** Stub browser minimal: anchor, object URL, Blob counter, dan fetch. */
function stubBrowser(): IBrowserSpies {
    const anchor: IFakeAnchor = {
        href: '',
        download: '',
        rel: '',
        click: vi.fn(),
        remove: vi.fn(),
    };
    const createObjectURL = vi.fn(() => 'blob:unduhan');
    const revokeObjectURL = vi.fn();
    const RealBlob = globalThis.Blob;
    const blobConstructor = vi.fn(class extends RealBlob {});
    const fetchMock = vi.fn();

    vi.stubGlobal('document', {
        createElement: vi.fn(() => anchor),
        body: { appendChild: vi.fn() },
    });
    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL });
    vi.stubGlobal('Blob', blobConstructor);
    vi.stubGlobal('fetch', fetchMock);

    return { anchor, createObjectURL, revokeObjectURL, blobConstructor, fetchMock };
}

/** Reader palsu yang mengembalikan urutan hasil `read()`. */
function createReader(steps: Array<() => Promise<{ done: boolean; value?: Uint8Array }>>): IFakeReader {
    const read = vi.fn();
    for (const step of steps) read.mockImplementationOnce(step);

    return { read, releaseLock: vi.fn(), cancel: vi.fn(() => Promise.resolve()) };
}

/** Respons palsu dengan body yang memakai reader tertentu. */
function createResponse(headers: Record<string, string>, reader: IFakeReader): Response {
    return {
        ok: true,
        status: 200,
        headers: new Headers(headers),
        body: { getReader: (): IFakeReader => reader },
    } as unknown as Response;
}

/** Satu chunk byte dengan ukuran tertentu. */
function chunk(size: number): Uint8Array {
    return new Uint8Array(size);
}

/** Reader yang mengirim dua chunk lalu selesai. */
function twoChunks(firstSize: number, secondSize: number): IFakeReader {
    return createReader([
        () => Promise.resolve({ done: false, value: chunk(firstSize) }),
        () => Promise.resolve({ done: false, value: chunk(secondSize) }),
        () => Promise.resolve({ done: true }),
    ]);
}

afterEach((): void => {
    vi.unstubAllGlobals();
});

describe('downloadWithProgress', () => {
    let spies: IBrowserSpies;

    beforeEach((): void => {
        spies = stubBrowser();
    });

    it('reports byte progress and saves the streamed blob', async () => {
        const reader = twoChunks(4, 4);
        spies.fetchMock.mockResolvedValue(
            createResponse({ 'content-length': '8', 'content-type': 'text/csv' }, reader)
        );
        const progress: IDownloadProgress[] = [];

        const result = await downloadWithProgress('/admin/export/laporan.csv?periode=1', {
            onProgress: (value): void => {
                progress.push(value);
            },
        });

        expect(progress).toEqual([
            { loaded: 4, total: 8 },
            { loaded: 8, total: 8 },
        ]);
        expect(result).toEqual({
            mode: 'stream',
            bytes: 8,
            fileName: 'laporan.csv',
            indeterminate: false,
        });
        expect(spies.blobConstructor).toHaveBeenCalledTimes(1);
        expect(spies.createObjectURL).toHaveBeenCalledTimes(1);
        expect(spies.revokeObjectURL).toHaveBeenCalledWith('blob:unduhan');
        expect(spies.anchor.download).toBe('laporan.csv');
        expect(spies.anchor.click).toHaveBeenCalledTimes(1);
        expect(reader.releaseLock).toHaveBeenCalledTimes(1);
    });

    it('uses an RFC 5987 filename* from Content-Disposition', async () => {
        const reader = twoChunks(4, 4);
        spies.fetchMock.mockResolvedValue(
            createResponse(
                {
                    'content-length': '8',
                    'content-disposition': "attachment; filename*=UTF-8''laporan%20pendaftaran.xlsx",
                },
                reader
            )
        );

        const result = await downloadWithProgress('/admin/export/export');

        expect(result).toMatchObject({ mode: 'stream', fileName: 'laporan pendaftaran.xlsx' });
        expect(spies.anchor.download).toBe('laporan pendaftaran.xlsx');
    });

    it('uses a quoted filename from Content-Disposition', async () => {
        const reader = twoChunks(4, 4);
        spies.fetchMock.mockResolvedValue(
            createResponse(
                { 'content-length': '8', 'content-disposition': 'attachment; filename="registrations-event-7.csv"' },
                reader
            )
        );

        const result = await downloadWithProgress('/admin/export/export');

        expect(result).toMatchObject({ fileName: 'registrations-event-7.csv' });
        expect(spies.anchor.download).toBe('registrations-event-7.csv');
    });

    it('uses an unquoted filename from Content-Disposition', async () => {
        const reader = twoChunks(4, 4);
        spies.fetchMock.mockResolvedValue(
            createResponse(
                { 'content-length': '8', 'content-disposition': 'attachment; filename=oprec-funnel-20261111.csv' },
                reader
            )
        );

        const result = await downloadWithProgress('/admin/export/funnel');

        expect(result).toMatchObject({ fileName: 'oprec-funnel-20261111.csv' });
    });

    it('falls back to the URL segment when Content-Disposition is absent', async () => {
        const reader = twoChunks(4, 4);
        spies.fetchMock.mockResolvedValue(createResponse({ 'content-length': '8' }, reader));

        const result = await downloadWithProgress('/admin/export/tanpa-header.csv');

        expect(result).toMatchObject({ fileName: 'tanpa-header.csv' });
    });

    it('sanitises path separators and control characters from the header name', async () => {
        const reader = twoChunks(4, 4);
        spies.fetchMock.mockResolvedValue(
            createResponse(
                {
                    'content-length': '8',
                    'content-disposition': 'attachment; filename="../../etc/ev\\il\u0007.xlsx"',
                },
                reader
            )
        );

        const result = await downloadWithProgress('/admin/export/export');

        expect(result.fileName).toBe('etcevil.xlsx');
        expect(spies.anchor.download).toBe('etcevil.xlsx');
    });

    it('carries the header name into the direct download fallback', async () => {
        const reader = createReader([]);
        spies.fetchMock.mockResolvedValue(
            createResponse(
                {
                    'content-length': String(50 * 1024 * 1024 + 1),
                    'content-disposition': "attachment; filename*=UTF-8''attendance-event-seminar-20261111-101500.xlsx",
                },
                reader
            )
        );

        const result = await downloadWithProgress('/admin/export/export');

        expect(result).toEqual({ mode: 'direct', fileName: 'attendance-event-seminar-20261111-101500.xlsx' });
        expect(spies.anchor.download).toBe('attendance-event-seminar-20261111-101500.xlsx');
        expect(spies.blobConstructor).not.toHaveBeenCalled();
        expect(reader.read).not.toHaveBeenCalled();
    });

    it('streams indeterminate progress when Content-Length is missing', async () => {
        const reader = twoChunks(4, 4);
        spies.fetchMock.mockResolvedValue(
            createResponse(
                { 'content-type': 'text/csv', 'content-disposition': 'attachment; filename=stream.csv' },
                reader
            )
        );
        const progress: IDownloadProgress[] = [];

        const result = await downloadWithProgress('/admin/export/stream', {
            onProgress: (value): void => {
                progress.push(value);
            },
        });

        expect(progress).toEqual([
            { loaded: 4, total: null },
            { loaded: 8, total: null },
        ]);
        expect(result).toEqual({ mode: 'stream', bytes: 8, fileName: 'stream.csv', indeterminate: true });
        expect(spies.blobConstructor).toHaveBeenCalledTimes(1);
        expect(spies.revokeObjectURL).toHaveBeenCalledWith('blob:unduhan');
    });

    it('falls back to direct when indeterminate bytes cross the safety cap', async () => {
        const reader = twoChunks(6, 6);
        spies.fetchMock.mockResolvedValue(
            createResponse({ 'content-disposition': 'attachment; filename=besar.csv' }, reader)
        );

        const result = await downloadWithProgress('/admin/export/besar', { maxBytes: 10 });

        expect(result).toEqual({ mode: 'direct', fileName: 'besar.csv' });
        expect(spies.blobConstructor).not.toHaveBeenCalled();
        expect(spies.createObjectURL).not.toHaveBeenCalled();
        expect(spies.anchor.click).toHaveBeenCalledTimes(1);
        expect(reader.cancel).toHaveBeenCalledTimes(1);
        expect(reader.releaseLock).toHaveBeenCalledTimes(1);
    });

    it('rejects with AbortError and keeps no payload when aborted mid-stream', async () => {
        const caller = new AbortController();
        const reader = createReader([
            () => Promise.resolve({ done: false, value: chunk(4) }),
            () => Promise.reject(new DOMException('aborted', 'AbortError')),
        ]);
        spies.fetchMock.mockResolvedValue(createResponse({ 'content-length': '8' }, reader));

        await expect(
            downloadWithProgress('/admin/export/batal.csv', {
                signal: caller.signal,
                onProgress: (): void => caller.abort(),
            })
        ).rejects.toMatchObject({ name: 'AbortError' });

        expect(spies.createObjectURL).not.toHaveBeenCalled();
        expect(spies.blobConstructor).not.toHaveBeenCalled();
        expect(reader.releaseLock).toHaveBeenCalledTimes(1);
    });

    it('rejects a response that is not ok', async () => {
        spies.fetchMock.mockResolvedValue({ ok: false, status: 500, headers: new Headers(), body: null });

        await expect(downloadWithProgress('/admin/export/gagal.csv')).rejects.toThrow('Unduhan gagal (kode 500).');
    });
});
