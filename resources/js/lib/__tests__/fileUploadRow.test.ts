import { describe, expect, it } from 'vitest';
import {
    uploadRowPercentLabel,
    uploadRowPhase,
    uploadRowStatusLabel,
    type TUploadRowPhase,
    type TUploadRowState,
} from '@/lib/fileUploadRow';

describe('uploadRowPhase', () => {
    const cases: Array<[TUploadRowState, TUploadRowPhase]> = [
        ['idle', 'idle'],
        ['uploading', 'uploading'],
        ['completed', 'completed'],
        ['failed', 'failed'],
        ['error', 'failed'],
    ];

    for (const [state, expected] of cases) {
        it(`${state} → ${expected}`, () => {
            expect(uploadRowPhase(state)).toBe(expected);
        });
    }
});

describe('uploadRowStatusLabel', () => {
    it('memakai label status Indonesia dan idle tanpa label', () => {
        expect(uploadRowStatusLabel('uploading')).toBe('Sedang mengunggah…');
        expect(uploadRowStatusLabel('completed')).toBe('Selesai');
        expect(uploadRowStatusLabel('failed')).toBe('Gagal');
        expect(uploadRowStatusLabel('idle')).toBe('');
    });
});

describe('uploadRowPercentLabel', () => {
    const cases: Array<[number, string]> = [
        [0, '0%'],
        [49.5, '50%'],
        [50, '50%'],
        [99.6, '100%'],
        [100, '100%'],
        [-12, '0%'],
        [180, '100%'],
        [Number.NaN, '0%'],
        [Number.POSITIVE_INFINITY, '0%'],
    ];

    for (const [percent, expected] of cases) {
        it(`${percent} → ${expected}`, () => {
            expect(uploadRowPercentLabel(percent)).toBe(expected);
        });
    }
});
