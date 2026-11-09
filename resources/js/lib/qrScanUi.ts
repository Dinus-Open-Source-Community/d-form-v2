import type { Component } from 'vue'
import { AlertTriangle, CheckCircle, XCircle } from 'lucide-vue-next'
import { formatDateTime } from '@/lib/dateTime'

/** Interval dasar polling feed scan lintas meja (ms). */
export const FEED_POLL_MS = 2000

const FEED_BACKOFF_CAP_MS = 30_000

/**
 * Jeda sebelum percobaan feed berikutnya; exponential 2s/4s/8s/16s, cap 30s.
 * `0` kegagalan = FEED_POLL_MS. Nilai negatif/NaN diperlakukan sebagai tanpa kegagalan.
 */
export function nextFeedDelayMs(consecutiveFailures: number): number {
    if (!Number.isFinite(consecutiveFailures) || consecutiveFailures <= 0) {
        return FEED_POLL_MS
    }

    const exponent = Math.floor(consecutiveFailures) - 1

    return Math.min(FEED_POLL_MS * 2 ** exponent, FEED_BACKOFF_CAP_MS)
}

export type ScanStatus = 'success' | 'already' | 'invalid'

export const SCAN_STATUS_THEME: Record<
    ScanStatus,
    { icon: Component; class: string; bg: string; label: string }
> = {
    success: { icon: CheckCircle, class: 'text-success', bg: 'bg-success/10', label: 'Check-in berhasil' },
    already: { icon: AlertTriangle, class: 'text-warning', bg: 'bg-warning/10', label: 'Sudah pernah scan' },
    invalid: { icon: XCircle, class: 'text-destructive', bg: 'bg-destructive/10', label: 'QR tidak valid' },
}

export interface ScanEntry {
    id: string
    name: string
    email: string
    time: string
    status: ScanStatus
    source: 'camera' | 'manual'
    eventKind: 'event' | 'oprec'
    eventTitle: string
    queueNumber: number | null
}

export interface ScanResult {
    name: string
    email: string
    status: ScanStatus
    source: 'camera' | 'manual'
    rawCode: string
    eventKind: 'event' | 'oprec'
    eventTitle: string
    queueNumber: number | null
}

export function extractQrCandidate(decodedText: string): string {
    const raw = decodedText.trim()
    if (!raw.startsWith('{') || !raw.endsWith('}')) {
        return raw
    }

    try {
        const parsed = JSON.parse(raw) as Record<string, unknown>
        const candidate =
            parsed.application_id ?? parsed.submission_id ?? parsed.token ?? parsed.code ?? parsed.qr ?? parsed.email ?? parsed.id
        if (typeof candidate === 'string' && candidate.trim().length > 0) {
            return candidate.trim()
        }
    }
    catch {
        return raw
    }

    return raw
}

export function createScanHistoryEntry(result: ScanResult): ScanEntry {
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
    }
}

