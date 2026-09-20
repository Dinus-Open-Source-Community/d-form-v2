<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import InterviewSessionCreateSheet, {
    type InterviewDivisionChoice,
} from '@/components/modules/dashboard/recruitment/InterviewSessionCreateSheet.vue'
import { routes } from '@/lib/routes'

interface SessionRow {
    id: string
    session_date: string
    starts_at: string
    ends_at: string
    location: string
    room: string
    is_active: boolean
    interviews_count: number
    period: { id: string; name: string } | null
    division: { id: string; name: string; code: string } | null
}

interface SessionPaginator {
    data: SessionRow[]
    current_page: number
    last_page: number
    total: number
}

defineProps<{
    sessions: SessionPaginator | null
    todaySessions: SessionRow[]
    periodId: string
    divisionOptions: InterviewDivisionChoice[]
}>()

const createOpen = ref<boolean>(false)
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold tracking-wide uppercase text-muted-foreground">
                Interview periode ini
            </h2>
            <Button size="sm" class="gap-1.5" @click="createOpen = true">
                <Plus class="size-4" aria-hidden="true" />
                Buat sesi
            </Button>
        </div>

        <InterviewSessionCreateSheet
            :open="createOpen"
            :period-id="periodId"
            :divisions="divisionOptions"
            @close="createOpen = false"
        />

        <Card v-if="todaySessions.length > 0" class="rounded-2xl border-primary/30 bg-primary/5">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Sesi hari ini</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div
                    v-for="session in todaySessions"
                    :key="session.id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-background p-4"
                >
                    <div class="text-sm">
                        <p class="font-medium">{{ session.division?.name ?? 'Semua divisi' }}</p>
                        <p class="text-muted-foreground text-xs">
                            {{ session.starts_at }}–{{ session.ends_at }} · {{ session.location }} · Ruang
                            {{ session.room }} · {{ session.interviews_count }} terjadwal
                        </p>
                    </div>
                    <Button as-child size="sm">
                        <Link :href="routes.admin.recruitment.queue.show(session.id)">Buka antrean live</Link>
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card v-if="sessions" class="overflow-hidden rounded-2xl border-border/70">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 border-b text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">Tanggal</th>
                                <th class="px-4 py-3 font-medium">Waktu</th>
                                <th class="px-4 py-3 font-medium">Divisi</th>
                                <th class="px-4 py-3 font-medium">Lokasi</th>
                                <th class="px-4 py-3 font-medium">Terjadwal</th>
                                <th class="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="session in sessions.data"
                                :key="session.id"
                                class="border-b last:border-0 hover:bg-muted/20"
                            >
                                <td class="px-4 py-3">{{ session.session_date }}</td>
                                <td class="px-4 py-3">{{ session.starts_at }}–{{ session.ends_at }}</td>
                                <td class="px-4 py-3">{{ session.division?.name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ session.location }} · {{ session.room }}</td>
                                <td class="px-4 py-3">{{ session.interviews_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Button as-child size="sm" variant="ghost">
                                        <Link :href="routes.admin.recruitment.interviewSessions.show(session.id)">Detail</Link>
                                    </Button>
                                    <Button as-child size="sm" variant="ghost">
                                        <Link :href="routes.admin.recruitment.queue.show(session.id)">Antrean</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <p v-else class="text-muted-foreground text-sm">Data sesi tidak tersedia untuk tab ini.</p>
    </div>
</template>
