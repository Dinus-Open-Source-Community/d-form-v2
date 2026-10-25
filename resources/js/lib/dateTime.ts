// Satu-satunya formatter tanggal/waktu front-end. Semua angka, label, dan locale
// dipusatkan di sini supaya tidak ada implementasi `toLocale*` tersebar di komponen.

/** Zona waktu tetap aplikasi (sama dengan `config/app.php`). */
const TIME_ZONE = 'Asia/Jakarta';

/** Varian tampilan yang dipakai lintas halaman. */
export type TDateTimeVariant =
    | 'date'
    | 'date-range'
    | 'date-medium'
    | 'date-long'
    | 'date-numeric'
    | 'date-weekday'
    | 'time'
    | 'time-short'
    | 'date-time'
    | 'date-time-medium'
    | 'date-time-long'
    | 'date-time-weekday'
    | 'date-time-numeric';

/** Argumen `formatDateTime`. `end` hanya dibaca varian `date-range`. */
export interface IFormatDateTimeOptions {
    value: string | null | undefined;
    variant?: TDateTimeVariant;
    end?: string | null;
}

interface IFormatPreset {
    locale: string;
    options: Intl.DateTimeFormatOptions;
}

type TSingleDateVariant = Exclude<TDateTimeVariant, 'date-range'>;

const PRESETS: Record<TSingleDateVariant, IFormatPreset> = {
    date: { locale: 'en-US', options: { month: 'short', day: 'numeric', year: 'numeric' } },
    'date-medium': { locale: 'id-ID', options: { day: 'numeric', month: 'short', year: 'numeric' } },
    'date-long': { locale: 'id-ID', options: { day: 'numeric', month: 'long', year: 'numeric' } },
    'date-numeric': { locale: 'id-ID', options: { year: 'numeric', month: 'numeric', day: 'numeric' } },
    'date-weekday': { locale: 'id-ID', options: { weekday: 'short', day: 'numeric', month: 'short' } },
    time: { locale: 'id-ID', options: { hour: '2-digit', minute: '2-digit', second: '2-digit' } },
    'time-short': { locale: 'id-ID', options: { hour: '2-digit', minute: '2-digit' } },
    'date-time': { locale: 'en-US', options: { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' } },
    'date-time-medium': { locale: 'id-ID', options: { dateStyle: 'medium', timeStyle: 'short' } },
    'date-time-long': {
        locale: 'id-ID',
        options: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' },
    },
    'date-time-weekday': {
        locale: 'id-ID',
        options: { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' },
    },
    'date-time-numeric': {
        locale: 'id-ID',
        options: { year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' },
    },
};

/** Formatter dibuat sekali per varian lalu dipakai ulang (hindari alokasi di render). */
const formatterCache = new Map<TSingleDateVariant, Intl.DateTimeFormat>();

/** Ambil (atau buat) formatter Jakarta untuk satu varian. */
function formatterFor(variant: TSingleDateVariant): Intl.DateTimeFormat {
    const cached = formatterCache.get(variant);
    if (cached) {
        return cached;
    }

    const preset = PRESETS[variant];
    const formatter = new Intl.DateTimeFormat(preset.locale, { ...preset.options, timeZone: TIME_ZONE });
    formatterCache.set(variant, formatter);

    return formatter;
}

const MONTH_DAY = new Intl.DateTimeFormat('en-US', { timeZone: TIME_ZONE, month: 'short', day: 'numeric' });
const CALENDAR_DAY = new Intl.DateTimeFormat('en-CA', { timeZone: TIME_ZONE, year: 'numeric', month: '2-digit', day: '2-digit' });

/** "2026-01-02" versi kalender Jakarta — dipakai membandingkan hari/bulan/tahun. */
function calendarDay(date: Date): string {
    return CALENDAR_DAY.format(date);
}

/** "Jan 2" tanpa tahun. */
function monthDay(date: Date): string {
    return MONTH_DAY.format(date);
}

/** Rentang tanggal ringkas: satu hari, satu bulan, satu tahun, atau lintas tahun. */
function formatRange(start: Date, end: Date | null): string {
    const startDay = calendarDay(start);
    if (!end || Number.isNaN(end.getTime())) {
        return formatterFor('date').format(start);
    }

    const endDay = calendarDay(end);
    const [startYear, startMonth] = startDay.split('-');
    const [endYear, endMonth, endDate] = endDay.split('-');

    if (endDay < startDay) {
        return formatterFor('date').format(start);
    }

    if (endDay === startDay) {
        return formatterFor('date').format(start);
    }

    if (startYear === endYear && startMonth === endMonth) {
        return `${monthDay(start)}–${Number(endDate)}, ${startYear}`;
    }

    if (startYear === endYear) {
        return `${monthDay(start)}–${monthDay(end)}, ${startYear}`;
    }

    return `${formatterFor('date').format(start)} — ${formatterFor('date').format(end)}`;
}

/**
 * Format tanggal/waktu sesuai varian. Mengembalikan string kosong bila nilai tidak valid.
 * Dipakai semua halaman, tabel, dan card yang menampilkan tanggal.
 */
export function formatDateTime({ value, variant = 'date', end }: IFormatDateTimeOptions): string {
    const start = value ? new Date(value) : null;
    if (!start || Number.isNaN(start.getTime())) {
        return '';
    }

    if (variant === 'date-range') {
        return formatRange(start, end ? new Date(end) : null);
    }

    return formatterFor(variant).format(start);
}
