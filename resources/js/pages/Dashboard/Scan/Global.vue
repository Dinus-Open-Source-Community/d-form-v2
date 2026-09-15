<script setup lang="ts">
import { computed, onMounted, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import QrScanInstructionsCard from '@/components/modules/dashboard/QrScanInstructionsCard.vue'
import QrScanScannerCard from '@/components/modules/dashboard/QrScanScannerCard.vue'
import QrScanSidebar from '@/components/modules/dashboard/QrScanSidebar.vue'
import { Button } from '@/components/ui/button'
import { useGlobalQrScanPage } from '@/utils/composables/useGlobalQrScanPage'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    targets: {
        sessions: Array<{ id: string } & Record<string, unknown>>
        events: Array<{ id: string | number } & Record<string, unknown>>
    }
    globalScanStoreUrl: string
    globalScanStreamUrl: string
}>()

const s = reactive(
    useGlobalQrScanPage('global-qr-scanner-region', props.globalScanStoreUrl, props.globalScanStreamUrl, props.targets),
)

interface GlobalScanSummaryRow {
    id: string
    title: string
    kind: 'event' | 'oprec'
    success: number
    duplicate: number
    invalid: number
}

const summaryRows = computed<GlobalScanSummaryRow[]>(() => {
    const optionIdByKindTitle = new Map<string, string>()
    for (const option of s.targetOptions) {
        const key = `${option.kind}::${option.label}`
        if (!optionIdByKindTitle.has(key)) {
            optionIdByKindTitle.set(key, option.id)
        }
    }

    const rows = new Map<string, GlobalScanSummaryRow>()
    for (const entry of s.scanHistory) {
        const title = entry.eventTitle !== '' && entry.eventTitle !== '-' ? entry.eventTitle : '(Tanpa acara)'
        const key = `${entry.eventKind}::${title}`
        let row = rows.get(key)
        if (row === undefined) {
            row = {
                id: optionIdByKindTitle.get(key) ?? title,
                title,
                kind: entry.eventKind,
                success: 0,
                duplicate: 0,
                invalid: 0,
            }
            rows.set(key, row)
        }

        if (entry.status === 'success') {
            row.success += 1
        }
        else if (entry.status === 'already') {
            row.duplicate += 1
        }
        else {
            row.invalid += 1
        }
    }

    return [...rows.values()]
})

function handleSelectTarget(id: string | null): void {
    if (id === null || id === s.selectedTarget) {
        s.selectTarget('all')

        return
    }

    s.selectTarget(id)
}

function toggleLog(): void {
    s.logExpanded = !s.logExpanded
}

function handleLogQuery(value: string): void {
    s.logQuery = value
}

onMounted(() => {
    setTopbar({ title: 'Scanner Global', subtitle: 'Pindai QR apapun' })
})
</script>

<template>
    <Head title="Scanner Global" />

    <div class="flex flex-col gap-5">
        <div class="rounded-2xl border border-border/70 bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
            Aktif hari ini ({{ s.activeTargetCount }}) — {{ props.targets.sessions.length }} sesi oprec ·
            {{ props.targets.events.length }} event
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                type="button"
                size="sm"
                :variant="s.selectedTarget === 'all' ? 'default' : 'outline'"
                @click="s.selectTarget('all')"
            >
                Semua
            </Button>
            <Button
                v-for="option in s.targetOptions"
                :key="`${option.kind}-${option.id}`"
                type="button"
                size="sm"
                :variant="s.selectedTarget === option.id ? 'default' : 'outline'"
                @click="s.selectTarget(option.id)"
            >
                {{ option.label }}
            </Button>
        </div>

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
                :scan-busy="s.scanBusy"
                :successful-scans-count="s.successfulScansCount"
                :duplicate-scans-count="s.duplicateScansCount"
                :invalid-scans-count="s.invalidScansCount"
                @switch-camera="s.switchCamera"
                @start-camera="s.startCameraScanner"
                @stop-camera="s.stopCameraScanner"
                @submit-manual="s.submitManualCode"
            />

            <QrScanSidebar
                :scan-result="s.scanResult"
                :summary="summaryRows"
                :scan-history="s.scanHistory"
                :selected-target="s.selectedTarget"
                :log-expanded="s.logExpanded"
                :log-query="s.logQuery"
                @select-target="handleSelectTarget"
                @toggle-log="toggleLog"
                @update:logQuery="handleLogQuery"
                @clear-history="s.clearHistory"
            />
        </div>
    </div>
</template>
