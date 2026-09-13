<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

interface InterviewRow {
    interview_id: string
    scheduled_at: string | null
    status: string
    status_label: string
    location: string
    room: string
    queue_number: number | null
    has_evaluation: boolean
    evaluation_locked: boolean
    application: {
        id: string
        full_name: string
        registration_number: string
        nim: string
        semester: number
        primary_division: string | null
    } | null
    session: {
        id: string
        session_date: string
        division: string | null
    } | null
}

defineProps<{
    interviews: {
        data: InterviewRow[]
        links: unknown[]
        current_page: number
        last_page: number
    }
}>()

onMounted(() => {
    setTopbar({ title: 'Interview Saya', subtitle: 'Applicant yang ditugaskan ke kamu' })
})

function formatSchedule(iso: string | null) {
    if (!iso) return '—'
    return new Date(iso).toLocaleString('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}
</script>

<template>
    <Head title="Interview Saya" />

    <PageHeader title="Interview Saya" description="Daftar applicant yang kamu wawancarai." />

    <Card class="rounded-2xl border-border/70">
        <CardHeader>
            <CardTitle class="text-base">Penugasan interview</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="pb-2 pr-4">Applicant</th>
                            <th class="pb-2 pr-4">Jadwal</th>
                            <th class="pb-2 pr-4">Antrean</th>
                            <th class="pb-2 pr-4">Penilaian</th>
                            <th class="pb-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in interviews.data"
                            :key="row.interview_id"
                            class="border-b border-border/50"
                        >
                            <td class="py-3 pr-4">
                                <p class="font-medium">{{ row.application?.full_name ?? '—' }}</p>
                                <p class="text-muted-foreground font-mono text-xs">
                                    {{ row.application?.registration_number }}
                                </p>
                                <p class="text-muted-foreground text-xs">
                                    {{ row.application?.primary_division }}
                                </p>
                            </td>
                            <td class="py-3 pr-4">
                                <p>{{ formatSchedule(row.scheduled_at) }}</p>
                                <p class="text-muted-foreground text-xs">{{ row.location }} · {{ row.room }}</p>
                            </td>
                            <td class="py-3 pr-4">
                                <span v-if="row.queue_number">#{{ String(row.queue_number).padStart(2, '0') }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="py-3 pr-4">
                                <Badge v-if="row.has_evaluation && row.evaluation_locked" variant="secondary">
                                    Terkunci
                                </Badge>
                                <Badge v-else-if="row.has_evaluation" variant="outline">Draft</Badge>
                                <Badge v-else variant="outline">Belum</Badge>
                            </td>
                            <td class="py-3">
                                <div class="flex flex-wrap gap-2">
                                    <Button v-if="row.application" as-child size="sm">
                                        <Link :href="routes.admin.recruitment.myInterviews.show(row.application.id)">
                                            Detail
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="row.session"
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link :href="routes.admin.recruitment.myInterviews.queue(row.session.id)">
                                            Antrean
                                        </Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="interviews.data.length === 0">
                            <td colspan="5" class="text-muted-foreground py-8 text-center">
                                Belum ada penugasan interview.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
