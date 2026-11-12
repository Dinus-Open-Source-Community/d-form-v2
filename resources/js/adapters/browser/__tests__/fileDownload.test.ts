import { afterEach, describe, expect, it, vi } from 'vitest';
import { downloadUrl, openUrlInNewTab, saveBlobAsFile } from '@/adapters/browser/fileDownload';
import { playScanFeedback, vibrate } from '@/adapters/browser/feedback';

interface IFakeAnchor {
    href: string;
    download: string;
    rel: string;
    target: string;
    click: ReturnType<typeof vi.fn>;
    remove: ReturnType<typeof vi.fn>;
}

/** Dokumen palsu dengan anchor yang mencatat pemakaian. */
function createDocument(): { anchor: IFakeAnchor; body: { appendChild: ReturnType<typeof vi.fn> } } {
    const anchor: IFakeAnchor = {
        href: '',
        download: '',
        rel: '',
        target: '',
        click: vi.fn(),
        remove: vi.fn(),
    };

    return {
        anchor,
        body: { appendChild: vi.fn() },
    };
}

afterEach((): void => {
    vi.unstubAllGlobals();
});

describe('fileDownload adapter', () => {
    it('is a safe no-op without browser globals (SSR)', () => {
        expect(saveBlobAsFile(new Blob(['x']), 'x.txt')).toBe(false);
        expect(openUrlInNewTab('/x')).toBe(false);
    });

    it('rejects javascript/data urls', () => {
        const fakeDocument = createDocument();
        const createElement = vi.fn(() => fakeDocument.anchor);
        vi.stubGlobal('document', { createElement, body: fakeDocument.body });

        expect(openUrlInNewTab('javascript:alert(1)')).toBe(false);
        expect(downloadUrl('data:text/html,x')).toBe(false);
        expect(downloadUrl('javascript:alert(1)')).toBe(false);
        expect(createElement).not.toHaveBeenCalled();
    });

    it('downloads a blob through a temporary anchor', () => {
        const fakeDocument = createDocument();
        const createObjectURL = vi.fn(() => 'blob:temporary');
        const revokeObjectURL = vi.fn();

        const documentRef = {
            createElement: vi.fn(() => fakeDocument.anchor),
            body: fakeDocument.body,
        };
        vi.stubGlobal('document', documentRef);
        vi.stubGlobal('URL', { createObjectURL, revokeObjectURL });
        vi.useFakeTimers();

        try {
            expect(saveBlobAsFile(new Blob(['isi']), 'berkas.txt')).toBe(true);
            vi.runAllTimers();
        } finally {
            vi.useRealTimers();
        }

        expect(createObjectURL).toHaveBeenCalledTimes(1);
        expect(fakeDocument.anchor.href).toBe('blob:temporary');
        expect(fakeDocument.anchor.download).toBe('berkas.txt');
        expect(fakeDocument.anchor.rel).toBe('noopener');
        expect(fakeDocument.body.appendChild).toHaveBeenCalledWith(fakeDocument.anchor);
        expect(fakeDocument.anchor.click).toHaveBeenCalledTimes(1);
        expect(fakeDocument.anchor.remove).toHaveBeenCalledTimes(1);
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:temporary');
    });

    it('opens a url in a new tab with a safe rel', () => {
        const fakeDocument = createDocument();
        vi.stubGlobal('document', {
            createElement: vi.fn(() => fakeDocument.anchor),
            body: fakeDocument.body,
        });

        expect(openUrlInNewTab('/lampiran.pdf', 'lampiran.pdf')).toBe(true);

        expect(fakeDocument.anchor.href).toBe('/lampiran.pdf');
        expect(fakeDocument.anchor.target).toBe('_blank');
        expect(fakeDocument.anchor.rel).toBe('noopener noreferrer');
        expect(fakeDocument.anchor.download).toBe('lampiran.pdf');
        expect(fakeDocument.anchor.click).toHaveBeenCalledTimes(1);
    });

    it('triggers a direct url download without opening a tab', () => {
        const fakeDocument = createDocument();
        vi.stubGlobal('document', {
            createElement: vi.fn(() => fakeDocument.anchor),
            body: fakeDocument.body,
        });

        expect(downloadUrl('/admin/scan/export?format=csv')).toBe(true);

        expect(fakeDocument.anchor.href).toBe('/admin/scan/export?format=csv');
        expect(fakeDocument.anchor.download).toBe('');
        expect(fakeDocument.anchor.rel).toBe('noopener');
        expect(fakeDocument.anchor.target).toBe('');
        expect(fakeDocument.anchor.click).toHaveBeenCalledTimes(1);
        expect(fakeDocument.anchor.remove).toHaveBeenCalledTimes(1);
    });
});

describe('feedback adapter', () => {
    it('stays silent when Web Audio is unavailable', () => {
        const browserNavigator = { vibrate: vi.fn() };
        vi.stubGlobal('navigator', browserNavigator);

        expect(() => playScanFeedback('success')).not.toThrow();
        expect(browserNavigator.vibrate).toHaveBeenCalledWith(50);
    });

    it('plays a distinct tone per scan status', () => {
        const frequencies: number[] = [];
        const stop = vi.fn();
        const close = vi.fn(() => Promise.resolve());

        class FakeAudioContext {
            destination = {};
            createOscillator(): {
                connect: () => void;
                start: () => void;
                stop: () => void;
                frequency: { value: number };
            } {
                return {
                    connect: vi.fn(),
                    start: vi.fn(),
                    stop,
                    frequency: {
                        set value(value: number) {
                            frequencies.push(value);
                        },
                        get value(): number {
                            return 0;
                        },
                    },
                };
            }

            createGain(): { connect: () => void; gain: { value: number } } {
                return { connect: vi.fn(), gain: { value: 1 } };
            }

            close(): Promise<void> {
                return close();
            }
        }

        vi.stubGlobal('AudioContext', FakeAudioContext);
        vi.stubGlobal('navigator', { vibrate: vi.fn() });
        vi.useFakeTimers();

        try {
            playScanFeedback('success');
            playScanFeedback('already');
            playScanFeedback('invalid');
            vi.runAllTimers();
        } finally {
            vi.useRealTimers();
        }

        expect(frequencies).toEqual([880, 440, 200]);
        expect(stop).toHaveBeenCalledTimes(3);
        expect(close).toHaveBeenCalledTimes(3);
    });

    it('vibrates only when the API exists', () => {
        expect(() => vibrate(50)).not.toThrow();

        const vibrateMock = vi.fn();
        vi.stubGlobal('navigator', { vibrate: vibrateMock });
        vibrate(30);
        expect(vibrateMock).toHaveBeenCalledWith(30);
    });
});
