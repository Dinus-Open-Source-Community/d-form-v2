<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

interface InterviewRow {
    id: string
    scheduled_at: string | null
    location: string
    room: string
    status: string
    status_label: string
    application: {
        id: string
        full_name: string
        registration_number: string
    } | null
    interviewer: { id: string; name: string } | null
}

interface SessionDetail {
    id: string
    session_date: string
    starts_at: string
    ends_at: string
    location: string
    room: string
    notes: string | null
    is_active: boolean
    interviews_count: number
    period: { id: string; name: string } | null
    division: { id: string; name: string; code: string } | null
    interviews: InterviewRow[]
}

const props = defineProps<{
    session: SessionDetail
}>()

onMounted(() => {
    setTopbar({
        title: 'Detail sesi interview',
        subtitle: props.session.division?.name ?? '',
    })
})
</script>

<template>
    <Head title="Detail Sesi Interview" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-wrap items-center justify-end gap-3">
            <Button variant="outline" as-child>
                <Link :href="routes.admin.recruitment.myInterviews.index()">Interview saya</Link>
            </Button>
        </div>

        <Card class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Informasi sesi</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-2 text-sm sm:grid-cols-2">
                <p><span class="text-muted-foreground">Tanggal:</span> {{ session.session_date }}</p>
                <p><span class="text-muted-foreground">Waktu:</span> {{ session.starts_at }}–{{ session.ends_at }}</p>
                <p><span class="text-muted-foreground">Lokasi:</span> {{ session.location }} / {{ session.room }}</p>
                <p><span class="text-muted-foreground">Periode:</span> {{ session.period?.name ?? '—' }}</p>
                <p><span class="text-muted-foreground">Divisi:</span> {{ session.division?.name ?? '—' }}</p>
                <p><span class="text-muted-foreground">Sudah absen:</span> {{ session.interviews.length }}</p>
                <p v-if="session.notes" class="sm:col-span-2">{{ session.notes }}</p>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Applicant (setelah absen)</CardTitle>
            </CardHeader>
            <CardContent>
                <p v-if="session.interviews.length === 0" class="text-muted-foreground text-sm">
                    Belum ada applicant yang absen pada sesi ini.
                </p>
                <ul v-else class="divide-y rounded-lg border">
                    <li
                        v-for="row in session.interviews"
                        :key="row.id"
                        class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
                    >
                        <div>
                            <p class="font-medium">{{ row.application?.full_name ?? '—' }}</p>
                            <p class="text-muted-foreground font-mono text-xs">
                                {{ row.application?.registration_number ?? '—' }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium">{{ row.status_label }}</p>
                            <p v-if="row.interviewer" class="text-muted-foreground text-xs">
                                {{ row.interviewer.name }}
                            </p>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
