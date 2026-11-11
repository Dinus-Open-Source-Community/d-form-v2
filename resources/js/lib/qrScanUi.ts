import { formatDateTime } from '@/lib/dateTime';
import { isRecord } from '@/lib/isRecord';

export { FEED_POLL_MS, nextFeedDelayMs } from '@/lib/qrScanFeed';

export type TScanStatus = 'success' | 'already' | 'invalid';

export interface IScanEntry {
    id: string;
    name: string;
    email: string;
    time: string;
    status: TScanStatus;
    source: 'camera' | 'manual';
    eventKind: 'event' | 'oprec';
    eventTitle: string;
    queueNumber: number | null;
}

export interface IScanResult {
    name: string;
    email: string;
    status: TScanStatus;
    source: 'camera' | 'manual';
    rawCode: string;
    eventKind: 'event' | 'oprec';
    eventTitle: string;
    queueNumber: number | null;
}

/**
 * Ambil nilai kandidat dari payload QR. QR JSON (`{...}`) dicari field
 * `application_id`/`submission_id`/`token`/`code`/`qr`/`email`/`id`; selain itu teks mentah dipakai apa adanya.
 */
export function extractQrCandidate(decodedText: string): string {
    const raw = decodedText.trim();
    if (!raw.startsWith('{') || !raw.endsWith('}')) {
        return raw;
    }

    try {
        const parsed: unknown = JSON.parse(raw);
        if (!isRecord(parsed)) {
            return raw;
        }

        const candidate =
            parsed.application_id ??
            parsed.submission_id ??
            parsed.token ??
            parsed.code ??
            parsed.qr ??
            parsed.email ??
            parsed.id;
        if (typeof candidate === 'string' && candidate.trim().length > 0) {
            return candidate.trim();
        }
    } catch {
        return raw;
    }

    return raw;
}

/**
 * Bentuk entri riwayat scan dari hasil scan. ID memakai timestamp + suffix acak agar unik
 * per baris, dan waktu memakai jam lokal saat pemanggilan.
 */
export function createScanHistoryEntry(result: IScanResult): IScanEntry {
    return {
        id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
        name: result.name,
        email: result.email,
        time: formatDateTime({ value: new Date().toISOString(), variant: 'time' }),
        status: result.status,
        source: result.source,
        eventKind: result.eventKind,
        eventTitle: result.eventTitle,
        queueNumber: result.queueNumber,
    };
}
