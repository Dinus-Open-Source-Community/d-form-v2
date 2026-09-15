import type { Component } from 'vue'
import { AlertTriangle, CheckCircle, XCircle } from 'lucide-vue-next'

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
    desk: string
    isOwnDesk: boolean
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
    desk: string
    isOwnDesk: boolean
}

export function normalizeQrCode(raw: string): string {
    return raw.trim().toLowerCase()
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
        time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
        status: result.status,
        source: result.source,
        eventKind: result.eventKind,
        eventTitle: result.eventTitle,
        queueNumber: result.queueNumber,
        desk: result.desk,
        isOwnDesk: result.isOwnDesk,
    }
}

export function playScanBeep(status: ScanStatus): void {
    try {
        const ctx = new AudioContext()
        const osc = ctx.createOscillator()
        const gain = ctx.createGain()
        osc.connect(gain)
        gain.connect(ctx.destination)
        osc.frequency.value = status === 'success' ? 880 : status === 'already' ? 440 : 200
        osc.start()
        const ms = status === 'already' ? 320 : 160
        window.setTimeout(() => {
            osc.stop()
            void ctx.close()
        }, ms)
        if (navigator.vibrate) {
            navigator.vibrate(50)
        }
    }
    catch {
        return
    }
}
