<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { toast } from 'vue-sonner'
import { Html5Qrcode } from 'html5-qrcode'
import {
    AlertTriangle,
    Camera,
    CheckCircle,
    CircleHelp,
    Clock3,
    QrCode,
    ScanLine,
    ShieldAlert,
    XCircle,
} from 'lucide-vue-next'

defineOptions({ layout: DashboardFocusLayout })

type ScanStatus = 'success' | 'already' | 'invalid'

interface MockRegistrant {
    id: string
    name: string
    email: string
    qrTokens: string[]
}

interface ScanEntry {
    id: string
    name: string
    email: string
    time: string
    status: ScanStatus
    source: 'camera' | 'manual'
}

interface ScanResult {
    name: string
    email: string
    status: ScanStatus
    source: 'camera' | 'manual'
    rawCode: string
}

const page = usePage()
const scannerContainerId = 'event-qr-scanner-region'
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

const mockRegistrants: MockRegistrant[] = [
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

const statusConfig: Record<ScanStatus, { icon: unknown; class: string; bg: string; label: string }> = {
    success: { icon: CheckCircle, class: 'text-success', bg: 'bg-success/10', label: 'Check-in berhasil' },
    already: { icon: AlertTriangle, class: 'text-warning', bg: 'bg-warning/10', label: 'Sudah pernah scan' },
    invalid: { icon: XCircle, class: 'text-destructive', bg: 'bg-destructive/10', label: 'QR tidak valid' },
}

function normalizeCode(raw: string): string {
    return raw.trim().toLowerCase()
}

function extractQrCandidate(decodedText: string): string {
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

function createHistoryEntry(result: ScanResult): ScanEntry {
    return {
        id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
        name: result.name,
        email: result.email,
        time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
        status: result.status,
        source: result.source,
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
    const normalizedCandidate = normalizeCode(qrCandidate)

    const registrant = mockRegistrants.find((item) =>
        item.qrTokens.some((token) => normalizeCode(token) === normalizedCandidate),
    )

    if (!registrant) {
        scanResult.value = {
            name: 'QR tidak dikenali',
            email: '-',
            status: 'invalid',
            source,
            rawCode: qrCandidate,
        }

        scanHistory.value.unshift(createHistoryEntry(scanResult.value))
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

    scanHistory.value.unshift(createHistoryEntry(scanResult.value))

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
                // Scanner mengirim callback berkala saat belum ada QR.
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

async function switchCamera(nextCameraId: string) {
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
</script>

<template>
    <Head title="Scanner Absensi QR" />

    <div class="flex flex-col gap-5">
        <PageHeader
            title="Scanner Absensi QR"
            subtitle="Halaman ini khusus check-in peserta. Backend belum diaktifkan, jadi validasi masih berbasis data contoh di frontend."
        />

        <Card class="rounded-2xl border border-border/70 bg-muted/15">
            <CardContent class="grid gap-3 p-4 text-sm md:grid-cols-3 md:p-5">
                <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Langkah 1</p>
                    <p class="mt-1 font-medium text-foreground">Pilih kamera lalu klik tombol Mulai kamera.</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Langkah 2</p>
                    <p class="mt-1 font-medium text-foreground">Arahkan QR peserta ke kotak scanner sampai terbaca.</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Langkah 3</p>
                    <p class="mt-1 font-medium text-foreground">Lihat hasil scan dan toast notifikasi di kanan atas.</p>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
            <Card class="overflow-hidden rounded-2xl border border-border/70">
                <CardHeader class="gap-3 border-b bg-muted/20">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <CardTitle class="text-base font-semibold">Area Scanner</CardTitle>
                        <Badge variant="outline" class="text-[11px]">Event UID: {{ eventUid }}</Badge>
                    </div>
                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto]">
                        <Select :model-value="selectedCameraId" @update:model-value="switchCamera">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Pilih kamera" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="camera in cameras" :key="camera.id" :value="camera.id">
                                    {{ camera.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Button class="md:min-w-36" :disabled="isStartingCamera || isCameraReady || !selectedCameraId" @click="startCameraScanner">
                            <Camera data-icon="inline-start" />
                            {{ isStartingCamera ? 'Menyalakan...' : 'Mulai kamera' }}
                        </Button>

                        <Button
                            variant="outline"
                            class="md:min-w-36"
                            :disabled="!isCameraReady"
                            @click="stopCameraScanner"
                        >
                            <ShieldAlert data-icon="inline-start" />
                            Stop kamera
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4 p-4 md:p-5">
                    <div class="relative overflow-hidden rounded-2xl border border-dashed border-border bg-muted/30 p-3">
                        <div :id="scannerContainerId" class="mx-auto min-h-80 w-full max-w-[560px] rounded-xl bg-background" />
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                            <div class="rounded-xl border-2 border-primary/35 px-8 py-10">
                                <ScanLine class="size-9 text-primary/70" />
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="permissionError"
                        class="rounded-xl border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive"
                    >
                        {{ permissionError }}
                    </div>

                    <div class="rounded-xl border border-border/70 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Fallback manual (tanpa kamera)</p>
                        <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                            <Input
                                v-model="manualQrInput"
                                placeholder="Tempel isi QR di sini, contoh: DOSCOM-2026-REG-001"
                                @keydown.enter.prevent="submitManualCode"
                            />
                            <Button variant="outline" class="sm:min-w-36" @click="submitManualCode">
                                <QrCode data-icon="inline-start" />
                                Proses manual
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-3">
                        <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                            <p class="text-xs text-muted-foreground">Check-in berhasil</p>
                            <p class="text-lg font-semibold text-success">{{ successfulScansCount }}</p>
                        </div>
                        <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                            <p class="text-xs text-muted-foreground">Sudah scan</p>
                            <p class="text-lg font-semibold text-warning">{{ duplicateScansCount }}</p>
                        </div>
                        <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                            <p class="text-xs text-muted-foreground">Tidak valid</p>
                            <p class="text-lg font-semibold text-destructive">{{ invalidScansCount }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-col gap-5">
                <Card class="rounded-2xl border border-border/70">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-semibold">Hasil Scan Terakhir</CardTitle>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <div v-if="scanResult" :class="['rounded-xl p-3', statusConfig[scanResult.status].bg]">
                            <div class="flex items-start gap-3">
                                <component :is="statusConfig[scanResult.status].icon" :class="['mt-0.5 size-5', statusConfig[scanResult.status].class]" />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-foreground">{{ scanResult.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ scanResult.email }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <Badge variant="outline" :class="statusConfig[scanResult.status].class">
                                            {{ statusConfig[scanResult.status].label }}
                                        </Badge>
                                        <Badge variant="outline">{{ scanResult.source === 'camera' ? 'Kamera' : 'Manual' }}</Badge>
                                    </div>
                                    <p class="mt-2 truncate text-xs text-muted-foreground">Raw code: {{ scanResult.rawCode }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="rounded-xl border border-dashed border-border/80 bg-muted/20 px-3 py-6 text-center text-sm text-muted-foreground">
                            Belum ada scan. Mulai kamera atau gunakan input manual.
                        </div>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border border-border/70">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between gap-3">
                            <CardTitle class="text-base font-semibold">Riwayat Scan</CardTitle>
                            <Button variant="ghost" size="sm" class="h-8 text-xs" :disabled="scanHistory.length === 0" @click="clearHistory">
                                Bersihkan
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <div v-if="scanHistory.length > 0" class="max-h-[420px] space-y-2 overflow-y-auto pr-1">
                            <div
                                v-for="entry in scanHistory"
                                :key="entry.id"
                                class="rounded-xl border border-border/70 bg-background px-3 py-2.5"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-foreground">{{ entry.name }}</p>
                                        <p class="truncate text-xs text-muted-foreground">{{ entry.email }}</p>
                                    </div>
                                    <span class="flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                                        <Clock3 class="size-3.5" />
                                        {{ entry.time }}
                                    </span>
                                </div>

                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <Badge variant="outline" :class="statusConfig[entry.status].class">
                                        {{ statusConfig[entry.status].label }}
                                    </Badge>
                                    <Badge variant="secondary" class="text-[11px]">
                                        {{ entry.source === 'camera' ? 'Kamera' : 'Manual' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        <p v-else class="rounded-xl border border-dashed border-border/80 px-3 py-8 text-center text-sm text-muted-foreground">
                            Belum ada riwayat scan.
                        </p>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border border-border/70">
                    <CardContent class="p-4">
                        <div class="flex items-start gap-2.5">
                            <CircleHelp class="mt-0.5 size-4 text-muted-foreground" />
                            <div class="space-y-1 text-xs text-muted-foreground">
                                <p class="font-medium text-foreground">Catatan mode frontend-only</p>
                                <p>Validasi QR saat ini memakai data mock pada halaman ini, belum tersambung ke database/backend.</p>
                                <p>Contoh kode yang valid: <span class="font-medium text-foreground">DOSCOM-2026-REG-001</span></p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
