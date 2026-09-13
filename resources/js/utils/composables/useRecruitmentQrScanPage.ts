import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import { toast } from 'vue-sonner'
import { Html5Qrcode } from 'html5-qrcode'
import { humanizeErrorMessage, parseApiErrorMessage, showErrorToast } from '@/lib/error-message'
import {
    createScanHistoryEntry,
    extractQrCandidate,
    type ScanEntry,
    type ScanResult,
} from '@/lib/qrScanUi'

interface AttendanceJson {
    message: string
    attendee: {
        name: string
        registration_number: string
        application_id: string
        queue_number: number | null
    }
}

export function useRecruitmentQrScanPage(
    scannerContainerId: string,
    attendanceScanStoreUrl: string,
    sessionId: () => string,
    sessionLabel: () => string,
) {
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
        payload: { raw_payload?: string; registration_number?: string; application_id?: string },
        source: 'camera' | 'manual',
        rawDisplay: string,
    ) {
        if (scanBusy.value || !sessionId()) {
            return
        }

        scanBusy.value = true

        try {
            const { data } = await axios.post<AttendanceJson>(
                attendanceScanStoreUrl,
                { session_id: sessionId(), ...payload },
                { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
            )

            const attendee = data.attendee
            scanResult.value = {
                name: attendee.name,
                email: attendee.registration_number,
                status: 'success',
                source,
                rawCode: rawDisplay,
            }

            scanHistory.value.unshift(createScanHistoryEntry(scanResult.value))
            toast.success(data.message ?? 'Check-in berhasil.', {
                description: `${attendee.name} · #${String(attendee.queue_number ?? '-').padStart(2, '0')}`,
            })

            if (source === 'manual') {
                manualQrInput.value = ''
                registrationCodeInput.value = ''
            }
        } catch (error) {
            if (axios.isAxiosError(error)) {
                const status = error.response?.status
                const body = error.response?.data as {
                    message?: string
                    attendee?: AttendanceJson['attendee']
                    errors?: Record<string, string[]>
                } | undefined

                if (status === 409) {
                    const msg = humanizeErrorMessage(body?.message ?? 'Applicant sudah check-in.')
                    const attendee = body?.attendee
                    const name = attendee?.name?.trim() || 'Sudah terdaftar hadir'
                    const reg = attendee?.registration_number?.trim() || '-'
                    scanResult.value = {
                        name,
                        email: reg,
                        status: 'already',
                        source,
                        rawCode: rawDisplay,
                    }
                    scanHistory.value.unshift(createScanHistoryEntry(scanResult.value))
                    toast.warning(msg, { description: `${name} · ${reg}` })

                    return
                }

                if (status === 422) {
                    const msg = parseApiErrorMessage(body, 'Data tidak valid.')
                    scanResult.value = {
                        name: 'Tidak dapat diproses',
                        email: '-',
                        status: 'invalid',
                        source,
                        rawCode: rawDisplay,
                    }
                    scanHistory.value.unshift(createScanHistoryEntry(scanResult.value))
                    showErrorToast(msg)

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
            showErrorToast('Gagal memproses scan.')
        } finally {
            scanBusy.value = false
        }
    }

    async function submitManualCode() {
        const raw = manualQrInput.value.trim()
        const reg = registrationCodeInput.value.trim()

        if (raw !== '') {
            await submitScanPayload({ raw_payload: raw }, 'manual', raw)
            return
        }

        if (reg !== '') {
            await submitScanPayload({ registration_number: reg.toUpperCase() }, 'manual', reg)
        }
    }

    async function onQrDecoded(decodedText: string) {
        const now = Date.now()
        if (decodedText === lastDecodedText.value && now - lastDecodedAt.value < 2500) {
            return
        }

        lastDecodedText.value = decodedText
        lastDecodedAt.value = now

        const candidate = extractQrCandidate(decodedText)
        await submitScanPayload({ raw_payload: candidate }, 'camera', candidate)
    }

    async function startCameraScanner() {
        if (isStartingCamera.value || isCameraReady.value || !sessionId()) {
            return
        }

        isStartingCamera.value = true
        permissionError.value = ''

        try {
            const devices = await Html5Qrcode.getCameras()
            cameras.value = devices.map((device) => ({ id: device.id, label: device.label || device.id }))
            selectedCameraId.value = cameras.value[0]?.id ?? ''

            if (!selectedCameraId.value) {
                permissionError.value = 'Kamera tidak ditemukan.'
                return
            }

            scanner.value = new Html5Qrcode(scannerContainerId)
            await scanner.value.start(
                selectedCameraId.value,
                { fps: 10, qrbox: { width: 260, height: 260 } },
                (decodedText) => {
                    void onQrDecoded(decodedText)
                },
                () => {},
            )
            isCameraReady.value = true
        } catch {
            permissionError.value = 'Izin kamera diperlukan untuk scan QR.'
        } finally {
            isStartingCamera.value = false
        }
    }

    async function stopCameraScanner() {
        if (scanner.value && isCameraReady.value) {
            await scanner.value.stop()
            scanner.value.clear()
            scanner.value = null
            isCameraReady.value = false
        }
    }

    async function switchCamera(cameraId: string) {
        selectedCameraId.value = cameraId
        await stopCameraScanner()
        await startCameraScanner()
    }

    function clearHistory() {
        scanHistory.value = []
        scanResult.value = null
    }

    onMounted(() => {
        void Html5Qrcode.getCameras()
            .then((devices) => {
                cameras.value = devices.map((device) => ({ id: device.id, label: device.label || device.id }))
                selectedCameraId.value = cameras.value[0]?.id ?? ''
            })
            .catch(() => {})
    })

    onBeforeUnmount(() => {
        void stopCameraScanner()
    })

    return {
        scannerContainerId,
        eventLabel: computed(() => sessionLabel()),
        cameras,
        selectedCameraId,
        isCameraReady,
        isStartingCamera,
        permissionError,
        manualQrInput,
        registrationCodeInput,
        scanResult,
        scanHistory,
        scanBusy,
        successfulScansCount,
        duplicateScansCount,
        invalidScansCount,
        switchCamera,
        startCameraScanner,
        stopCameraScanner,
        submitManualCode,
        clearHistory,
    }
}
