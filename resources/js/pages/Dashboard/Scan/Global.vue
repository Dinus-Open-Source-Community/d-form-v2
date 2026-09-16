<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import QrScanScannerCard from '@/components/modules/dashboard/QrScanScannerCard.vue';
import QrScanSidebar from '@/components/modules/dashboard/QrScanSidebar.vue';
import ScanExportDialog, {
    type ScanExportFormat,
    type ScanExportTarget,
} from '@/components/modules/dashboard/ScanExportDialog.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select';
import { FileSpreadsheet, FileText } from 'lucide-vue-next';
import { routes } from '@/lib/routes';
import { useGlobalQrScanPage } from '@/utils/composables/useGlobalQrScanPage';
import { setTopbar } from '@/utils/composables/useDashboardTopbar';

defineOptions({ layout: DashboardFocusLayout });

/** Jeda antar-unduhan agar browser tidak menganggapnya spam unduhan otomatis. */
const EXPORT_DOWNLOAD_STAGGER_MS = 400;

const props = defineProps<{
    targets: {
        sessions: Array<{ id: string } & Record<string, unknown>>;
        events: Array<{ id: string | number } & Record<string, unknown>>;
    };
    globalScanStoreUrl: string;
    globalScanFeedUrl?: string;
}>();

const feedUrl = computed<string>(() => {
    const explicit = props.globalScanFeedUrl?.trim() ?? '';
    if (explicit.length > 0) {
        return explicit;
    }

    return `${props.globalScanStoreUrl.replace(/\/+$/, '')}/feed`;
});

const s = reactive(
    useGlobalQrScanPage('global-qr-scanner-region', props.globalScanStoreUrl, feedUrl.value, () => props.targets)
);

const targetFilterOptions = computed<SimpleSelectOption[]>(() => [
    { value: 'all', label: 'Semua acara' },
    ...s.targetOptions.map((option) => ({
        value: option.id,
        label: `${option.kind === 'oprec' ? 'OPREC' : 'EVENT'} · ${option.label}`,
    })),
]);

const heroEmptyMessage = computed<string>(() =>
    s.selectedTarget === 'all'
        ? 'Belum ada scan. Mulai kamera atau gunakan input manual.'
        : `Belum ada scan untuk ${s.selectedTargetLabel}.`
);

const exportDialogOpen = ref(false);
const exportFormat = ref<ScanExportFormat>('xlsx');
const isExporting = ref(false);

function triggerBrowserDownload(url: string): void {
    const link = document.createElement('a');
    link.href = url;
    link.download = '';
    link.rel = 'noopener';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    window.setTimeout(() => link.remove(), 1500);
}

function downloadScanExports(targets: ScanExportTarget[], format: ScanExportFormat): void {
    if (targets.length === 0) {
        return;
    }

    isExporting.value = true;

    targets.forEach((target, index) => {
        window.setTimeout(() => {
            triggerBrowserDownload(routes.admin.scan.export({ kind: target.kind, target: target.id, format }));

            if (index === targets.length - 1) {
                isExporting.value = false;
                toast.success(
                    targets.length === 1
                        ? `Export ${format.toUpperCase()} diunduh.`
                        : `${targets.length} file ${format.toUpperCase()} sedang diunduh.`,
                    {
                        description:
                            targets.length > 1
                                ? 'Jika browser meminta izin, pilih "Allow" agar semua file terunduh.'
                                : undefined,
                    }
                );
            }
        }, index * EXPORT_DOWNLOAD_STAGGER_MS);
    });
}

function requestScanExport(format: ScanExportFormat): void {
    const selected = s.selectedTargetOption;
    if (selected !== null) {
        downloadScanExports([selected], format);

        return;
    }

    exportFormat.value = format;
    exportDialogOpen.value = true;
}

function confirmScanExport(targets: ScanExportTarget[]): void {
    downloadScanExports(targets, exportFormat.value);
}

function toggleLog(): void {
    s.logExpanded = !s.logExpanded;
}

function handleLogQuery(value: string): void {
    s.logQuery = value;
}

onMounted(() => {
    setTopbar({ title: 'Scanner Attendeance', subtitle: 'Pindai QR apapun' });
});
</script>

<template>
    <Head title="Scanner Global" />

    <div class="flex flex-col gap-5">
        <section class="flex flex-col gap-4">
            <div class="flex flex-wrap items-end justify-end gap-3">
                <div class="flex flex-wrap items-end gap-2">
                    <div class="w-full space-y-1.5 sm:w-60">
                        <Label
                            for="scan-target-filter"
                            class="text-muted-foreground text-xs font-semibold tracking-wide uppercase"
                        >
                            Filter acara
                        </Label>
                        <SimpleSelect
                            v-model="s.selectedTarget"
                            :options="targetFilterOptions"
                            id="scan-target-filter"
                            placeholder="Semua acara"
                            class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                            aria-label="Filter KPI, hasil scan terakhir, dan riwayat scan per acara"
                        />
                    </div>

                    <Button
                        variant="outline"
                        class="md:min-w-32"
                        :disabled="isExporting"
                        @click="requestScanExport('xlsx')"
                    >
                        <FileSpreadsheet data-icon="inline-start" />
                        Export Excel
                    </Button>
                    <Button
                        variant="outline"
                        class="md:min-w-32"
                        :disabled="isExporting"
                        @click="requestScanExport('csv')"
                    >
                        <FileText data-icon="inline-start" />
                        Export CSV
                    </Button>
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-3">
                <div class="border-border/70 rounded-xl border px-3 py-2.5">
                    <p class="text-muted-foreground text-xs">Check-in berhasil</p>
                    <p class="text-success text-lg font-semibold">{{ s.successfulScansCount }}</p>
                </div>
                <div class="border-border/70 rounded-xl border px-3 py-2.5">
                    <p class="text-muted-foreground text-xs">Sudah scan</p>
                    <p class="text-warning text-lg font-semibold">{{ s.duplicateScansCount }}</p>
                </div>
                <div class="border-border/70 rounded-xl border px-3 py-2.5">
                    <p class="text-muted-foreground text-xs">Tidak valid</p>
                    <p class="text-destructive text-lg font-semibold">{{ s.invalidScansCount }}</p>
                </div>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
            <QrScanScannerCard
                :scanner-container-id="s.scannerContainerId"
                :event-label="s.eventLabel"
                :cameras="s.cameras"
                :selected-camera-id="s.selectedCameraId"
                :is-starting-camera="s.isStartingCamera"
                :is-camera-ready="s.isCameraReady"
                :is-shutter-active="s.isShutterActive"
                :permission-error="s.permissionError"
                @switch-camera="s.switchCamera"
                @start-camera="s.startCameraScanner"
                @stop-camera="s.stopCameraScanner"
            />

            <QrScanSidebar
                v-model:registration-code-input="s.registrationCodeInput"
                :scan-result="s.heroResult"
                :scan-history="s.scanHistory"
                :log-entries="s.logEntries"
                :hero-empty-message="heroEmptyMessage"
                :scan-busy="s.scanBusy"
                :log-expanded="s.logExpanded"
                :log-query="s.logQuery"
                @toggle-log="toggleLog"
                @update:logQuery="handleLogQuery"
                @clear-history="s.clearHistory"
                @submit-manual="s.submitManualCode"
            />
        </div>

        <ScanExportDialog
            v-model:open="exportDialogOpen"
            :options="s.targetOptions"
            :format="exportFormat"
            @confirm="confirmScanExport"
        />
    </div>
</template>
