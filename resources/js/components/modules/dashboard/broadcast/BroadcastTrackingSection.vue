<script setup lang="ts">
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import BroadcastTrackingSummary from '@/components/modules/dashboard/broadcast/BroadcastTrackingSummary.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import { BROADCAST_BASE_PATH, type IBroadcastHubTracking, type TBroadcastHubStatus } from '@/lib/broadcastHub'
import { parseApiErrorMessage, showErrorToast, showHttpErrorToast } from '@/lib/error-message'
import { formatIdDateTimeLabel } from '@/lib/shadcnDateFormat'
import { cn } from '@/lib/utils'
import { Inbox, RotateCcw } from 'lucide-vue-next'

const props = defineProps<{
    broadcastId: string
    status: TBroadcastHubStatus
}>()

type TrackingRowStatus = 'sent' | 'failed' | 'pending'

interface BroadcastTrackingRow {
    email: string
    name: string | null
    status: TrackingRowStatus
    attempts: number
    sent_at: string | null
    failed_at: string | null
}

interface BroadcastTrackingPayload {
    rows: BroadcastTrackingRow[]
    summary: { total: number; sent: number; failed: number; pending: number }
}

const ROW_STATUS_META: Record<TrackingRowStatus, { label: string; classes: string }> = {
    sent: { label: 'Terkirim', classes: 'border-success/20 bg-success/10 text-success' },
    failed: { label: 'Gagal', classes: 'border-destructive/25 bg-destructive/10 text-destructive' },
    pending: { label: 'Menunggu', classes: 'border-border bg-muted text-muted-foreground' },
}

const payload = ref<BroadcastTrackingPayload | null>(null)
const loading = ref(true)
const failed = ref(false)
const retrying = ref(false)

const rows = computed<BroadcastTrackingRow[]>(() =>
    Array.isArray(payload.value?.rows) ? (payload.value?.rows ?? []) : [],
)
const summary = computed(() => payload.value?.summary ?? { total: 0, sent: 0, failed: 0, pending: 0 })

/** Ringkasan dipetakan ke bentuk kartu incumbent agar satu sumber gaya. */
const summaryCard = computed<IBroadcastHubTracking>(() => ({
    totalRecipients: summary.value.total,
    sentCount: summary.value.sent,
    failedCount: summary.value.failed,
    pendingCount: summary.value.pending,
}))

/** Draft murni menyembunyikan tracking; draft ber-failed tetap tampil demi tombol retry. */
const visible = computed<boolean>(() => props.status !== 'draft' || summary.value.failed > 0)

/** Retry hanya eligible di draft/scheduled (processing/sent -> 422 backend). */
const canRetry = computed<boolean>(
    () =>
        summary.value.failed > 0 &&
        (props.status === 'draft' || props.status === 'scheduled') &&
        !retrying.value,
)

function rowTime(row: BroadcastTrackingRow): string {
    const stamp = row.sent_at ?? row.failed_at
    if (!stamp) return '—'
    try {
        return formatIdDateTimeLabel(stamp)
    } catch {
        return stamp
    }
}

async function loadTracking(): Promise<void> {
    loading.value = true
    failed.value = false
    try {
        const { data } = await axios.get<BroadcastTrackingPayload>(
            `${BROADCAST_BASE_PATH}/${props.broadcastId}/tracking`,
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
        )
        payload.value = data
    } catch (error) {
        payload.value = null
        failed.value = true
        if (axios.isAxiosError(error) && error.response) {
            showHttpErrorToast(error.response.status, error.response.data)
        } else {
            showErrorToast('Gagal memuat tracking. Coba lagi.')
        }
    } finally {
        loading.value = false
    }
}

async function retryFailed(): Promise<void> {
    if (!canRetry.value) return
    retrying.value = true
    try {
        const { data } = await axios.post<{ retried: number }>(
            `${BROADCAST_BASE_PATH}/${props.broadcastId}/retry`,
            {},
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
        )
        const count = typeof data.retried === 'number' ? data.retried : 0
        toast.success(
            count > 0
                ? `${count.toLocaleString('id-ID')} email gagal diantre ulang.`
                : 'Tidak ada email gagal untuk diantre ulang.',
        )
        router.reload()
    } catch (error) {
        if (axios.isAxiosError(error) && error.response) {
            const message = parseApiErrorMessage(error.response.data, '')
            if (message) {
                toast.error(message)
                return
            }
            showHttpErrorToast(error.response.status, error.response.data, {
                422: 'Broadcast yang sedang diproses/sudah terkirim tidak bisa retry.',
            })
            return
        }
        showErrorToast('Gagal mengulang pengiriman. Coba lagi.')
    } finally {
        retrying.value = false
    }
}

onMounted(() => {
    void loadTracking()
})
</script>

<template>
    <div v-if="loading" aria-label="Memuat tracking" role="status">
        <Card class="rounded-2xl border-border/70">
            <CardContent class="space-y-2 p-4 sm:p-5">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div v-for="key in ['total', 'sent', 'pending', 'failed']" :key="key" class="h-16 animate-pulse rounded-xl bg-muted/60" />
                </div>
            </CardContent>
        </Card>
    </div>
    <div v-else-if="failed" class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border/80 px-4 py-8 text-center">
        <p class="text-sm font-medium">Tracking gagal dimuat.</p>
        <Button type="button" variant="outline" size="sm" @click="loadTracking">
            Muat ulang
        </Button>
    </div>
    <div v-else-if="visible">
        <BroadcastTrackingSummary :status="props.status" :tracking="summaryCard" />

        <Card class="mt-4 rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle class="text-base">Status per penerima</CardTitle>
                    <Button v-if="summary.failed > 0 && (props.status === 'draft' || props.status === 'scheduled')" type="button" variant="outline" size="sm" :disabled="!canRetry" @click="retryFailed">
                        <span v-if="retrying" class="flex items-center gap-2">
                            <Spinner />
                            Mengulang…
                        </span>
                        <span v-else class="flex items-center gap-2">
                            <RotateCcw class="size-4" aria-hidden="true" />
                            Ulangi {{ summary.failed.toLocaleString('id-ID') }} gagal
                        </span>
                    </Button>
                </div>
                <p v-if="summary.failed > 0 && props.status !== 'draft' && props.status !== 'scheduled'" class="text-xs text-muted-foreground">
                    Kirim ulang hanya tersedia untuk draft/terjadwal.
                </p>
            </CardHeader>
            <CardContent>
                <div v-if="rows.length > 0" class="overflow-x-auto rounded-xl border border-border/60">
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-border/60 bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                                <th scope="col" class="px-3 py-2.5 font-medium">Email</th>
                                <th scope="col" class="px-3 py-2.5 font-medium">Status</th>
                                <th scope="col" class="px-3 py-2.5 font-medium tabular-nums">Upaya</th>
                                <th scope="col" class="px-3 py-2.5 font-medium">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr v-for="row in rows" :key="row.email">
                                <td class="px-3 py-2.5">
                                    <p class="truncate font-medium">{{ row.name ?? row.email }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ row.email }}</p>
                                </td>
                                <td class="px-3 py-2.5">
                                    <Badge :class="cn('shrink-0 border text-[11px] font-medium', ROW_STATUS_META[row.status].classes)">
                                        {{ ROW_STATUS_META[row.status].label }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-2.5 tabular-nums">{{ row.attempts.toLocaleString('id-ID') }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-xs tabular-nums text-muted-foreground">
                                    {{ rowTime(row) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border/80 px-4 py-8 text-center"
                >
                    <span aria-hidden="true" class="flex size-10 items-center justify-center rounded-full bg-muted">
                        <Inbox class="size-5 text-muted-foreground" />
                    </span>
                    <p class="text-sm font-medium">Belum ada data pengiriman.</p>
                    <p class="max-w-sm text-xs leading-relaxed text-muted-foreground">
                        Baris per penerima muncul setelah broadcast dikirim.
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
