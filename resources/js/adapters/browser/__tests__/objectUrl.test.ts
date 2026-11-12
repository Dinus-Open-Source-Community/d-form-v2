import { afterEach, describe, expect, it, vi } from 'vitest';
import { createObjectUrl, revokeObjectUrl } from '@/adapters/browser/objectUrl';

/** Stub API object URL global + penanda browser (`readWindow` non-null). */
function stubObjectUrlApi(api: {
    createObjectURL: (file: Blob) => string;
    revokeObjectURL: (url: string) => void;
}): void {
    vi.stubGlobal('window', {});
    vi.stubGlobal('URL', api);
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('objectUrl adapter', () => {
    it('membuat object URL lewat URL.createObjectURL', () => {
        const create = vi.fn((): string => 'blob:https://dform.test/1');
        stubObjectUrlApi({ createObjectURL: create, revokeObjectURL: vi.fn() });

        const file = new Blob(['x'], { type: 'image/png' });

        expect(createObjectUrl(file)).toBe('blob:https://dform.test/1');
        expect(create).toHaveBeenCalledWith(file);
    });

    it('me-revoke hanya URL blob; nilai lain no-op', () => {
        const revoke = vi.fn();
        stubObjectUrlApi({ createObjectURL: vi.fn((): string => ''), revokeObjectURL: revoke });

        revokeObjectUrl('blob:https://dform.test/1');
        revokeObjectUrl('https://dform.test/storage/a.png');
        revokeObjectUrl('');
        revokeObjectUrl(null);
        revokeObjectUrl(undefined);

        expect(revoke).toHaveBeenCalledTimes(1);
        expect(revoke).toHaveBeenCalledWith('blob:https://dform.test/1');
    });

    it('no-op tanpa window (SSR/Node) dan saat API tidak tersedia', () => {
        vi.stubGlobal('window', undefined);

        expect(createObjectUrl(new Blob(['x']))).toBe('');
        expect(() => revokeObjectUrl('blob:https://dform.test/1')).not.toThrow();
    });

    it('tidak melempar saat API browser melempar', () => {
        stubObjectUrlApi({
            createObjectURL: () => {
                throw new Error('blocked');
            },
            revokeObjectURL: () => {
                throw new Error('blocked');
            },
        });

        expect(createObjectUrl(new Blob(['x']))).toBe('');
        expect(() => revokeObjectUrl('blob:https://dform.test/1')).not.toThrow();
    });
});
