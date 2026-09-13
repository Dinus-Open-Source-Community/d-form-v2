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
import { useRecruitmentQueue, type QueueSnapshot } from '@/utils/composables/useRecruitmentQueue'

defineOptions({ layout: DashboardLayout })

interface SessionDetail {
    id: string
    session_date: string
    starts_at: string
    ends_at: string
    location: string
    room: string
    division: { name: string } | null
}

const props = defineProps<{
    session: SessionDetail
    queue: QueueSnapshot
    pollUrl: string
}>()

const { queue } = useRecruitmentQueue(props.pollUrl, props.queue)

onMounted(() => {
    setTopbar({
        title: 'Antrean sesi',
        subtitle: props.session.division?.name ?? '',
    })
})
</script>

<template>
    <Head title="Antrean Interview Saya" />

    <PageHeader
        :title="`Antrean · ${session.division?.name ?? 'Interview'}`"
        :description="`${session.session_date} ${session.starts_at}–${session.ends_at}`"
        :back-href="routes.admin.recruitment.myInterviews.index"
    />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <Card>
            <CardHeader class="pb-2"><CardTitle class="text-sm">Menunggu</CardTitle></CardHeader>
            <CardContent><p class="text-2xl font-bold">{{ queue.stats.waiting }}</p></CardContent>
        </Card>
        <Card>
            <CardHeader class="pb-2"><CardTitle class="text-sm">Dipanggil</CardTitle></CardHeader>
            <CardContent><p class="text-2xl font-bold">{{ queue.stats.called }}</p></CardContent>
        </Card>
        <Card>
            <CardHeader class="pb-2"><CardTitle class="text-sm">Selesai</CardTitle></CardHeader>
            <CardContent><p class="text-2xl font-bold">{{ queue.stats.completed }}</p></CardContent>
        </Card>
    </div>

    <Card class="rounded-2xl border-border/70">
        <CardHeader>
            <CardTitle class="text-base">Sedang dilayani</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <template v-if="queue.current?.application">
                <p class="text-2xl font-bold">#{{ String(queue.current.queue_number).padStart(2, '0') }}</p>
                <p class="font-medium">{{ queue.current.application.full_name }}</p>
                <Button as-child size="sm">
                    <Link :href="routes.admin.recruitment.myInterviews.show(queue.current.application.id)">
                        Buka &amp; nilai
                    </Link>
                </Button>
            </template>
            <p v-else class="text-muted-foreground text-sm">Belum ada yang dipanggil.</p>

            <div v-if="queue.next?.application" class="rounded-xl border border-border/60 p-4 text-sm">
                <p class="text-muted-foreground mb-1">Berikutnya</p>
                <p class="font-medium">
                    #{{ String(queue.next.queue_number).padStart(2, '0') }} —
                    {{ queue.next.application.full_name }}
                </p>
            </div>

            <div class="overflow-x-auto pt-2">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="pb-2 pr-4">No.</th>
                            <th class="pb-2 pr-4">Applicant</th>
                            <th class="pb-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="entry in queue.entries" :key="entry.id" class="border-b border-border/50">
                            <td class="py-2 pr-4 font-mono">#{{ String(entry.queue_number).padStart(2, '0') }}</td>
                            <td class="py-2 pr-4">{{ entry.application?.full_name ?? '—' }}</td>
                            <td class="py-2"><Badge variant="outline">{{ entry.status_label }}</Badge></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-muted-foreground text-xs">Memperbarui otomatis setiap 10 detik.</p>
        </CardContent>
    </Card>
</template>
