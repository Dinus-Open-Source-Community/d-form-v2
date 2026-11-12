import { describe, expect, it } from 'vitest';
import { toggleVisibilitySelection } from '@/lib/formVisibility';

describe('toggleVisibilitySelection', () => {
    it('menambahkan nilai yang belum ada di akhir daftar', () => {
        expect(toggleVisibilitySelection(['public'], 'participant', true)).toEqual(['public', 'participant']);
    });

    it('membuang nilai saat dimatikan tanpa mengubah urutan sisanya', () => {
        expect(toggleVisibilitySelection(['public', 'participant', 'admin'], 'participant', false)).toEqual([
            'public',
            'admin',
        ]);
    });

    it('tidak menduplikasi nilai yang sudah terpilih', () => {
        expect(toggleVisibilitySelection(['public', 'participant'], 'participant', true)).toEqual([
            'public',
            'participant',
        ]);
    });

    it('tidak menduplikasi nilai ganda yang sudah ada di input', () => {
        expect(toggleVisibilitySelection(['public', 'public'], 'public', true)).toEqual(['public']);
    });

    it('mengabaikan input yang tidak ada saat dimatikan', () => {
        expect(toggleVisibilitySelection(['public'], 'admin', false)).toEqual(['public']);
    });

    it('tidak memutasi daftar sumber', () => {
        const source = ['public'];

        toggleVisibilitySelection(source, 'admin', true);

        expect(source).toEqual(['public']);
    });

    it('mengembalikan daftar kosong saat menambahkan ke daftar kosong', () => {
        expect(toggleVisibilitySelection([], 'public', true)).toEqual(['public']);
        expect(toggleVisibilitySelection([], 'public', false)).toEqual([]);
    });
});
