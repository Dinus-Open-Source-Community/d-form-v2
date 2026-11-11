import { afterEach, describe, expect, it, vi } from 'vitest';
import { readCookie } from '@/adapters/browser/cookies';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('readCookie', () => {
    it.each([
        ['XSRF-TOKEN=abc%3D; session=xyz', 'XSRF-TOKEN', 'abc%3D'],
        [' XSRF-TOKEN = token ; other=1', 'XSRF-TOKEN', 'token'],
        ['other=1', 'XSRF-TOKEN', ''],
        ['', 'XSRF-TOKEN', ''],
    ])('membaca %j dari cookie jar %j', (cookie: string, name: string, expected: string) => {
        vi.stubGlobal('document', { cookie });

        expect(readCookie(name)).toBe(expected);
    });

    it('string kosong saat document tidak tersedia (SSR/Node)', () => {
        vi.stubGlobal('document', undefined);

        expect(readCookie('XSRF-TOKEN')).toBe('');
    });
});
