<script setup lang="ts">
import { onMounted, reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import KpiCard from '@/components/modules/dashboard/KpiCard.vue';
import EventReportingFocusPanel from '@/components/modules/dashboard/EventReportingFocusPanel.vue';
import ExportDownloadStatus from '@/components/modules/dashboard/ExportDownloadStatus.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { BarChart3, ClipboardList, ScanLine, Download } from 'lucide-vue-next';
import { setTopbar } from '@/hooks/useDashboardTopbar';
import { useExportDownload } from '@/hooks/useExportDownload';

defineOptions({ layout: DashboardFocusLayout });

const props = defineProps<{
    globalSummary: {
        total_events: number;
        total_submissions: number;
        total_attendance_records: number;
    };
    event: IEvent;
    exports: { registrations: string; attendance: string };
    eventReporting: {
        summary: {
            submission_count: number;
            attended_count: number;
            attendance_rate_percent: number | null;
            registered_count: number;
            quota: number | null;
        };
        attendanceLog: {
            data: {
                id: string;
                scanned_at: string;
                form_answer_id: string;
                attendee: { name: string; email: string } | null;
                scanned_by: { name: string; email: string } | null;
            }[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            links?: { url: string | null; label: string; active: boolean }[];
        };
    };
}>();

onMounted(() => {
    setTopbar({ title: 'Laporan', subtitle: 'Unduhan CSV & ringkasan acara' });
});

/** Satu state unduhan per tombol export; keduanya GET download terpisah. */
const registrationsExport = reactive(useExportDownload());
const attendanceExport = reactive(useExportDownload());

function downloadRegistrations(): void {
    void registrationsExport.start([props.exports.registrations]);
}

function downloadAttendance(): void {
    void attendanceExport.start([props.exports.attendance]);
}
</script>

<template>
    <Head :title="`Laporan — ${props.event.title}`" />

    <div class="flex flex-col gap-8 md:gap-10">
        <div class="grid gap-4 sm:grid-cols-3">
            <KpiCard label="Events" :value="globalSummary.total_events" :icon="BarChart3" color="primary" />
            <KpiCard
                label="All submissions"
                :value="globalSummary.total_submissions"
                :icon="ClipboardList"
                color="warning"
            />
            <KpiCard
                label="Attendance records"
                :value="globalSummary.total_attendance_records"
                :icon="ScanLine"
                color="success"
            />
        </div>

        <Card class="rounded-xl border shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-medium">Focus event</CardTitle>
                <CardDescription class="text-xs">
                    Laporan untuk acara berikut beserta unduhan CSV pendaftaran dan kehadiran.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex min-w-[220px] flex-1 flex-col gap-1.5">
                    <Label for="laporan-event-title" class="text-muted-foreground text-xs font-semibold uppercase"
                        >Event</Label
                    >
                    <p
                        id="laporan-event-title"
                        class="border-input bg-muted/30 text-foreground flex min-h-10 items-center rounded-md border px-3 text-sm font-medium shadow-xs"
                    >
                        {{ event.title }}
                    </p>
                </div>
                <div class="flex flex-col gap-2">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="registrationsExport.isRunning"
                            @click="downloadRegistrations"
                        >
                            <Download class="mr-1.5 size-4" />Registrations CSV
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="attendanceExport.isRunning"
                            @click="downloadAttendance"
                        >
                            <Download class="mr-1.5 size-4" />Attendance CSV
                        </Button>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <ExportDownloadStatus
                            :phase="registrationsExport.phase"
                            label="Registrations CSV"
                            :progress="registrationsExport.progress"
                            :indeterminate="registrationsExport.indeterminate"
                            :loaded-bytes="registrationsExport.loadedBytes"
                            :direct-count="registrationsExport.directCount"
                            :error-message="registrationsExport.errorMessage"
                            @cancel="registrationsExport.cancel()"
                        />
                        <ExportDownloadStatus
                            :phase="attendanceExport.phase"
                            label="Attendance CSV"
                            :progress="attendanceExport.progress"
                            :indeterminate="attendanceExport.indeterminate"
                            :loaded-bytes="attendanceExport.loadedBytes"
                            :direct-count="attendanceExport.directCount"
                            :error-message="attendanceExport.errorMessage"
                            @cancel="attendanceExport.cancel()"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <EventReportingFocusPanel
            :event-title="event.title"
            :summary="eventReporting.summary"
            :export-urls="exports"
            :attendance-log="eventReporting.attendanceLog"
            :show-export-toolbar="false"
        />
    </div>
</template>
