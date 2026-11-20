import { describe, expect, it } from 'vitest';
import {
    FORM_ACCESS_STATUS_LABEL,
    compactDateRangeLabel,
    eventDurationLabel,
    eventRegistrationStatusLabel,
    formPurposeLabel,
} from '@/lib/eventDetailSummary';

describe('eventDurationLabel', () => {
    it('menghitung dua hari untuk tanggal mulai dan selesai berbeda satu hari', () => {
        expect(eventDurationLabel('2026-12-10', '2026-12-11')).toBe('2 hari');
    });

    it('menghitung satu hari untuk acara sehari', () => {
        expect(eventDurationLabel('2026-12-10', '2026-12-10')).toBe('1 hari');
    });

    it('menghitung lintas bulan', () => {
        expect(eventDurationLabel('2026-12-30', '2027-01-02')).toBe('4 hari');
    });

    it('mengembalikan string kosong saat tanggal tidak lengkap', () => {
        expect(eventDurationLabel(null, '2026-12-11')).toBe('');
        expect(eventDurationLabel('2026-12-10', null)).toBe('');
    });

    it('mengembalikan string kosong saat format tanggal tidak valid', () => {
        expect(eventDurationLabel('bukan-tanggal', '2026-12-11')).toBe('');
        expect(eventDurationLabel('2026-12-10', '')).toBe('');
    });

    it('mengembalikan string kosong saat tanggal selesai mendahului tanggal mulai', () => {
        expect(eventDurationLabel('2026-12-11', '2026-12-10')).toBe('');
    });
});

describe('formPurposeLabel', () => {
    it('memberi label Pendaftaran untuk form pendaftaran', () => {
        expect(formPurposeLabel('registration')).toBe('Pendaftaran');
    });

    it('memberi label Survei untuk form lain', () => {
        expect(formPurposeLabel('other')).toBe('Survei');
    });
});

describe('eventRegistrationStatusLabel', () => {
    it('memakai kalimat lengkap untuk chip hero', () => {
        expect(eventRegistrationStatusLabel('open')).toBe('Pendaftaran dibuka');
        expect(eventRegistrationStatusLabel('full')).toBe('Pendaftaran penuh');
        expect(eventRegistrationStatusLabel('closed')).toBe('Pendaftaran ditutup');
        expect(eventRegistrationStatusLabel('not_yet_open')).toBe('Pendaftaran segera dibuka');
    });
});

describe('FORM_ACCESS_STATUS_LABEL', () => {
    it('memakai copy Selesai untuk form yang sudah diisi', () => {
        expect(FORM_ACCESS_STATUS_LABEL.already_submitted).toBe('Selesai');
        expect(FORM_ACCESS_STATUS_LABEL.allowed).toBe('Tersedia');
        expect(FORM_ACCESS_STATUS_LABEL.quota_full).toBe('Kuota penuh');
    });
});

describe('compactDateRangeLabel', () => {
    it('meringkas rentang dalam bulan yang sama', () => {
        expect(compactDateRangeLabel('2026-12-10', '2026-12-11')).toBe('10–11 Des 2026');
    });

    it('menampilkan satu tanggal untuk acara sehari', () => {
        expect(compactDateRangeLabel('2026-12-10', '2026-12-10')).toBe('10 Des 2026');
    });

    it('memakai dua tanggal penuh saat lintas bulan', () => {
        expect(compactDateRangeLabel('2026-12-30', '2027-01-02')).toBe('30 Des 2026 - 2 Jan 2027');
    });

    it('mengembalikan string kosong saat tanggal mulai tidak ada', () => {
        expect(compactDateRangeLabel(null, '2026-12-11')).toBe('');
        expect(compactDateRangeLabel('', '2026-12-11')).toBe('');
    });

    it('memakai tanggal mulai saat tanggal selesai tidak valid', () => {
        expect(compactDateRangeLabel('2026-12-10', 'bukan-tanggal')).toBe('10 Des 2026');
    });
});
