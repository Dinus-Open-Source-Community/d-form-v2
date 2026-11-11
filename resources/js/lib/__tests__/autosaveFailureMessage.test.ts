import { describe, expect, it } from 'vitest';
import { buildAutosaveFailureMessage } from '@/lib/autosaveFailureMessage';

describe('buildAutosaveFailureMessage', () => {
    it('humanizes a known backend message', () => {
        expect(buildAutosaveFailureMessage('Failed to save form. Please check your fields.')).toBe(
            'Gagal menyimpan form. Periksa field yang ditandai.'
        );
    });

    it('keeps an unknown message as-is', () => {
        expect(buildAutosaveFailureMessage('jaringan putus')).toBe('jaringan putus');
    });

    it.each(['', '   '])('falls back to the generic autosave copy for %j', (raw: string) => {
        expect(buildAutosaveFailureMessage(raw)).toBe('Gagal menyimpan otomatis. Perubahan tetap ada di kanvas.');
    });
});
