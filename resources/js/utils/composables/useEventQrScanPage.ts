import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Html5Qrcode } from 'html5-qrcode'
import {
    MOCK_REGISTRANTS,
    createScanHistoryEntry,
    extractQrCandidate,
    normalizeQrCode,
    type ScanEntry,
    type ScanResult,
} from '@/lib/qrScanUi'

export function useEventQrScanPage(scannerContainerId: string) {
    const page = usePage()
    const scanner = ref<Html5Qrcode | null>(null)
    const cameras = ref<Array<{ id: string; label: string }>>([])
    const selectedCameraId = ref('')
    const isCameraReady = ref(false)
    const isStartingCamera = ref(false)
    const permissionError = ref('')
    const manualQrInput = ref('')
    const scanResult = ref<ScanResult | null>(null)
    const scanHistory = ref<ScanEntry[]>([])
    const scannedRegistrantIds = ref<Set<string>>(new Set())
    const lastDecodedText = ref('')
    const lastDecodedAt = ref(0)

    const eventUid = computed(() => {
        const pathname = page.url.split('?')[0] ?? ''
        const segments = pathname.split('/').filter(Boolean)
        const eventSegmentIndex = segments.findIndex((segment) => segment === 'events')
        if (eventSegmentIndex === -1) return '-'

        return segments[eventSegmentIndex + 1] ?? '-'
    })

    const successfulScansCount = computed(() => scanHistory.value.filter((entry) => entry.status === 'success').length)
    const duplicateScansCount = computed(() => scanHistory.value.filter((entry) => entry.status === 'already').length)
    const invalidScansCount = computed(() => scanHistory.value.filter((entry) => entry.status === 'invalid').length)

    function processScan(decodedText: string, source: 'camera' | 'manual') {
        const now = Date.now()
        if (source === 'camera' && decodedText === lastDecodedText.value && now - lastDecodedAt.value < 1500) {
            return
        }

        lastDecodedText.value = decodedText
        lastDecodedAt.value = now

        const qrCandidate = extractQrCandidate(decodedText)
        const normalizedCandidate = normalizeQrCode(qrCandidate)

        const registrant = MOCK_REGISTRANTS.find((item) =>
            item.qrTokens.some((token) => normalizeQrCode(token) === normalizedCandidate),
        )

        if (!registrant) {
            scanResult.value = {
                name: 'QR tidak dikenali',
                email: '-',
                status: 'invalid',
                source,
                rawCode: qrCandidate,
            }

            scanHistory.value.unshift(createScanHistoryEntry(scanResult.value))
            toast.error('QR tidak valid', {
                description: 'Kode tidak ditemukan pada daftar peserta (mode frontend-only).',
            })
            return
        }

        const isDuplicate = scannedRegistrantIds.value.has(registrant.id)
        if (!isDuplicate) {
            scannedRegistrantIds.value.add(registrant.id)
        }

        scanResult.value = {
            name: registrant.name,
            email: registrant.email,
            status: isDuplicate ? 'already' : 'success',
            source,
            rawCode: qrCandidate,
        }

        scanHistory.value.unshift(createScanHistoryEntry(scanResult.value))

        if (isDuplicate) {
            toast.warning('Peserta sudah pernah scan', {
                description: `${registrant.name} sudah tercatat sebelumnya.`,
            })
            return
        }

        toast.success('Check-in berhasil', {
            description: `${registrant.name} berhasil dicatat hadir.`,
        })
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
        if (isCameraReady.value || isStartingCamera.value) return
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
        if (!scanner.value) return

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
        if (nextCameraId === undefined) return
        selectedCameraId.value = nextCameraId
        if (!nextCameraId) return
        if (!isCameraReady.value) return

        await stopCameraScanner()
        await startCameraScanner()
    }

    function submitManualCode() {
        const value = manualQrInput.value.trim()
        if (value.length === 0) {
            toast.error('Masukkan teks QR terlebih dahulu.')
            return
        }

        processScan(value, 'manual')
        manualQrInput.value = ''
    }

    function clearHistory() {
        scanHistory.value = []
        scannedRegistrantIds.value = new Set()
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
        scanResult,
        scanHistory,
        eventUid,
        successfulScansCount,
        duplicateScansCount,
        invalidScansCount,
        processScan,
        startCameraScanner,
        stopCameraScanner,
        switchCamera,
        submitManualCode,
        clearHistory,
    }
}
