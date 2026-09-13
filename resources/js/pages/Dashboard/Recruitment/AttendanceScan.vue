<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import QrScanInstructionsCard from '@/components/modules/dashboard/QrScanInstructionsCard.vue'
import QrScanScannerCard from '@/components/modules/dashboard/QrScanScannerCard.vue'
import QrScanSidebar from '@/components/modules/dashboard/QrScanSidebar.vue'
import { useRecruitmentQrScanPage } from '@/utils/composables/useRecruitmentQrScanPage'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import { Label } from '@/components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'

defineOptions({ layout: DashboardFocusLayout })

interface SessionOption {
    id: string
    session_date: string
    starts_at: string
    ends_at: string
    location: string
    room: string
    division: { name: string } | null
    period: { name: string } | null
}

const props = defineProps<{
    sessions: SessionOption[]
    attendanceScanStoreUrl: string
}>()

const selectedSessionId = ref(props.sessions[0]?.id ?? '')

const selectedSession = computed(() => props.sessions.find((s) => s.id === selectedSessionId.value) ?? null)

const s = reactive(
    useRecruitmentQrScanPage(
        'recruitment-qr-scanner-region',
        props.attendanceScanStoreUrl,
        () => selectedSessionId.value,
        () => {
            const session = selectedSession.value
            if (!session) {
                return 'Pilih sesi interview'
            }

            return `${session.division?.name ?? 'Interview'} · ${session.session_date}`
        },
    ),
)

onMounted(() => {
    setTopbar({ title: 'Scanner Absensi OpRec', subtitle: 'Pindai QR atau masukkan nomor pendaftaran' })
})
</script>

<template>
    <Head title="Scanner Absensi OpRec" />

    <div class="flex flex-col gap-5">
        <div v-if="sessions.length > 0" class="max-w-md space-y-2">
            <Label for="session">Sesi interview</Label>
            <Select v-model="selectedSessionId">
                <SelectTrigger id="session">
                    <SelectValue placeholder="Pilih sesi" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="session in sessions" :key="session.id" :value="session.id">
                        {{ session.division?.name ?? 'Interview' }} · {{ session.session_date }}
                        {{ session.starts_at }}–{{ session.ends_at }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <p v-else class="text-muted-foreground text-sm">Tidak ada sesi interview aktif.</p>

        <QrScanInstructionsCard />

        <div class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
            <QrScanScannerCard
                v-model:manual-qr-input="s.manualQrInput"
                v-model:registration-code-input="s.registrationCodeInput"
                :scanner-container-id="s.scannerContainerId"
                :event-label="s.eventLabel"
                :cameras="s.cameras"
                :selected-camera-id="s.selectedCameraId"
                :is-starting-camera="s.isStartingCamera"
                :is-camera-ready="s.isCameraReady"
                :permission-error="s.permissionError"
                :scan-busy="s.scanBusy || !selectedSessionId"
                :successful-scans-count="s.successfulScansCount"
                :duplicate-scans-count="s.duplicateScansCount"
                :invalid-scans-count="s.invalidScansCount"
                @switch-camera="s.switchCamera"
                @start-camera="s.startCameraScanner"
                @stop-camera="s.stopCameraScanner"
                @submit-manual="s.submitManualCode"
            />

            <QrScanSidebar :scan-result="s.scanResult" :scan-history="s.scanHistory" @clear-history="s.clearHistory" />
        </div>
    </div>
</template>
