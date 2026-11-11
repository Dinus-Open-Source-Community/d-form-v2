import { formatDateTime } from '@/lib/dateTime';
import type { IScanTargetEvent, IScanTargetSession } from '@/types/scan';

/** Jenis target scan: event biasa atau sesi interview OPRec. */
export type TScanKind = 'event' | 'oprec';

/** Peta `type` kontrak API scan (`event`/`recruitment`) ke jenis internal. */
export function scanKindFromApiType(type: string | undefined): TScanKind {
    return type === 'recruitment' ? 'oprec' : 'event';
}

function readTrimmed(value: string | null | undefined): string {
    return typeof value === 'string' ? value.trim() : '';
}

/** Judul acara untuk feed/riwayat; oprec membuang bagian waktu ISO pada judul backend. */
export function formatGlobalScanEventTitle(kind: TScanKind, rawTitle: string): string {
    const title = rawTitle.trim();
    if (title.length === 0) {
        return '-';
    }

    if (kind !== 'oprec') {
        return title;
    }

    return title.replace(/(\d{4}-\d{2}-\d{2})[T ]\d{2}:\d{2}(?::\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?/g, '$1');
}

/** Tanggal sesi interview dalam format panjang; fallback ke teks mentah. */
export function formatScanSessionDate(raw: string): string {
    const text = raw.trim();
    if (text.length === 0) {
        return '';
    }

    return formatDateTime({ value: text, variant: 'date-long' }) || text;
}

/** Nama divisi sesi interview; fallback `Interview` bila backend tidak mengirim nama. */
export function scanSessionDivisionName(session: IScanTargetSession): string {
    const name = readTrimmed(session.division?.name);
    if (name.length > 0) {
        return name;
    }

    return 'Interview';
}

/** Label opsi filter untuk satu sesi interview OPRec. */
export function scanSessionOptionLabel(session: IScanTargetSession): string {
    const divisionName = scanSessionDivisionName(session);
    const date = formatScanSessionDate(readTrimmed(session.session_date));

    return date.length > 0 ? `${divisionName} · ${date}` : divisionName;
}

/**
 * Backend menyusun `eventTitle` oprec sebagai
 * `Oprec · {division.name} · {session_date}`. Kunci ini meniru bagian setelah
 * prefix "Oprec · " agar cocok dengan entri riwayat hasil scan.
 */
export function scanSessionMatchKey(session: IScanTargetSession): string {
    const divisionName = scanSessionDivisionName(session);
    const rawDate = readTrimmed(session.session_date);

    return `${divisionName} · ${rawDate}`;
}

/** Label opsi filter untuk satu event; fallback memakai id event. */
export function scanEventOptionLabel(event: IScanTargetEvent): string {
    const title = readTrimmed(event.title);

    return title.length > 0 ? title : `Event ${String(event.id)}`;
}

/** Normalkan teks pembanding filter: lowercase, non-alfanumerik menjadi spasi. */
export function normalizeScanMatchText(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

/** Jam lokal untuk satu timestamp feed; timestamp tidak valid memakai jam sekarang. */
export function formatScanFeedTime(ts: string): string {
    const parsed = new Date(ts);
    if (Number.isNaN(parsed.getTime())) {
        return formatDateTime({ value: new Date().toISOString(), variant: 'time' });
    }

    return formatDateTime({ value: ts, variant: 'time' });
}

/** Identitas satu hasil scan, dipakai untuk menekan entri feed yang sudah diproses lokal. */
export interface IScanIdentity {
    kind: TScanKind;
    identifier: string;
    queueNumber: number | null;
    eventTitle: string;
}

/** Kunci identitas hasil scan; `null` queueNumber dinormalkan menjadi `-`. */
export function scanIdentity(input: IScanIdentity): string {
    const queue = input.queueNumber === null ? '-' : String(input.queueNumber);

    return `${input.kind}|${input.eventTitle}|${input.identifier}|${queue}`;
}
