<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import {
    useRecruitmentQueue,
    type QueueEntryRow,
    type QueueSnapshot,
} from '@/utils/composables/useRecruitmentQueue'
import { Loader2 } from 'lucide-vue-next'

const props = defineProps<{
    pollUrl: string
    sessionDate: string | null
    division: string | null
}>()

const EMPTY_SNAPSHOT: QueueSnapshot = {
    entries: [],
    current: null,
    next: null,
    stats: { waiting: 0, called: 0, completed: 0, total: 0 },
}

const { queue, refresh } = useRecruitmentQueue(props.pollUrl, EMPTY_SNAPSHOT)
const loading = ref<boolean>(true)

onMounted(async (): Promise<void> => {
    await refresh()
    loading.value = false
})

function formatSessionDate(value: string): string {
    const parsed: Date = new Date(`${value}T00:00:00`)
    if (Number.isNaN(parsed.getTime())) return value
    return parsed.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    })
}

const sessionSubtitle = computed<string>((): string => {
    const parts: string[] = []
    if (props.sessionDate !== null && props.sessionDate !== '') {
        parts.push(formatSessionDate(props.sessionDate))
    }
    if (props.division !== null && props.division !== '') {
        parts.push(props.division)
    }
    return parts.length > 0 ? parts.join(' · ') : 'Ringkasan antrean sesi interview'
})

function queueNumber(value: number): string {
    return `#${String(value).padStart(2, '0')}`
}

const waitingEntries = computed<QueueEntryRow[]>((): QueueEntryRow[] =>
    queue.value.entries
        .filter((entry: QueueEntryRow): boolean => entry.status === 'waiting')
        .slice()
        .sort((a: QueueEntryRow, b: QueueEntryRow): number => a.queue_number - b.queue_number),
)

interface StatItem {
    key: string
    label: string
    value: number
}

const statItems = computed<StatItem[]>((): StatItem[] => [
    { key: 'waiting', label: 'Menunggu', value: queue.value.stats.waiting },
    { key: 'called', label: 'Berlangsung', value: queue.value.stats.called },
    { key: 'completed', label: 'Selesai', value: queue.value.stats.completed },
    { key: 'total', label: 'Total', value: queue.value.stats.total },
])
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <SheetHeader class="shrink-0 space-y-1 border-b border-border/70 py-4 pr-12 pl-4 text-left">
            <SheetTitle class="text-base">Antrean sesi</SheetTitle>
            <SheetDescription class="truncate text-xs text-muted-foreground">
                {{ sessionSubtitle }}
            </SheetDescription>
        </SheetHeader>

        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-4" :aria-busy="loading">
            <p v-if="loading" class="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 class="size-4 animate-spin" aria-hidden="true" />
                Memuat antrean…
            </p>

            <template v-else>
                <dl class="grid grid-cols-2 gap-2">
                    <div
                        v-for="stat in statItems"
                        :key="stat.key"
                        class="rounded-xl border border-border/70 px-3 py-2"
                    >
                        <dt class="text-xs uppercase text-muted-foreground">{{ stat.label }}</dt>
                        <dd class="mt-0.5 text-lg font-semibold tabular-nums">{{ stat.value }}</dd>
                    </div>
                </dl>

                <section class="space-y-2" aria-label="Sedang interview">
                    <h3 class="text-sm font-semibold">Sedang interview</h3>
                    <div
                        v-if="queue.current"
                        class="flex items-center gap-3 rounded-xl border border-primary/30 bg-primary/5 px-3.5 py-3"
                    >
                        <span class="font-mono text-lg font-semibold tabular-nums text-primary">
                            {{ queueNumber(queue.current.queue_number) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ queue.current.application?.full_name ?? '—' }}
                            </p>
                            <p class="truncate font-mono text-xs text-muted-foreground">
                                {{ queue.current.application?.registration_number ?? '—' }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-else
                        class="rounded-xl border border-dashed border-border/70 px-3.5 py-3 text-sm text-muted-foreground"
                    >
                        Belum ada yang sedang diinterview.
                    </p>
                </section>

                <section class="space-y-2" aria-label="Menunggu">
                    <div class="flex items-baseline justify-between gap-2">
                        <h3 class="text-sm font-semibold">Menunggu</h3>
                        <p class="text-xs text-muted-foreground tabular-nums">
                            {{ queue.stats.waiting }} menunggu
                        </p>
                    </div>
                    <ul
                        v-if="waitingEntries.length > 0"
                        class="divide-y divide-border/60 rounded-xl border border-border/70"
                    >
                        <li
                            v-for="entry in waitingEntries"
                            :key="entry.id"
                            class="flex items-center gap-3 px-3.5 py-2.5"
                        >
                            <span class="font-mono text-sm font-semibold tabular-nums">
                                {{ queueNumber(entry.queue_number) }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm">
                                    {{ entry.application?.full_name ?? '—' }}
                                </p>
                                <p class="truncate font-mono text-xs text-muted-foreground">
                                    {{ entry.application?.registration_number ?? '—' }}
                                </p>
                            </div>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="rounded-xl border border-dashed border-border/70 px-3.5 py-3 text-sm text-muted-foreground"
                    >
                        Tidak ada antrean menunggu.
                    </p>
                </section>
            </template>
        </div>

        <SheetFooter class="shrink-0 border-t border-border/70 py-3">
            <p class="text-xs text-muted-foreground">Memperbarui otomatis setiap 10 detik</p>
        </SheetFooter>
    </div>
</template>
