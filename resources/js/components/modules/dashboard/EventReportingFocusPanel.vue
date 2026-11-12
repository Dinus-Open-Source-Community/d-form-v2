<script setup lang="ts">
import { reactive } from 'vue';
import KpiCard from '@/components/modules/dashboard/KpiCard.vue';
import AppPagination from '@/components/core/AppPagination.vue';
import ExportDownloadStatus from '@/components/modules/dashboard/ExportDownloadStatus.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ClipboardList, ScanLine, Users, Download } from 'lucide-vue-next';
import { formatDateTime } from '@/lib/dateTime';
import { usePaginationNavigation } from '@/hooks/usePaginationNavigation';
import { useExportDownload } from '@/hooks/useExportDownload';

const props = withDefaults(
    defineProps<{
        eventTitle: string;
        summary: {
            submission_count: number;
            attended_count: number;
            attendance_rate_percent: number | null;
            registered_count: number;
            quota: number | null;
        };
        exportUrls: { registrations: string; attendance: string };
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
        /** When false, parent renders CSV actions elsewhere (e.g. next to an event picker). */
        showExportToolbar?: boolean;
    }>(),
    { showExportToolbar: true }
);

const { goToPage } = usePaginationNavigation();

/** Satu state unduhan per tombol: kedua export adalah GET download terpisah. */
const registrationsExport = reactive(useExportDownload());
const attendanceExport = reactive(useExportDownload());

function downloadRegistrations(): void {
    void registrationsExport.start([props.exportUrls.registrations]);
}

function downloadAttendance(): void {
    void attendanceExport.start([props.exportUrls.attendance]);
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="props.showExportToolbar" class="flex flex-col gap-2">
            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="registrationsExport.isRunning"
                    @click="downloadRegistrations"
                >
                    <Download class="mr-1.5 size-4" />Registrations CSV
                </Button>
                <Button variant="outline" size="sm" :disabled="attendanceExport.isRunning" @click="downloadAttendance">
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

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <KpiCard
                label="Submissions (all forms)"
                :value="summary.submission_count"
                :icon="ClipboardList"
                color="primary"
            />
            <KpiCard label="Attended (scanned)" :value="summary.attended_count" :icon="ScanLine" color="success" />
            <KpiCard
                label="Attendance rate"
                :value="summary.attendance_rate_percent != null ? `${summary.attendance_rate_percent}%` : '—'"
                :icon="Users"
                color="warning"
            />
            <KpiCard
                label="Registered / quota"
                :value="
                    summary.quota != null
                        ? `${summary.registered_count} / ${summary.quota}`
                        : String(summary.registered_count)
                "
                :icon="Users"
                color="primary"
            />
        </div>

        <Card class="rounded-xl border shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-medium">Attendance log</CardTitle>
                <CardDescription class="text-xs">
                    Rows from <code class="bg-muted rounded px-1 py-0.5 text-[11px]">event_attendances</code> for
                    {{ eventTitle }}.
                </CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto pt-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Scanned at</TableHead>
                            <TableHead>Attendee</TableHead>
                            <TableHead>Submission</TableHead>
                            <TableHead>Scanned by</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in attendanceLog.data" :key="row.id">
                            <TableCell class="text-sm whitespace-nowrap">{{
                                formatDateTime({ value: row.scanned_at, variant: 'date-time-medium' })
                            }}</TableCell>
                            <TableCell>
                                <template v-if="row.attendee">
                                    <span class="block text-sm font-medium">{{ row.attendee.name }}</span>
                                    <span class="text-muted-foreground text-xs">{{ row.attendee.email }}</span>
                                </template>
                                <span v-else class="text-muted-foreground text-sm">—</span>
                            </TableCell>
                            <TableCell class="font-mono text-xs">{{ row.form_answer_id }}</TableCell>
                            <TableCell>
                                <template v-if="row.scanned_by">
                                    <span class="block text-sm">{{ row.scanned_by.name }}</span>
                                    <span class="text-muted-foreground text-xs">{{ row.scanned_by.email }}</span>
                                </template>
                                <span v-else class="text-muted-foreground text-sm">—</span>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="attendanceLog.data.length === 0">
                            <TableCell colspan="4" class="text-muted-foreground py-10 text-center text-sm">
                                No attendance records yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <AppPagination
                    :current-page="attendanceLog.current_page"
                    :last-page="attendanceLog.last_page"
                    :total="attendanceLog.total"
                    :per-page="attendanceLog.per_page"
                    :per-page-options="[]"
                    @page-change="goToPage"
                />
            </CardContent>
        </Card>
    </div>
</template>
