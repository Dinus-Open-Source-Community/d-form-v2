import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { isRecord } from '@/adapters/http/httpError';
import { createPercentProgressReporter, uploadWithProgress } from '@/adapters/http/upload';
import { startJsonServer } from '@/adapters/http/__tests__/support/jsonServer';
import type { IJsonServerHandle } from '@/adapters/http/__tests__/support/jsonServer';
import type { ITransferProgress } from '@/types/http';

describe('upload adapter', () => {
    let server: IJsonServerHandle;

    beforeAll(async (): Promise<void> => {
        server = await startJsonServer((): { body: string } => ({ body: JSON.stringify({ ok: true }) }));
    });

    afterAll(async (): Promise<void> => {
        await server.close();
    });

    it('posts multipart FormData and returns the parsed payload', async () => {
        const formData = new FormData();
        formData.append('file', new Blob(['isi']), 'berkas.pdf');

        const result = await uploadWithProgress(
            { url: `${server.baseUrl}/upload`, formData, onProgress: (): void => undefined },
            (payload) => (isRecord(payload) && payload.ok === true ? true : null)
        );

        expect(result).toBe(true);
        expect(server.requests[0]?.method).toBe('POST');
        expect(server.requests[0]?.contentType).toContain('multipart/form-data');
    });

    it('maps byte progress to the 0..100 contract for hooks', () => {
        const percents: number[] = [];
        const reporter = createPercentProgressReporter((percent: number): void => {
            percents.push(percent);
        });

        expect(createPercentProgressReporter(undefined)).toBeUndefined();

        const progress: ITransferProgress = { loaded: 512, total: 2048, percent: 25 };
        reporter?.(progress);

        expect(percents).toEqual([25]);
    });
});
