import { describe, expect, it } from 'vitest';
import {
    ANSWER_COLUMN_LIMIT,
    normalizeAnswerColumnSelection,
    toggleAnswerColumnSelection,
} from '@/lib/answerColumnSelection';

const available = ['nama', 'phone', 'nim', 'faculty', 'motivation', 'email', 'wa', 'pengalaman'];

describe('normalizeAnswerColumnSelection', () => {
    it('memakai kolom pertama saat belum ada pilihan tersimpan', () => {
        expect(normalizeAnswerColumnSelection(null, available)).toEqual(available.slice(0, ANSWER_COLUMN_LIMIT));
    });

    it('menyaring kolom yang sudah tidak ada dan membatasi jumlahnya', () => {
        const stored = JSON.stringify(['phone', 'hilang', 'nim', 'faculty', 'motivation', 'email', 'wa']);

        expect(normalizeAnswerColumnSelection(stored, available)).toEqual([
            'phone',
            'nim',
            'faculty',
            'motivation',
            'email',
            'wa',
        ]);
    });

    it('mengabaikan nilai rusak', () => {
        expect(normalizeAnswerColumnSelection('bukan json', available)).toEqual(
            available.slice(0, ANSWER_COLUMN_LIMIT)
        );
    });
});

describe('toggleAnswerColumnSelection', () => {
    it('menambah kolom selama batas belum tercapai', () => {
        expect(toggleAnswerColumnSelection(['a'], 'b')).toEqual(['a', 'b']);
    });

    it('menolak penambahan saat batas tercapai', () => {
        const full = ['a', 'b', 'c', 'd', 'e', 'f'];

        expect(toggleAnswerColumnSelection(full, 'g')).toEqual(full);
    });

    it('membuang kolom yang sudah dipilih', () => {
        expect(toggleAnswerColumnSelection(['a', 'b'], 'a')).toEqual(['b']);
    });
});
