<script setup lang="ts">
import KpiCard from '@/components/modules/dashboard/KpiCard.vue'
import AppPagination from '@/components/core/AppPagination.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { ClipboardList, ScanLine, Users, Download } from 'lucide-vue-next'
import { formatDateTime } from '@/lib/dateTime'

const props = withDefaults(
    defineProps<{
        eventTitle: string
        summary: {
            submission_count: number
            attended_count: number
            attendance_rate_percent: number | null
            registered_count: number
            quota: number | null
        }
        exportUrls: { registrations: string; attendance: string }
        attendanceLog: {
            data: {
                id: string
                scanned_at: string
                form_answer_id: string
                attendee: { name: string; email: string } | null
                scanned_by: { name: string; email: string } | null
            }[]
            current_page: number
            last_page: number
            per_page: number
            total: number
            links?: { url: string | null; label: string; active: boolean }[]
        }
        /** When false, parent renders CSV actions elsewhere (e.g. next to an event picker). */
        showExportToolbar?: boolean
    }>(),
    { showExportToolbar: true },
)

</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="props.showExportToolbar" class="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" as-child class="">
                <a :href="exportUrls.registrations">
                    <Download class="mr-1.5 size-4" />Registrations CSV
                </a>
            </Button>
            <Button variant="outline" size="sm" as-child class="">
                <a :href="exportUrls.attendance">
                    <Download class="mr-1.5 size-4" />Attendance CSV
                </a>
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <KpiCard label="Submissions (all forms)" :value="summary.submission_count" :icon="ClipboardList" color="primary" />
            <KpiCard label="Attended (scanned)" :value="summary.attended_count" :icon="ScanLine" color="success" />
            <KpiCard
                label="Attendance rate"
                :value="summary.attendance_rate_percent != null ? `${summary.attendance_rate_percent}%` : '—'"
                :icon="Users"
                color="warning"
            />
            <KpiCard
                label="Registered / quota"
                :value="summary.quota != null ? `${summary.registered_count} / ${summary.quota}` : String(summary.registered_count)"
                :icon="Users"
                color="primary"
            />
        </div>

        <Card class="rounded-xl border shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-medium">Attendance log</CardTitle>
                <CardDescription class="text-xs">
                    Rows from <code class="rounded bg-muted px-1 py-0.5 text-[11px]">event_attendances</code>
                    for {{ eventTitle }}.
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
                            <TableCell class="whitespace-nowrap text-sm">{{ formatDateTime({ value: row.scanned_at, variant: 'date-time-medium' }) }}</TableCell>
                            <TableCell>
                                <template v-if="row.attendee">
                                    <span class="block text-sm font-medium">{{ row.attendee.name }}</span>
                                    <span class="text-xs text-muted-foreground">{{ row.attendee.email }}</span>
                                </template>
                                <span v-else class="text-sm text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell class="font-mono text-xs">{{ row.form_answer_id }}</TableCell>
                            <TableCell>
                                <template v-if="row.scanned_by">
                                    <span class="block text-sm">{{ row.scanned_by.name }}</span>
                                    <span class="text-xs text-muted-foreground">{{ row.scanned_by.email }}</span>
                                </template>
                                <span v-else class="text-sm text-muted-foreground">—</span>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="attendanceLog.data.length === 0">
                            <TableCell colspan="4" class="py-10 text-center text-sm text-muted-foreground">
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
                />
            </CardContent>
        </Card>
    </div>
</template>
