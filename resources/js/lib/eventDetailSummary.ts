import type { TFormAccessStatus, TFormPurpose } from '@/types/form';

const MILLISECONDS_PER_DAY = 86_400_000;

/** Tanggal `Y-m-d` dari server, dibaca sebagai UTC agar tidak bergeser oleh zona waktu. */
function parseDateOnly(value: string): Date | null {
    const match = /^\d{4}-\d{2}-\d{2}$/.test(value);
    if (!match) return null;

    const parsed = new Date(`${value}T00:00:00Z`);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

/** Durasi acara dalam hari, inklusif: 10 sampai 11 Desember berarti 2 hari. */
export function eventDurationLabel(start: string | null, end: string | null): string {
    if (start === null || end === null) return '';

    const startDate = parseDateOnly(start);
    const endDate = parseDateOnly(end);

    if (startDate === null || endDate === null) return '';
    if (endDate.getTime() < startDate.getTime()) return '';

    const days = Math.round((endDate.getTime() - startDate.getTime()) / MILLISECONDS_PER_DAY) + 1;

    return `${days} hari`;
}

/** Label tipe formulir pada daftar formulir acara. */
export function formPurposeLabel(purpose: TFormPurpose): string {
    return purpose === 'other' ? 'Survei' : 'Pendaftaran';
}

/** Label chip status pendaftaran pada hero; memakai kelas badge yang sudah ada. */
export function eventRegistrationStatusLabel(status: IEvent['registration_status']): string {
    switch (status) {
        case 'open':
            return 'Pendaftaran dibuka';
        case 'full':
            return 'Pendaftaran penuh';
        case 'closed':
            return 'Pendaftaran ditutup';
        default:
            return 'Pendaftaran segera dibuka';
    }
}

/** Satu peta label status akses form; `Record` penuh membuat status baru gagal di typecheck. */
export const FORM_ACCESS_STATUS_LABEL: Record<TFormAccessStatus, string> = {
    allowed: 'Tersedia',
    already_submitted: 'Selesai',
    prerequisite_not_met: 'Menunggu syarat',
    form_closed: 'Form ditutup',
    registration_not_open: 'Pendaftaran belum buka',
    quota_full: 'Kuota penuh',
    pending_team_confirmation: 'Menunggu konfirmasi tim',
    invitation_closed: 'Undangan berakhir',
    not_visible: 'Tidak tersedia',
    unsupported_registration_mode: 'Belum didukung',
    event_form_already_chosen: 'Sudah pilih form lain',
};

const DAY_MONTH_YEAR = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'UTC',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const MONTH_YEAR = new Intl.DateTimeFormat('id-ID', { timeZone: 'UTC', month: 'short', year: 'numeric' });

/** Rentang tanggal ringkas untuk kartu fakta: "10–11 Des 2026" bila bulan dan tahun sama. */
export function compactDateRangeLabel(start: string | null, end: string | null): string {
    if (start === null || start === '') return '';
    if (end === null || end === '') return formatDateOnly(start);

    const startDate = parseDateOnly(start);
    const endDate = parseDateOnly(end);

    if (startDate === null) return '';
    if (endDate === null) return formatDateOnly(start);
    if (endDate.getTime() < startDate.getTime()) return formatDateOnly(start);
    if (endDate.getTime() === startDate.getTime()) return formatDateOnly(start);

    const sameMonth =
        startDate.getUTCMonth() === endDate.getUTCMonth() && startDate.getUTCFullYear() === endDate.getUTCFullYear();

    if (!sameMonth) {
        return `${formatDateOnly(start)} - ${formatDateOnly(end)}`;
    }

    return `${startDate.getUTCDate()}–${endDate.getUTCDate()} ${MONTH_YEAR.format(startDate)}`;
}

/** Satu tanggal dengan bulan singkat, mis. "10 Des 2026". */
export function formatDateOnly(value: string): string {
    const date = parseDateOnly(value);

    return date === null ? '' : DAY_MONTH_YEAR.format(date);
}
