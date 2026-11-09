import { afterEach, describe, expect, it, vi } from 'vitest';
import { readStorage, removeStorage, writeStorage } from '@/adapters/browser/storage';

/** Storage in-memory dengan bentuk API `Storage`. */
function createStorage(): Storage {
    const values = new Map<string, string>();

    return {
        get length(): number {
            return values.size;
        },
        clear: (): void => values.clear(),
        getItem: (key: string): string | null => values.get(key) ?? null,
        key: (index: number): string | null => Array.from(values.keys())[index] ?? null,
        removeItem: (key: string): void => {
            values.delete(key);
        },
        setItem: (key: string, value: string): void => {
            values.set(key, value);
        },
    };
}

afterEach((): void => {
    vi.unstubAllGlobals();
});

describe('storage adapter', () => {
    it('is a safe no-op without a browser window (SSR)', () => {
        expect(readStorage({ area: 'local', key: 'x' })).toBeNull();
        expect(writeStorage({ area: 'local', key: 'x', value: '1' })).toBe(false);
        expect(removeStorage({ area: 'local', key: 'x' })).toBe(false);
    });

    it('reads, writes, and removes values per area', () => {
        const local = createStorage();
        const session = createStorage();
        vi.stubGlobal('window', { localStorage: local, sessionStorage: session });

        expect(writeStorage({ area: 'local', key: 'theme', value: 'dark' })).toBe(true);
        expect(readStorage({ area: 'local', key: 'theme' })).toBe('dark');
        expect(readStorage({ area: 'session', key: 'theme' })).toBeNull();

        writeStorage({ area: 'session', key: 'desk', value: 'meja-1' });
        expect(readStorage({ area: 'session', key: 'desk' })).toBe('meja-1');

        expect(removeStorage({ area: 'local', key: 'theme' })).toBe(true);
        expect(readStorage({ area: 'local', key: 'theme' })).toBeNull();
    });

    it('falls back when the browser blocks storage access', () => {
        vi.stubGlobal('window', {
            get localStorage(): Storage {
                throw new Error('blocked');
            },
        });

        expect(readStorage({ area: 'local', key: 'x' })).toBeNull();
        expect(writeStorage({ area: 'local', key: 'x', value: '1' })).toBe(false);
        expect(removeStorage({ area: 'local', key: 'x' })).toBe(false);
    });

    it('falls back when setItem throws (quota or private mode)', () => {
        const blocked = createStorage();
        blocked.setItem = (): void => {
            throw new Error('quota');
        };
        vi.stubGlobal('window', { localStorage: blocked, sessionStorage: blocked });

        expect(writeStorage({ area: 'local', key: 'x', value: '1' })).toBe(false);
    });
});
