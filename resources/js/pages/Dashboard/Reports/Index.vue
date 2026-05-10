<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import KpiCard from '@/components/modules/dashboard/KpiCard.vue'
import FormSubmissionsPagination from '@/components/modules/dashboard/FormSubmissionsPagination.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { BarChart3, Users, ClipboardList, ScanLine, Download } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

defineProps<{
    globalSummary: {
        total_events: number
        total_submissions: number
        total_attendance_records: number
    }
    events: { id: string; title: string; slug: string }[]
    selectedEventId: string | null
    selectedEvent: { id: string; title: string; slug: string } | null
    selectedEventSummary: {
        submission_count: number
        attended_count: number
        attendance_rate_percent: number | null
        registered_count: number
        quota: number | null
    } | null
    exportUrls: { registrations: string; attendance: string } | null
    attendanceLog:
        | {
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
        | null
}>()

function onEventChange(e: Event) {
    const el = e.target as HTMLSelectElement
    const id = el.value
    router.get('/admin/dashboard/reports', id ? { event: id } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function formatDt(iso: string) {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso))
}
</script>

<template>
    <Head title="Reports" />

    <div class="flex flex-col gap-8 md:gap-10">
        <PageHeader
            eyebrow="Pelaporan"
            title="Laporan"
            subtitle="Unduhan CSV pendaftaran, kehadiran, dan ringkasan per acara."
        />

        <div class="grid gap-4 sm:grid-cols-3">
            <KpiCard label="Events" :value="globalSummary.total_events" :icon="BarChart3" color="primary" />
            <KpiCard label="All submissions" :value="globalSummary.total_submissions" :icon="ClipboardList" color="warning" />
            <KpiCard label="Attendance records" :value="globalSummary.total_attendance_records" :icon="ScanLine" color="success" />
        </div>

        <Card class="rounded-xl border shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-medium">Focus event</CardTitle>
                <CardDescription class="text-xs">
                    Select an event for detailed KPIs, the attendance audit log, and exports.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex min-w-[220px] flex-1 flex-col gap-1.5">
                    <Label for="report-event" class="text-xs font-semibold uppercase text-muted-foreground">Event</Label>
                    <select
                        id="report-event"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :value="selectedEventId ?? ''"
                        @change="onEventChange"
                    >
                        <option value="">— Overview only —</option>
                        <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.title }}</option>
                    </select>
                </div>
                <div v-if="exportUrls && selectedEvent" class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" as-child class="rounded-full">
                        <a :href="exportUrls.registrations">
                            <Download class="mr-1.5 size-4" />Registrations CSV
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" as-child class="rounded-full">
                        <a :href="exportUrls.attendance">
                            <Download class="mr-1.5 size-4" />Attendance CSV
                        </a>
                    </Button>
                </div>
            </CardContent>
        </Card>

        <template v-if="selectedEventSummary && selectedEvent">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCard label="Submissions (all forms)" :value="selectedEventSummary.submission_count" :icon="ClipboardList" color="primary" />
                <KpiCard label="Attended (scanned)" :value="selectedEventSummary.attended_count" :icon="ScanLine" color="success" />
                <KpiCard
                    label="Attendance rate"
                    :value="selectedEventSummary.attendance_rate_percent != null ? `${selectedEventSummary.attendance_rate_percent}%` : '—'"
                    :icon="Users"
                    color="warning"
                />
                <KpiCard
                    label="Registered / quota"
                    :value="
                        selectedEventSummary.quota != null
                            ? `${selectedEventSummary.registered_count} / ${selectedEventSummary.quota}`
                            : String(selectedEventSummary.registered_count)
                    "
                    :icon="Users"
                    color="primary"
                />
            </div>

            <Card class="rounded-xl border shadow-xs">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base font-medium">Attendance log</CardTitle>
                    <CardDescription class="text-xs">
                        Rows from <code class="rounded bg-muted px-1 py-0.5 text-[11px]">event_attendances</code>
                        for {{ selectedEvent.title }}.
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
                            <TableRow v-for="row in attendanceLog?.data ?? []" :key="row.id">
                                <TableCell class="whitespace-nowrap text-sm">{{ formatDt(row.scanned_at) }}</TableCell>
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
                            <TableRow v-if="(attendanceLog?.data ?? []).length === 0">
                                <TableCell colspan="4" class="py-10 text-center text-sm text-muted-foreground">
                                    No attendance records yet.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <FormSubmissionsPagination
                        v-if="attendanceLog"
                        :links="attendanceLog.links"
                        :current-page="attendanceLog.current_page"
                        :last-page="attendanceLog.last_page"
                        :total="attendanceLog.total"
                        total-label="rekaman"
                    />
                </CardContent>
            </Card>
        </template>

        <p v-else class="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
            Choose an event above to load attendance audit data and CSV export links.
            <Link href="/admin/dashboard/events" class="ml-1 text-primary underline">Browse events</Link>
        </p>
    </div>
</template>
