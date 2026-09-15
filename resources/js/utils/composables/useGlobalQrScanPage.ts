import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import { toast } from 'vue-sonner'
import { Html5Qrcode } from 'html5-qrcode'
import { humanizeErrorMessage, parseApiErrorMessage, showErrorToast } from '@/lib/error-message'
import {
    createScanHistoryEntry,
    extractQrCandidate,
    isGlobalScanFeedPayload,
    parseGlobalScanCursor,
    parseGlobalScanFeedRows,
    parseGlobalScanQueue,
    playScanBeep,
    type GlobalScanFeedRow,
    type GlobalScanQueueSession,
    type ScanEntry,
    type ScanResult,
} from '@/lib/qrScanUi'

export interface GlobalScanTargets {
    sessions: Array<{ id: string } & Record<string, unknown>>
    events: Array<{ id: string | number } & Record<string, unknown>>
}

export interface GlobalScanTargetOption {
    id: string
    label: string
    kind: 'event' | 'oprec'
}

export interface GlobalScanSummary {
    total: number
    success: number
    already: number
    invalid: number
}

interface GlobalScanAttendee {
    name?: string
    email?: string
    registration_number?: string
    application_id?: string
    queue_number?: number | null
    form_answer_id?: string
}

interface GlobalScanEnvelope {
    type: 'event' | 'recruitment'
    eventTitle: string
    attendee: GlobalScanAttendee
    status: 'success' | 'duplicate'
    scannedAt: string
}

interface GlobalScanErrorBody {
    message?: string
    type?: string
    eventTitle?: string
    attendee?: GlobalScanAttendee
    errors?: Record<string, string[]>
}

const DESK_STORAGE_KEY = 'scan-desk-id'
const SCAN_COOLDOWN_MS = 2000
const FEED_POLL_MS = 2000
const FEED_POLL_TIMEOUT_MS = 8000

function resolveDeskId(): string {
    try {
        const existing = sessionStorage.getItem(DESK_STORAGE_KEY)
        if (existing !== null && existing.trim().length > 0) {
            return existing
        }

        const fresh =
            typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
                ? crypto.randomUUID().slice(0, 8)
                : Math.random().toString(16).slice(2, 10)
        sessionStorage.setItem(DESK_STORAGE_KEY, fresh)

        return fresh
    }
    catch {
        return Math.random().toString(16).slice(2, 10)
    }
}

function padQueueNumber(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-'
    }

    return String(value).padStart(2, '0')
}

function formatGlobalEventTitle(kind: 'event' | 'oprec', rawTitle: string): string {
    const title = rawTitle.trim()
    if (title.length === 0) {
        return '-'
    }

    if (kind !== 'oprec') {
        return title
    }

    return title.replace(/(\d{4}-\d{2}-\d{2})[T ]\d{2}:\d{2}(?::\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?/g, '$1')
}

function formatSessionDate(raw: string): string {
    const text: string = raw.trim()
    if (text.length === 0) {
        return ''
    }

    const parsed = new Date(text)
    if (Number.isNaN(parsed.getTime())) {
        return text
    }

    return parsed.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

function readRecordString(record: Record<string, unknown>, key: string): string {
    const value: unknown = record[key]

    return typeof value === 'string' ? value : ''
}

function sessionOptionLabel(session: { id: string } & Record<string, unknown>): string {
    const division: unknown = session.division
    let divisionName = 'Interview'
    if (typeof division === 'object' && division !== null) {
        const name: unknown = (division as Record<string, unknown>).name
        if (typeof name === 'string' && name.trim().length > 0) {
            divisionName = name.trim()
        }
    }

    const date = formatSessionDate(readRecordString(session, 'session_date'))

    return date.length > 0 ? `${divisionName} · ${date}` : divisionName
}

function eventOptionLabel(event: { id: string | number } & Record<string, unknown>): string {
    const title = readRecordString(event, 'title')

    return title.length > 0 ? title : `Event ${String(event.id)}`
}

function formatFeedTime(ts: string): string {
    const parsed = new Date(ts)
    if (Number.isNaN(parsed.getTime())) {
        return new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
    }

    return parsed.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

function scanIdentity(
    kind: 'event' | 'oprec',
    identifier: string,
    queueNumber: number | null,
    eventTitle: string,
): string {
    return `${kind}|${eventTitle}|${identifier}|${queueNumber === null ? '-' : String(queueNumber)}`
}

export function useGlobalQrScanPage(
    scannerContainerId: string,
    storeUrl: string,
    feedUrl: string,
    getTargets: () => GlobalScanTargets,
) {
    const deskId = resolveDeskId()

    const scanner = ref<Html5Qrcode | null>(null)
    const cameras = ref<Array<{ id: string; label: string }>>([])
    const selectedCameraId = ref('')
    const isCameraReady = ref(false)
    const isStartingCamera = ref(false)
    const permissionError = ref('')
    const registrationCodeInput = ref('')
    const scanResult = ref<ScanResult | null>(null)
    const scanHistory = ref<ScanEntry[]>([])
    const lastRaw = ref('')
    const lastAt = ref(0)
    const scanBusy = ref(false)
    const selectedTarget = ref('all')
    const logExpanded = ref(false)
    const logQuery = ref('')

    const queue = ref<GlobalScanQueueSession[]>([])
    const feedOnline = ref(false)

    const scanEntryEpochMs = new Map<string, number>()
    const localEntryIdentities = new Set<string>()
    const seenFeedIds = new Set<string>()
    let feedCursor = ''
    let pollTimer: number | null = null
    let pollAbort: AbortController | null = null

    function isTodayEntry(entry: ScanEntry): boolean {
        const epoch: number | undefined = scanEntryEpochMs.get(entry.id)
        if (epoch === undefined) {
            return true
        }

        return new Date(epoch).toDateString() === new Date().toDateString()
    }

    const todayEntries = computed<ScanEntry[]>(() => scanHistory.value.filter(isTodayEntry))
    const successfulScansCount = computed(() => todayEntries.value.filter((entry) => entry.status === 'success').length)
    const duplicateScansCount = computed(() => todayEntries.value.filter((entry) => entry.status === 'already').length)
    const invalidScansCount = computed(() => todayEntries.value.filter((entry) => entry.status === 'invalid').length)

    const summary = computed<GlobalScanSummary>(() => ({
        total: todayEntries.value.length,
        success: successfulScansCount.value,
        already: duplicateScansCount.value,
        invalid: invalidScansCount.value,
    }))

    const targetOptions = computed<GlobalScanTargetOption[]>(() => {
        const targets = getTargets()

        return [
            ...targets.sessions.map((session) => ({
                id: String(session.id),
                label: sessionOptionLabel(session),
                kind: 'oprec' as const,
            })),
            ...targets.events.map((event) => ({
                id: String(event.id),
                label: eventOptionLabel(event),
                kind: 'event' as const,
            })),
        ]
    })

    const activeTargetCount = computed(() => {
        const targets = getTargets()

        return targets.sessions.length + targets.events.length
    })

    const eventLabel = computed(() => {
        const current = scanResult.value
        if (current !== null && current.eventTitle !== '' && current.eventTitle !== '-') {
            return `${current.eventKind === 'oprec' ? 'OPREC' : 'EVENT'} · ${current.eventTitle}`
        }

        return 'Siap — mode Semua'
    })

    function mapEnvelopeKind(type: string | undefined): 'event' | 'oprec' {
        return type === 'recruitment' ? 'oprec' : 'event'
    }

    function pushResult(result: ScanResult): void {
        localEntryIdentities.add(
            scanIdentity(result.eventKind, result.email, result.queueNumber, result.eventTitle),
        )
        scanResult.value = result
        const entry: ScanEntry = createScanHistoryEntry(result)
        scanEntryEpochMs.set(entry.id, Date.now())
        scanHistory.value.unshift(entry)
        playScanBeep(result.status)
    }

    function ingestFeedRow(row: GlobalScanFeedRow): void {
        if (seenFeedIds.has(row.id)) {
            return
        }
        seenFeedIds.add(row.id)

        const kind: 'event' | 'oprec' = row.type === 'recruitment' ? 'oprec' : 'event'
        const name = row.name.trim().length > 0 ? row.name.trim() : 'Tanpa nama'
        const identifier = row.identifier.trim().length > 0 ? row.identifier.trim() : '-'
        const eventTitle = formatGlobalEventTitle(kind, row.eventTitle)

        if (localEntryIdentities.has(scanIdentity(kind, identifier, row.queueNumber, eventTitle))) {
            return
        }

        const parsed = new Date(row.ts)
        const epoch = Number.isNaN(parsed.getTime()) ? Date.now() : parsed.getTime()

        const entry: ScanEntry = {
            id: row.id,
            name,
            email: identifier,
            time: formatFeedTime(row.ts),
            status: 'success',
            source: 'camera',
            eventKind: kind,
            eventTitle,
            queueNumber: row.queueNumber,
        }
        scanEntryEpochMs.set(entry.id, epoch)
        scanHistory.value.unshift(entry)
    }

    function applyFeed(payload: unknown): void {
        if (!isGlobalScanFeedPayload(payload)) {
            return
        }

        const cursor = parseGlobalScanCursor(payload)
        if (cursor.length > 0) {
            feedCursor = cursor
        }

        queue.value = parseGlobalScanQueue(payload)

        for (const row of parseGlobalScanFeedRows(payload)) {
            ingestFeedRow(row)
        }

        feedOnline.value = true
    }

    async function pollFeed(): Promise<void> {
        if (pollAbort !== null) {
            return
        }

        const controller = new AbortController()
        pollAbort = controller

        try {
            const { data } = await axios.get<unknown>(feedUrl, {
                params: feedCursor.length > 0 ? { since: feedCursor } : {},
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
                timeout: FEED_POLL_TIMEOUT_MS,
            })

            applyFeed(data)
        }
        catch {
            feedOnline.value = false
        }
        finally {
            pollAbort = null
        }
    }

    function startFeedPolling(): void {
        if (pollTimer !== null) {
            return
        }

        void pollFeed()
        pollTimer = window.setInterval(() => {
            void pollFeed()
        }, FEED_POLL_MS)
    }

    function stopFeedPolling(): void {
        if (pollTimer !== null) {
            window.clearInterval(pollTimer)
            pollTimer = null
        }

        if (pollAbort !== null) {
            pollAbort.abort()
            pollAbort = null
        }
    }

    async function submitScanPayload(raw: string, source: 'camera' | 'manual'): Promise<void> {
        if (scanBusy.value) {
            return
        }

        const trimmed = raw.trim()
        if (trimmed.length === 0) {
            showErrorToast('Isi kode registrasi terlebih dahulu.')

            return
        }

        scanBusy.value = true
        const rawDisplay = extractQrCandidate(trimmed)

        try {
            const { data } = await axios.post<GlobalScanEnvelope>(
                storeUrl,
                { raw: trimmed, desk: deskId },
                { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
            )

            const kind = mapEnvelopeKind(data.type)
            const title = formatGlobalEventTitle(kind, data.eventTitle ?? '')

            let result: ScanResult

            if (kind === 'oprec') {
                const identifier = data.attendee.registration_number?.trim() || '-'
                const queueNumber = data.attendee.queue_number ?? null
                result = {
                    name: data.attendee.name?.trim() || 'Tanpa nama',
                    email: identifier,
                    status: 'success',
                    source,
                    rawCode: rawDisplay,
                    eventKind: kind,
                    eventTitle: title,
                    queueNumber,
                }
                toast.success(data.attendee.name?.trim() || 'Check-in berhasil.', {
                    description: `#${padQueueNumber(queueNumber)} — arahkan ke ruang tunggu`,
                })
            }
            else {
                const email = data.attendee.email?.trim() || '-'
                result = {
                    name: data.attendee.name?.trim() || 'Tanpa nama',
                    email,
                    status: 'success',
                    source,
                    rawCode: rawDisplay,
                    eventKind: kind,
                    eventTitle: title,
                    queueNumber: null,
                }
                toast.success(data.attendee.name?.trim() || 'Check-in berhasil.', {
                    description: 'Boleh masuk — tiket dikirim ke email',
                })
            }

            pushResult(result)

            if (source === 'manual') {
                registrationCodeInput.value = ''
            }
        }
        catch (error) {
            if (axios.isAxiosError(error)) {
                const status = error.response?.status
                const body = error.response?.data as GlobalScanErrorBody | undefined

                if (status === 409) {
                    const kind = mapEnvelopeKind(body?.type)
                    const title = formatGlobalEventTitle(kind, body?.eventTitle ?? '')
                    const fallbackQueue = scanResult.value?.eventKind === kind ? scanResult.value.queueNumber : null
                    const msg = humanizeErrorMessage(body?.message ?? 'Peserta sudah pernah scan.')

                    if (kind === 'oprec') {
                        const identifier = body?.attendee?.registration_number?.trim() || '-'
                        const name = body?.attendee?.name?.trim() || 'Sudah terdaftar hadir'
                        pushResult({
                            name,
                            email: identifier,
                            status: 'already',
                            source,
                            rawCode: rawDisplay,
                            eventKind: kind,
                            eventTitle: title,
                            queueNumber: body?.attendee?.queue_number ?? fallbackQueue,
                        })
                        toast.warning(msg, {
                            description: `${name} · ${identifier}`,
                        })
                    }
                    else {
                        const email = body?.attendee?.email?.trim() || '-'
                        const name = body?.attendee?.name?.trim() || 'Sudah terdaftar hadir'
                        pushResult({
                            name,
                            email,
                            status: 'already',
                            source,
                            rawCode: rawDisplay,
                            eventKind: kind,
                            eventTitle: title,
                            queueNumber: null,
                        })
                        toast.warning(msg, {
                            description: email !== '-' ? `${name} · ${email}` : name,
                        })
                    }

                    return
                }

                if (status === 422) {
                    const msg = parseApiErrorMessage(body, 'Data tidak valid.')
                    pushResult({
                        name: 'Tidak dapat diproses',
                        email: '-',
                        status: 'invalid',
                        source,
                        rawCode: rawDisplay,
                        eventKind: scanResult.value?.eventKind ?? 'event',
                        eventTitle: scanResult.value?.eventTitle ?? '-',
                        queueNumber: null,
                    })
                    showErrorToast(msg)

                    return
                }
            }

            pushResult({
                name: 'Kesalahan jaringan',
                email: '-',
                status: 'invalid',
                source,
                rawCode: rawDisplay,
                eventKind: scanResult.value?.eventKind ?? 'event',
                eventTitle: scanResult.value?.eventTitle ?? '-',
                queueNumber: null,
            })
            showErrorToast('Permintaan gagal', {
                description: error instanceof Error ? humanizeErrorMessage(error.message) : 'Coba lagi dalam beberapa saat.',
            })
        }
        finally {
            scanBusy.value = false
        }
    }

    function processScan(decodedText: string, source: 'camera' | 'manual'): void {
        const now = Date.now()
        const key = decodedText.trim()
        if (key.length > 0 && key === lastRaw.value && now - lastAt.value < SCAN_COOLDOWN_MS) {
            return
        }

        lastRaw.value = key
        lastAt.value = now

        void submitScanPayload(key, source)
    }

    async function loadCameras(): Promise<void> {
        try {
            const discoveredCameras = await Html5Qrcode.getCameras()
            cameras.value = discoveredCameras.map((camera, index) => ({
                id: camera.id,
                label: camera.label || `Camera ${index + 1}`,
            }))

            if (cameras.value.length > 0 && selectedCameraId.value.length === 0) {
                selectedCameraId.value = cameras.value[0].id
            }

            permissionError.value = ''
        }
        catch (error) {
            permissionError.value = humanizeErrorMessage(
                'Gagal membaca daftar kamera. Pastikan browser punya izin kamera.',
            )
            showErrorToast('Kamera tidak tersedia', {
                description:
                    error instanceof Error
                        ? humanizeErrorMessage(error.message)
                        : 'Terjadi kesalahan saat mengakses kamera.',
            })
        }
    }

    async function startCameraScanner(): Promise<void> {
        if (isCameraReady.value || isStartingCamera.value) {
            return
        }

        if (!selectedCameraId.value) {
            showErrorToast('Pilih kamera terlebih dahulu.')

            return
        }

        isStartingCamera.value = true
        permissionError.value = ''

        try {
            scanner.value = new Html5Qrcode(scannerContainerId)
            await scanner.value.start(
                selectedCameraId.value,
                {
                    fps: 10,
                    qrbox: { width: 280, height: 280 },
                    aspectRatio: 1,
                },
                (decodedText) => processScan(decodedText, 'camera'),
                () => {
                },
            )
            isCameraReady.value = true
            toast.success('Kamera aktif', {
                description: 'Arahkan QR ke area scanner untuk check-in otomatis.',
            })
        }
        catch (error) {
            permissionError.value = humanizeErrorMessage(
                'Izin kamera ditolak atau kamera sedang digunakan aplikasi lain.',
            )
            showErrorToast('Tidak bisa memulai kamera', {
                description:
                    error instanceof Error
                        ? humanizeErrorMessage(error.message)
                        : 'Coba pilih kamera lain atau muat ulang halaman.',
            })
        }
        finally {
            isStartingCamera.value = false
        }
    }

    async function stopCameraScanner(): Promise<void> {
        if (!scanner.value) {
            return
        }

        try {
            if (isCameraReady.value) {
                await scanner.value.stop()
            }
            await scanner.value.clear()
        }
        finally {
            scanner.value = null
            isCameraReady.value = false
        }
    }

    async function switchCamera(nextCameraId: string | undefined): Promise<void> {
        if (nextCameraId === undefined) {
            return
        }

        selectedCameraId.value = nextCameraId

        if (!nextCameraId) {
            return
        }

        if (!isCameraReady.value) {
            return
        }

        await stopCameraScanner()
        await startCameraScanner()
    }

    function submitManualCode(): void {
        const code = registrationCodeInput.value.trim()

        if (code.length === 0) {
            showErrorToast('Isi kode registrasi terlebih dahulu.')

            return
        }

        void submitScanPayload(code, 'manual')
    }

    function selectTarget(id: string): void {
        selectedTarget.value = id
    }

    function clearHistory(): void {
        scanHistory.value = []
        scanEntryEpochMs.clear()
        scanResult.value = null
        toast('Riwayat scan dibersihkan')
    }

    onMounted(loadCameras)
    onMounted(startFeedPolling)
    onBeforeUnmount(stopCameraScanner)
    onBeforeUnmount(stopFeedPolling)

    return {
        scannerContainerId,
        cameras,
        selectedCameraId,
        isCameraReady,
        isStartingCamera,
        permissionError,
        registrationCodeInput,
        scanResult,
        scanHistory,
        eventLabel,
        successfulScansCount,
        duplicateScansCount,
        invalidScansCount,
        summary,
        selectedTarget,
        selectTarget,
        logExpanded,
        logQuery,
        targetOptions,
        activeTargetCount,
        scanBusy,
        queue,
        feedOnline,
        processScan,
        submitScanPayload,
        startCameraScanner,
        stopCameraScanner,
        switchCamera,
        submitManualCode,
        clearHistory,
    }
}
