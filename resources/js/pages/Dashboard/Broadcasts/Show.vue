<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BroadcastTrackingSummary from '@/components/modules/dashboard/broadcast/BroadcastTrackingSummary.vue'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { buildBroadcastSnapshotRows } from '@/lib/broadcastHub'
import type {
    IBroadcastShowContext,
    IBroadcastShownBroadcast,
    IBroadcastSnapshot,
    TBroadcastHubStatus,
} from '@/lib/broadcastHub'
import { cn } from '@/lib/utils'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import { MailOpen } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    broadcast: IBroadcastShownBroadcast
    snapshot: IBroadcastSnapshot | null
    context: IBroadcastShowContext | null
}>()

const statusClasses: Record<TBroadcastHubStatus, string> = {
    draft: 'border-border bg-secondary text-secondary-foreground',
    scheduled: 'border-warning/25 bg-warning/10 text-warning-foreground',
    processing: 'border-primary/25 bg-primary/10 text-primary',
    sent: 'border-success/20 bg-success/10 text-success',
}

const statusLabels: Record<TBroadcastHubStatus, string> = {
    draft: 'Draft',
    scheduled: 'Terjadwal',
    processing: 'Diproses',
    sent: 'Terkirim',
}

/** Baris snapshot read-only (source of truth per broadcast). */
const snapshotRows = computed(() => buildBroadcastSnapshotRows(props.snapshot, props.context))

const scheduleLabel = computed<string>(() => props.broadcast.scheduled_at ?? 'Draft — tanpa jadwal')
const delayLabel = computed<string>(() =>
    props.broadcast.send_delay_seconds === null ? '—' : `${props.broadcast.send_delay_seconds} detik`,
)

onMounted(() => {
    setTopbar({ title: props.broadcast.name, subtitle: 'Detail broadcast' })
})
</script>

<template>
    <Head :title="props.broadcast.name" />

    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 pb-8 sm:pb-10">
        <Card class="overflow-hidden rounded-xl border-border/70 shadow-sm">
            <CardContent class="p-4 sm:p-5">
                <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                    <h1 class="min-w-0 break-words text-lg font-semibold tracking-tight sm:text-xl">
                        {{ props.broadcast.name }}
                    </h1>
                    <Badge
                        :class="
                            cn('shrink-0 border text-[11px] font-medium', statusClasses[props.broadcast.status])
                        "
                    >
                        {{ statusLabels[props.broadcast.status] }}
                    </Badge>
                </div>
                <dl class="mt-3 grid gap-3 border-t border-border/60 pt-3 sm:grid-cols-3">
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Jadwal</dt>
                        <dd class="mt-1 text-sm tabular-nums">{{ scheduleLabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                            Jeda antar email
                        </dt>
                        <dd class="mt-1 text-sm tabular-nums">{{ delayLabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Penerima</dt>
                        <dd class="mt-1 text-sm tabular-nums">
                            {{ props.broadcast.recipient_count.toLocaleString('id-ID') }} orang
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <!-- Seam Fase 2: backend Fase 1 tidak mengirim objek tracking — tidak render apa pun. -->
        <BroadcastTrackingSummary :status="props.broadcast.status" :tracking="null" />

        <Card class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Snapshot penerima</CardTitle>
                <p class="text-xs text-muted-foreground">Read-only — snapshot terkunci saat broadcast disimpan.</p>
            </CardHeader>
            <CardContent>
                <div v-if="props.snapshot">
                    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div
                            v-for="row in snapshotRows"
                            :key="row.key"
                            class="rounded-xl border border-border/60 bg-muted/30 px-3 py-2.5"
                        >
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                {{ row.label }}
                            </dt>
                            <dd class="mt-1 text-sm font-semibold break-words">{{ row.value }}</dd>
                        </div>
                    </dl>
                    <ul
                        v-if="props.snapshot.recipients.length > 0"
                        class="mt-4 max-h-72 divide-y divide-border/60 overflow-y-auto rounded-xl border border-border/60"
                    >
                        <li
                            v-for="recipient in props.snapshot.recipients"
                            :key="recipient.email"
                            class="flex min-w-0 items-center justify-between gap-3 px-3 py-2"
                        >
                            <span class="min-w-0 truncate text-sm font-medium">
                                {{ recipient.name ?? recipient.email }}
                            </span>
                            <span class="shrink-0 truncate text-xs text-muted-foreground">{{ recipient.email }}</span>
                        </li>
                    </ul>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border/80 px-4 py-8 text-center"
                >
                    <span aria-hidden="true" class="flex size-10 items-center justify-center rounded-full bg-muted">
                        <MailOpen class="size-5 text-muted-foreground" />
                    </span>
                    <p class="text-sm font-medium">Belum ada snapshot penerima.</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
