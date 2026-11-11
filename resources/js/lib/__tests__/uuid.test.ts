import { afterEach, describe, expect, it, vi } from 'vitest';
import { createShortId, createUuid } from '@/lib/uuid';

const UUID_V4_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

interface IRandomValuesSource {
    getRandomValues: (array: Uint8Array) => Uint8Array;
}

function stubCrypto(value: unknown): void {
    vi.stubGlobal('crypto', value);
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('createUuid', () => {
    it('memakai randomUUID saat tersedia dan mengembalikan UUID v4', () => {
        expect(createUuid()).toMatch(UUID_V4_PATTERN);
    });

    it('tetap menghasilkan UUID v4 dari getRandomValues saat randomUUID tidak ada', () => {
        const realCrypto = globalThis.crypto;
        const source: IRandomValuesSource = {
            getRandomValues: (array: Uint8Array): Uint8Array => realCrypto.getRandomValues(array),
        };

        stubCrypto(source);

        expect(typeof globalThis.crypto.randomUUID).toBe('undefined');
        expect(createUuid()).toMatch(UUID_V4_PATTERN);
    });

    it('memakai fallback timestamp+counter tanpa melempar saat keduanya tidak ada', () => {
        stubCrypto({});

        const first = createUuid();
        const second = createUuid();

        expect(first).toMatch(UUID_V4_PATTERN);
        expect(second).toMatch(UUID_V4_PATTERN);
        expect(first).not.toBe(second);
    });

    it('seribu pemanggilan menghasilkan id unik', () => {
        const ids = new Set<string>();

        for (let i = 0; i < 1000; i += 1) {
            ids.add(createUuid());
        }

        expect(ids.size).toBe(1000);
    });
});

describe('createShortId', () => {
    it('mengikuti panjang yang diminta dan hanya memakai alfabet heksadesimal', () => {
        expect(createShortId(12)).toMatch(/^[0-9a-f]{12}$/);
        expect(createShortId(8)).toMatch(/^[0-9a-f]{8}$/);
        expect(createShortId(1)).toMatch(/^[0-9a-f]$/);
    });

    it('mengembalikan string kosong untuk panjang tidak valid', () => {
        expect(createShortId(0)).toBe('');
        expect(createShortId(-3)).toBe('');
        expect(createShortId(2.5)).toBe('');
    });

    it('tetap berjalan saat hanya randomUUID yang tersedia', () => {
        stubCrypto({ randomUUID: (): string => '12345678-1234-4123-8123-123456789abc' });

        expect(createShortId(12)).toBe('123456781234');
    });
});
