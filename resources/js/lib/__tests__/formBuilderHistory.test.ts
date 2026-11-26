import { describe, expect, it } from 'vitest';
import { createHistory, pushHistory, redoHistory, undoHistory } from '@/lib/formBuilderHistory';

describe('form builder history', () => {
    it('mencatat keadaan baru dan membuang cabang redo', () => {
        let state = createHistory('awal');
        state = pushHistory(state, 'kedua');
        state = undoHistory(state);
        state = pushHistory(state, 'ketiga');

        expect(state.future).toEqual([]);
        expect(state.present).toBe('ketiga');
    });

    it('undo mengembalikan keadaan sebelumnya dan redo memulihkannya', () => {
        let state = createHistory('a');
        state = pushHistory(state, 'b');
        state = pushHistory(state, 'c');

        state = undoHistory(state);
        expect(state.present).toBe('b');

        state = redoHistory(state);
        expect(state.present).toBe('c');
    });

    it('tidak melakukan apa pun bila tidak ada yang bisa di-undo atau di-redo', () => {
        const state = createHistory('a');

        expect(undoHistory(state)).toBe(state);
        expect(redoHistory(state)).toBe(state);
    });

    it('mengabaikan keadaan yang sama persis', () => {
        const state = createHistory('a');

        expect(pushHistory(state, 'a')).toBe(state);
    });

    it('membatasi panjang riwayat', () => {
        let state = createHistory(0);

        for (let value = 1; value <= 5; value += 1) {
            state = pushHistory(state, value, 3);
        }

        expect(state.past).toHaveLength(3);
        expect(state.present).toBe(5);
    });
});
