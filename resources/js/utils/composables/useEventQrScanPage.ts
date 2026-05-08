import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import { toast } from 'vue-sonner'
import { Html5Qrcode } from 'html5-qrcode'
import {
    createScanHistoryEntry,
    extractQrCandidate,
    type ScanEntry,
    type ScanResult,
} from '@/lib/qrScanUi'

interface AttendanceQueuedJson {
    message: string
    attendee: {
        name: string
        email: string
        form_answer_id: string
    }
}

function firstValidationMessage(errors: Record<string, string[]> | undefined): string | null {
    if (!errors) {
        return null
    }

    for (const messages of Object.values(errors)) {
        if (messages.length > 0) {
            return messages[0] ?? null
        }
    }

    return null
}

export function useEventQrScanPage(scannerContainerId: string, attendanceScanStoreUrl: string, eventLabel: string) {
    const scanner = ref<Html5Qrcode | null>(null)
    const cameras = ref<Array<{ id: string; label: string }>>([])
    const selectedCameraId = ref('')
    const isCameraReady = ref(false)
    const isStartingCamera = ref(false)
    const permissionError = ref('')
    const manualQrInput = ref('')
    const registrationCodeInput = ref('')
    const scanResult = ref<ScanResult | null>(null)
    const scanHistory = ref<ScanEntry[]>([])
    const lastDecodedText = ref('')
    const lastDecodedAt = ref(0)
    const scanBusy = ref(false)

    const successfulScansCount = computed(() => scanHistory.value.filter((entry) => entry.status === 'success').length)
    const duplicateScansCount = computed(() => scanHistory.value.filter((entry) => entry.status === 'already').length)
    const invalidScansCount = computed(() => scanHistory.value.filter((entry) => entry.status === 'invalid').length)

    async function submitScanPayload(
        payload: { raw_payload?: string; registration_code?: string },
        source: 'camera' | 'manual',
        rawDisplay: string,
    ) {
        if (scanBusy.value) {
            return
        }

        scanBusy.value = true

        try {
            const { data } = await axios.post<AttendanceQueuedJson>(attendanceScanStoreUrl, payload, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })

            const attendee = data.attendee
            scanResult.value = {
                name: attendee.name,
                email: attendee.email,
                status: 'success',
                source,
                rawCode: rawDisplay,
            }

            scanHistory.value.unshift(createScanHistoryEntry(scanResult.value))
            toast.success(data.message ?? 'Check-in diproses di latar belakang.', {
                description: `${attendee.name} · ${attendee.email}`,
            })

            if (source === 'manual') {
                manualQrInput.value = ''
                registrationCodeInput.value = ''
            }
        }
        catch (error) {
            if (axios.isAxiosError(error)) {
                const status = error.response?.status
                const body = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined

                if (status === 409) {
                    const msg = body?.message ?? 'Peserta sudah pernah scan untuk event ini.'
                    scanResult.value = {
                        name: 'Sudah terdaftar hadir',
                        email: '-',
                        status: 'already',
                        source,
                        rawCode: rawDisplay,
                    }
                    scanHistory.value.unshift(createScanHistoryEntry(scanResult.value))
                    toast.warning(msg)

                    return
                }

                if (status === 422) {
                    const msg =
                        firstValidationMessage(body?.errors)
                        ?? body?.message
                        ?? 'Data tidak valid.'
                    scanResult.value = {
                        name: 'Tidak dapat diproses',
                        email: '-',
                        status: 'invalid',
                        source,
                        rawCode: rawDisplay,
                    }
                    scanHistory.value.unshift(createScanHistoryEntry(scanResult.value))
                    toast.error(msg)

                    return
                }
            }

            scanResult.value = {
                name: 'Kesalahan jaringan',
                email: '-',
                status: 'invalid',
                source,
                rawCode: rawDisplay,
            }
            scanHistory.value.unshift(createScanHistoryEntry(scanResult.value))
            toast.error('Permintaan gagal', {
                description: error instanceof Error ? error.message : 'Coba lagi dalam beberapa saat.',
            })
        }
        finally {
            scanBusy.value = false
        }
    }

    function processScan(decodedText: string, source: 'camera' | 'manual') {
        const now = Date.now()
        if (source === 'camera' && decodedText === lastDecodedText.value && now - lastDecodedAt.value < 1500) {
            return
        }

        lastDecodedText.value = decodedText
        lastDecodedAt.value = now

        const qrCandidate = extractQrCandidate(decodedText)

        void submitScanPayload({ raw_payload: decodedText.trim() }, source, qrCandidate)
    }

    async function loadCameras() {
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
            permissionError.value = 'Gagal membaca daftar kamera. Pastikan browser punya izin kamera.'
            toast.error('Kamera tidak tersedia', {
                description: error instanceof Error ? error.message : 'Terjadi kesalahan saat mengakses kamera.',
            })
        }
    }

    async function startCameraScanner() {
        if (isCameraReady.value || isStartingCamera.value) {
            return
        }

        if (!selectedCameraId.value) {
            toast.error('Pilih kamera terlebih dahulu.')

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
            permissionError.value = 'Izin kamera ditolak atau kamera sedang digunakan aplikasi lain.'
            toast.error('Tidak bisa memulai kamera', {
                description: error instanceof Error ? error.message : 'Coba pilih kamera lain atau muat ulang halaman.',
            })
        }
        finally {
            isStartingCamera.value = false
        }
    }

    async function stopCameraScanner() {
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

    async function switchCamera(nextCameraId: string | undefined) {
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

    function submitManualCode() {
        const raw = manualQrInput.value.trim()
        const code = registrationCodeInput.value.trim()

        if (raw.length === 0 && code.length === 0) {
            toast.error('Tempel isi QR atau isi kode registrasi.')

            return
        }

        const rawDisplay = raw.length > 0 ? extractQrCandidate(raw) : code

        void submitScanPayload(
            {
                ...(raw.length > 0 ? { raw_payload: raw } : {}),
                ...(code.length > 0 ? { registration_code: code } : {}),
            },
            'manual',
            rawDisplay,
        )
    }

    function clearHistory() {
        scanHistory.value = []
        scanResult.value = null
        toast('Riwayat scan dibersihkan')
    }

    onMounted(loadCameras)
    onBeforeUnmount(stopCameraScanner)

    return {
        scannerContainerId,
        cameras,
        selectedCameraId,
        isCameraReady,
        isStartingCamera,
        permissionError,
        manualQrInput,
        registrationCodeInput,
        scanResult,
        scanHistory,
        eventLabel,
        successfulScansCount,
        duplicateScansCount,
        invalidScansCount,
        scanBusy,
        processScan,
        startCameraScanner,
        stopCameraScanner,
        switchCamera,
        submitManualCode,
        clearHistory,
    }
}
