import { afterEach, describe, expect, it, vi } from 'vitest';
import { copyTextToClipboard } from '@/adapters/browser/clipboard';

afterEach((): void => {
    vi.unstubAllGlobals();
});

describe('clipboard adapter', () => {
    it('returns false without a browser window (SSR)', async () => {
        await expect(copyTextToClipboard('abc')).resolves.toBe(false);
    });

    it('returns false in an insecure context', async () => {
        const writeText = vi.fn();
        vi.stubGlobal('window', { isSecureContext: false });
        vi.stubGlobal('navigator', { clipboard: { writeText } });

        await expect(copyTextToClipboard('abc')).resolves.toBe(false);
        expect(writeText).not.toHaveBeenCalled();
    });

    it('returns false when the clipboard API is unavailable', async () => {
        vi.stubGlobal('window', { isSecureContext: true });
        vi.stubGlobal('navigator', {});

        await expect(copyTextToClipboard('abc')).resolves.toBe(false);
    });

    it('writes text in a secure context', async () => {
        const writeText = vi.fn(() => Promise.resolve());
        vi.stubGlobal('window', { isSecureContext: true });
        vi.stubGlobal('navigator', { clipboard: { writeText } });

        await expect(copyTextToClipboard('nomor-1')).resolves.toBe(true);
        expect(writeText).toHaveBeenCalledWith('nomor-1');
    });

    it('swallows a browser rejection and reports failure', async () => {
        const writeText = vi.fn(() => Promise.reject(new Error('denied')));
        vi.stubGlobal('window', { isSecureContext: true });
        vi.stubGlobal('navigator', { clipboard: { writeText } });

        await expect(copyTextToClipboard('abc')).resolves.toBe(false);
    });
});
