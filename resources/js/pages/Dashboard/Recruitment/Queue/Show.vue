<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import axios from 'axios'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import { useRecruitmentQueue, type QueueSnapshot } from '@/utils/composables/useRecruitmentQueue'
import { toast } from 'vue-sonner'

defineOptions({ layout: DashboardLayout })

interface SessionDetail {
    id: string
    session_date: string
    starts_at: string
    ends_at: string
    location: string
    room: string
    division: { name: string } | null
    period: { name: string } | null
}

const props = defineProps<{
    session: SessionDetail
    queue: QueueSnapshot
    pollUrl: string
    callNextUrl: string
    completeUrlTemplate: string
    canManage: boolean
}>()

const { queue, refresh } = useRecruitmentQueue(props.pollUrl, props.queue)
const actionBusy = ref(false)

onMounted(() => {
    setTopbar({
        title: 'Monitor antrean',
        subtitle: props.session.division?.name ?? '',
    })
})

const completeUrlFor = (entryId: string) => props.completeUrlTemplate.replace('__ENTRY__', entryId)

async function callNext() {
    if (actionBusy.value || !props.canManage) {
        return
    }

    actionBusy.value = true

    try {
        const { data } = await axios.post(props.callNextUrl, {}, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        queue.value = data.queue
        toast.success(data.message ?? 'Applicant dipanggil.')
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 404) {
            toast.info('Tidak ada antrean menunggu.')
            await refresh()
        } else {
            toast.error('Gagal memanggil antrean berikutnya.')
        }
    } finally {
        actionBusy.value = false
    }
}

async function completeEntry(entryId: string) {
    if (actionBusy.value || !props.canManage) {
        return
    }

    actionBusy.value = true

    try {
        const { data } = await axios.post(completeUrlFor(entryId), {}, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        queue.value = data.queue
        toast.success(data.message ?? 'Antrean selesai.')
    } catch {
        toast.error('Gagal menyelesaikan antrean.')
    } finally {
        actionBusy.value = false
    }
}

const statusVariant = (status: string) => {
    if (status === 'called' || status === 'in_progress') return 'default'
    if (status === 'completed') return 'secondary'
    return 'outline'
}
</script>

<template>
    <Head title="Monitor Antrean Interview" />

    <PageHeader
        title="Monitor antrean"
        :description="`${session.division?.name ?? ''} · ${session.session_date} ${session.starts_at}–${session.ends_at}`"
    >
        <template #actions>
            <Button v-if="canManage" :disabled="actionBusy" @click="callNext">Panggil berikutnya</Button>
            <Button variant="outline" as-child>
                <Link :href="routes.admin.recruitment.interviewSessions.show(session.id)">Detail sesi</Link>
            </Button>
        </template>
    </PageHeader>

    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        <Card>
            <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Total</CardTitle></CardHeader>
            <CardContent><p class="text-2xl font-bold">{{ queue.stats.total }}</p></CardContent>
        </Card>
        <Card>
            <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Menunggu</CardTitle></CardHeader>
            <CardContent><p class="text-2xl font-bold">{{ queue.stats.waiting }}</p></CardContent>
        </Card>
        <Card>
            <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Dipanggil</CardTitle></CardHeader>
            <CardContent><p class="text-2xl font-bold">{{ queue.stats.called }}</p></CardContent>
        </Card>
        <Card>
            <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Selesai</CardTitle></CardHeader>
            <CardContent><p class="text-2xl font-bold">{{ queue.stats.completed }}</p></CardContent>
        </Card>
    </div>

    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <Card>
            <CardHeader><CardTitle class="text-base">Sedang dilayani</CardTitle></CardHeader>
            <CardContent>
                <template v-if="queue.current">
                    <p class="text-3xl font-bold">#{{ String(queue.current.queue_number).padStart(2, '0') }}</p>
                    <p class="font-medium">{{ queue.current.application?.full_name }}</p>
                    <p class="text-muted-foreground text-sm">{{ queue.current.application?.registration_number }}</p>
                    <Button
                        v-if="canManage"
                        class="mt-3"
                        size="sm"
                        variant="secondary"
                        :disabled="actionBusy"
                        @click="completeEntry(queue.current.id)"
                    >
                        Tandai selesai
                    </Button>
                </template>
                <p v-else class="text-muted-foreground text-sm">Belum ada yang dipanggil.</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle class="text-base">Berikutnya</CardTitle></CardHeader>
            <CardContent>
                <template v-if="queue.next">
                    <p class="text-3xl font-bold">#{{ String(queue.next.queue_number).padStart(2, '0') }}</p>
                    <p class="font-medium">{{ queue.next.application?.full_name }}</p>
                    <p class="text-muted-foreground text-sm">{{ queue.next.application?.registration_number }}</p>
                </template>
                <p v-else class="text-muted-foreground text-sm">Antrean kosong.</p>
            </CardContent>
        </Card>
    </div>

    <Card>
        <CardHeader><CardTitle class="text-base">Daftar antrean</CardTitle></CardHeader>
        <CardContent>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="pb-2 pr-4">No.</th>
                            <th class="pb-2 pr-4">Applicant</th>
                            <th class="pb-2 pr-4">Status</th>
                            <th v-if="canManage" class="pb-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="entry in queue.entries" :key="entry.id" class="border-b border-border/50">
                            <td class="py-3 pr-4 font-mono font-semibold">
                                #{{ String(entry.queue_number).padStart(2, '0') }}
                            </td>
                            <td class="py-3 pr-4">
                                <p class="font-medium">{{ entry.application?.full_name ?? '—' }}</p>
                                <p class="text-muted-foreground text-xs">{{ entry.application?.registration_number }}</p>
                            </td>
                            <td class="py-3 pr-4">
                                <Badge :variant="statusVariant(entry.status)">{{ entry.status_label }}</Badge>
                            </td>
                            <td v-if="canManage" class="py-3">
                                <Button
                                    v-if="entry.status === 'called' || entry.status === 'in_progress'"
                                    size="sm"
                                    variant="outline"
                                    :disabled="actionBusy"
                                    @click="completeEntry(entry.id)"
                                >
                                    Selesai
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="queue.entries.length === 0">
                            <td colspan="4" class="text-muted-foreground py-6 text-center">Belum ada check-in.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-muted-foreground mt-4 text-xs">Memperbarui otomatis setiap 10 detik.</p>
        </CardContent>
    </Card>
</template>
