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

export interface MockRegistrant {
    id: string
    name: string
    email: string
    qrTokens: string[]
}

export interface ScanEntry {
    id: string
    name: string
    email: string
    time: string
    status: ScanStatus
    source: 'camera' | 'manual'
}

export interface ScanResult {
    name: string
    email: string
    status: ScanStatus
    source: 'camera' | 'manual'
    rawCode: string
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
        const candidate = parsed.token ?? parsed.code ?? parsed.qr ?? parsed.email ?? parsed.id
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
    }
}

export const MOCK_REGISTRANTS: MockRegistrant[] = [
    {
        id: 'REG-001',
        name: 'Ahmad Fauzi',
        email: 'ahmad@student.dinus.ac.id',
        qrTokens: ['REG-001', 'ahmad@student.dinus.ac.id', 'DOSCOM-2026-REG-001'],
    },
    {
        id: 'REG-002',
        name: 'Siti Nurhaliza',
        email: 'siti@student.dinus.ac.id',
        qrTokens: ['REG-002', 'siti@student.dinus.ac.id', 'DOSCOM-2026-REG-002'],
    },
    {
        id: 'REG-003',
        name: 'Dewi Lestari',
        email: 'dewi@student.dinus.ac.id',
        qrTokens: ['REG-003', 'dewi@student.dinus.ac.id', 'DOSCOM-2026-REG-003'],
    },
]
