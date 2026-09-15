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
    }
}

export interface GlobalScanQueueServee {
    name: string
    queueNumber: number | null
}

export interface GlobalScanQueueSession {
    sessionId: string
    label: string
    nowServing: GlobalScanQueueServee | null
    waiting: GlobalScanQueueServee[]
    waitingCount: number
}

export interface GlobalScanFeedRow {
    id: string
    ts: string
    type: 'recruitment' | 'event'
    eventTitle: string
    name: string
    identifier: string
    queueNumber: number | null
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function toUnknownArray(value: unknown): unknown[] {
    if (!Array.isArray(value)) {
        return []
    }

    const items: unknown[] = []
    for (let index = 0; index < value.length; index += 1) {
        const item: unknown = value[index]
        items.push(item)
    }

    return items
}

function readString(record: Record<string, unknown>, key: string): string {
    const value = record[key]

    return typeof value === 'string' ? value : ''
}

function readQueueNumber(record: Record<string, unknown>, key: string): number | null {
    const value = record[key]

    return typeof value === 'number' && Number.isFinite(value) ? value : null
}

function parseQueueServee(value: unknown): GlobalScanQueueServee | null {
    if (!isRecord(value)) {
        return null
    }

    const name = readString(value, 'name').trim()
    if (name.length === 0) {
        return null
    }

    return { name, queueNumber: readQueueNumber(value, 'queueNumber') }
}

export function isGlobalScanFeedPayload(payload: unknown): boolean {
    if (!isRecord(payload)) {
        return false
    }

    return Array.isArray(payload.rows) || Array.isArray(payload.queue)
}

export function parseGlobalScanFeedRows(payload: unknown): GlobalScanFeedRow[] {
    if (!isRecord(payload)) {
        return []
    }

    const rows: GlobalScanFeedRow[] = []
    for (const item of toUnknownArray(payload.rows)) {
        if (!isRecord(item)) {
            continue
        }

        const id = readString(item, 'id')
        if (id.length === 0) {
            continue
        }

        rows.push({
            id,
            ts: readString(item, 'ts'),
            type: readString(item, 'type') === 'recruitment' ? 'recruitment' : 'event',
            eventTitle: readString(item, 'eventTitle'),
            name: readString(item, 'name'),
            identifier: readString(item, 'identifier'),
            queueNumber: readQueueNumber(item, 'queueNumber'),
        })
    }

    return rows
}

export function parseGlobalScanCursor(payload: unknown): string {
    if (!isRecord(payload)) {
        return ''
    }

    return readString(payload, 'cursor')
}

export function parseGlobalScanQueue(payload: unknown): GlobalScanQueueSession[] {
    if (!isRecord(payload)) {
        return []
    }

    const sessions: GlobalScanQueueSession[] = []
    for (const item of toUnknownArray(payload.queue)) {
        if (!isRecord(item)) {
            continue
        }

        const sessionId = readString(item, 'sessionId').trim()
        if (sessionId.length === 0) {
            continue
        }

        const waiting: GlobalScanQueueServee[] = []
        for (const servee of toUnknownArray(item.waiting)) {
            const parsed = parseQueueServee(servee)
            if (parsed !== null) {
                waiting.push(parsed)
            }
        }

        const waitingCount = readQueueNumber(item, 'waitingCount')

        sessions.push({
            sessionId,
            label: readString(item, 'label'),
            nowServing: parseQueueServee(item.nowServing),
            waiting,
            waitingCount: waitingCount !== null ? waitingCount : waiting.length,
        })
    }

    return sessions
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
