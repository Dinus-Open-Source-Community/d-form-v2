<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import KpiCard from '@/components/modules/dashboard/KpiCard.vue'
import EventReportingFocusPanel from '@/components/modules/dashboard/EventReportingFocusPanel.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { BarChart3, ClipboardList, ScanLine, Download } from 'lucide-vue-next'
import { routes } from '@/lib/routes'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    globalSummary: {
        total_events: number
        total_submissions: number
        total_attendance_records: number
    }
    event: IEvent
    exports: { registrations: string; attendance: string }
    eventReporting: {
        summary: {
            submission_count: number
            attended_count: number
            attendance_rate_percent: number | null
            registered_count: number
            quota: number | null
        }
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
    }
}>()

</script>

<template>
    <Head :title="`Laporan — ${props.event.title}`" />

    <div class="flex flex-col gap-8 md:gap-10">
        <PageHeader
            title="Laporan"
            subtitle="Unduhan CSV pendaftaran, kehadiran, dan ringkasan untuk acara ini."
            :back-href="routes.admin.events.show(props.event.id)"
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
                    Laporan untuk acara berikut beserta unduhan CSV pendaftaran dan kehadiran.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex min-w-[220px] flex-1 flex-col gap-1.5">
                    <Label for="laporan-event-title" class="text-xs font-semibold uppercase text-muted-foreground">Event</Label>
                    <p
                        id="laporan-event-title"
                        class="flex min-h-10 items-center rounded-md border border-input bg-muted/30 px-3 text-sm font-medium text-foreground shadow-xs"
                    >
                        {{ event.title }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" as-child class="rounded-full">
                        <a :href="exports.registrations">
                            <Download class="mr-1.5 size-4" />Registrations CSV
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" as-child class="rounded-full">
                        <a :href="exports.attendance">
                            <Download class="mr-1.5 size-4" />Attendance CSV
                        </a>
                    </Button>
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
