import { describe, expect, it } from 'vitest';
import { joinAriaIds } from '@/lib/ariaIds';

describe('joinAriaIds', () => {
    it('menggabungkan id yang terisi tanpa duplikat', () => {
        expect(joinAriaIds('f-title-hint', 'f-title-error')).toBe('f-title-hint f-title-error');
    });

    it('mengabaikan id kosong dan undefined', () => {
        expect(joinAriaIds('f-title-hint', undefined, '')).toBe('f-title-hint');
    });

    it('mengembalikan undefined bila tidak ada id yang bisa dipakai', () => {
        expect(joinAriaIds(undefined, '')).toBeUndefined();
    });

    it('membuang duplikat tanpa mengubah urutan', () => {
        expect(joinAriaIds('f-title-hint', 'f-title-hint', 'f-title-error')).toBe('f-title-hint f-title-error');
    });
});
