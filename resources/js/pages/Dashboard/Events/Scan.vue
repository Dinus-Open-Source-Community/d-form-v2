<script setup lang="ts">
import { reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import QrScanInstructionsCard from '@/components/modules/dashboard/QrScanInstructionsCard.vue'
import QrScanScannerCard from '@/components/modules/dashboard/QrScanScannerCard.vue'
import QrScanSidebar from '@/components/modules/dashboard/QrScanSidebar.vue'
import { useEventQrScanPage } from '@/utils/composables/useEventQrScanPage'

defineOptions({ layout: DashboardFocusLayout })

const s = reactive(useEventQrScanPage('event-qr-scanner-region'))
</script>

<template>
    <Head title="Scanner Absensi QR" />

    <div class="flex flex-col gap-5">
        <PageHeader
            title="Scanner Absensi QR"
            subtitle="Halaman ini khusus check-in peserta. Backend belum diaktifkan, jadi validasi masih berbasis data contoh di frontend."
        />

        <QrScanInstructionsCard />

        <div class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
            <QrScanScannerCard
                v-model:manual-qr-input="s.manualQrInput"
                :scanner-container-id="s.scannerContainerId"
                :event-uid="s.eventUid"
                :cameras="s.cameras"
                :selected-camera-id="s.selectedCameraId"
                :is-starting-camera="s.isStartingCamera"
                :is-camera-ready="s.isCameraReady"
                :permission-error="s.permissionError"
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
