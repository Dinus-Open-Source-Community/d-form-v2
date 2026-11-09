import { createServer } from 'node:http';
import type { IncomingMessage, Server, ServerResponse } from 'node:http';

/** Satu respons JSON statis dari server uji lokal. */
export interface IJsonServerResponse {
    status?: number;
    body: string;
    headers?: Record<string, string>;
}

/** Request yang diterima server uji; dipakai assert method/body/konten. */
export interface IJsonServerRequest {
    method: string;
    path: string;
    body: string;
    contentType: string;
}

/** Handle server uji; `baseUrl` siap dipakai adapter sebagai URL absolut. */
export interface IJsonServerHandle {
    baseUrl: string;
    requests: IJsonServerRequest[];
    close: () => Promise<void>;
}

/** Baca body request sebagai string; `''` bila tidak ada body. */
function readBody(request: IncomingMessage): Promise<string> {
    return new Promise((resolve): void => {
        const chunks: Buffer[] = [];
        request.on('data', (chunk: Buffer): void => {
            chunks.push(chunk);
        });
        request.on('end', (): void => {
            resolve(Buffer.concat(chunks).toString('utf8'));
        });
    });
}

/**
 * Jalankan server HTTP lokal yang membalas satu respons per request. Dipakai
 * adapter test agar kontrak benar-benar diuji lewat transport, bukan mock axios.
 */
export function startJsonServer(
    handler: (url: string) => IJsonServerResponse,
): Promise<IJsonServerHandle> {
    const requests: IJsonServerRequest[] = [];
    const server: Server = createServer((request: IncomingMessage, response: ServerResponse): void => {
        void readBody(request).then((body): void => {
            const rawUrl = request.url ?? '/';
            const path = new URL(rawUrl, 'http://127.0.0.1').pathname;
            requests.push({
                method: request.method ?? 'GET',
                path,
                body,
                contentType: String(request.headers['content-type'] ?? ''),
            });

            const result = handler(rawUrl);
            response.writeHead(result.status ?? 200, {
                'Content-Type': 'application/json',
                ...result.headers,
            });
            response.end(result.body);
        });
    });

    return new Promise((resolve): void => {
        server.listen(0, '127.0.0.1', (): void => {
            const address = server.address();
            const port = typeof address === 'object' && address !== null ? address.port : 0;

            resolve({
                baseUrl: `http://127.0.0.1:${port}`,
                requests,
                close: (): Promise<void> =>
                    new Promise((done): void => {
                        server.close((): void => done());
                    }),
            });
        });
    });
}
