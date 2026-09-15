<script setup lang="ts">
import { computed, onMounted, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import QrScanScannerCard from '@/components/modules/dashboard/QrScanScannerCard.vue'
import QrScanSidebar from '@/components/modules/dashboard/QrScanSidebar.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { SimpleSelectOption } from '@/components/ui/simple-select'
import { useGlobalQrScanPage } from '@/utils/composables/useGlobalQrScanPage'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    targets: {
        sessions: Array<{ id: string } & Record<string, unknown>>
        events: Array<{ id: string | number } & Record<string, unknown>>
    }
    globalScanStoreUrl: string
    globalScanFeedUrl?: string
}>()

const feedUrl = computed<string>(() => {
    const explicit = props.globalScanFeedUrl?.trim() ?? ''
    if (explicit.length > 0) {
        return explicit
    }

    return `${props.globalScanStoreUrl.replace(/\/+$/, '')}/feed`
})

const s = reactive(
    useGlobalQrScanPage('global-qr-scanner-region', props.globalScanStoreUrl, feedUrl.value, () => props.targets),
)

const targetFilterOptions = computed<SimpleSelectOption[]>(() => [
    { value: 'all', label: 'Semua acara' },
    ...s.targetOptions.map((option) => ({
        value: option.id,
        label: `${option.kind === 'oprec' ? 'OPREC' : 'EVENT'} · ${option.label}`,
    })),
])

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
        <Card class="rounded-2xl border border-border/70">
            <CardHeader class="pb-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle class="text-base font-semibold">Ringkasan Hari Ini</CardTitle>
                    <p class="text-muted-foreground text-xs">
                        Aktif hari ini ({{ s.activeTargetCount }}) — {{ props.targets.sessions.length }} sesi oprec ·
                        {{ props.targets.events.length }} event
                    </p>
                </div>
            </CardHeader>
            <CardContent class="pt-0">
                <div class="grid gap-2 sm:grid-cols-3">
                    <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                        <p class="text-muted-foreground text-xs">Check-in berhasil</p>
                        <p class="text-success text-lg font-semibold">{{ s.successfulScansCount }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                        <p class="text-muted-foreground text-xs">Sudah scan</p>
                        <p class="text-warning text-lg font-semibold">{{ s.duplicateScansCount }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                        <p class="text-muted-foreground text-xs">Tidak valid</p>
                        <p class="text-destructive text-lg font-semibold">{{ s.invalidScansCount }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
            <QrScanScannerCard
                v-model:target-filter="s.selectedTarget"
                :scanner-container-id="s.scannerContainerId"
                :event-label="s.eventLabel"
                :cameras="s.cameras"
                :selected-camera-id="s.selectedCameraId"
                :is-starting-camera="s.isStartingCamera"
                :is-camera-ready="s.isCameraReady"
                :is-shutter-active="s.isShutterActive"
                :permission-error="s.permissionError"
                :target-options="targetFilterOptions"
                @switch-camera="s.switchCamera"
                @start-camera="s.startCameraScanner"
                @stop-camera="s.stopCameraScanner"
            />

            <QrScanSidebar
                v-model:registration-code-input="s.registrationCodeInput"
                :scan-result="s.scanResult"
                :scan-history="s.scanHistory"
                :log-entries="s.logEntries"
                :scan-busy="s.scanBusy"
                :log-expanded="s.logExpanded"
                :log-query="s.logQuery"
                @toggle-log="toggleLog"
                @update:logQuery="handleLogQuery"
                @clear-history="s.clearHistory"
                @submit-manual="s.submitManualCode"
            />
        </div>
    </div>
</template>
