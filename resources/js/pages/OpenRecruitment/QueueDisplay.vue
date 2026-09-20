<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import LandingLayout from '@/layouts/LandingLayout.vue'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select'
import { Megaphone, WifiOff } from 'lucide-vue-next'

defineOptions({ layout: LandingLayout })

interface QueueDisplayEntry {
    queue_number: number
    display_name: string
    division?: string | null
    room?: string | null
    status: string
    status_label?: string | null
    called_at?: string | null
}

interface QueueDisplaySession {
    name?: string | null
    division?: string | null
    room?: string | null
    time?: string | null
}

interface QueueDisplayStats {
    waiting?: number | null
    called?: number | null
    completed?: number | null
    total?: number | null
}

interface QueueDisplaySnapshot {
    session?: QueueDisplaySession | null
    entries?: QueueDisplayEntry[] | null
    current?: QueueDisplayEntry | null
    next?: QueueDisplayEntry | null
    stats?: QueueDisplayStats | null
}

const POLL_INTERVAL_MS = 15_000

const props = defineProps<{
    snapshot: QueueDisplaySnapshot
    pollUrl?: string | null
}>()

const live = ref<QueueDisplaySnapshot>(props.snapshot)
const loadError = ref<boolean>(false)
const isOffline = ref<boolean>(false)
const lastUpdatedAt = ref<Date | null>(null)

const divisionFilter = ref<string>('')
const roomFilter = ref<string>('')
const statusFilter = ref<string>('')

let pollTimer: ReturnType<typeof setInterval> | null = null
let isRefreshing = false

function queueNumberLabel(value: number | null | undefined): string {
    return `#${String(value ?? 0).padStart(2, '0')}`
}

function entryStatusLabel(entry: QueueDisplayEntry): string {
    return entry.status_label?.trim() || entry.status || '—'
}

const entries = computed<QueueDisplayEntry[]>(() => live.value.entries ?? [])

function uniqueOptions(values: (string | null | undefined)[], allLabel: string): SimpleSelectOption[] {
    const seen = new Map<string, string>()
    for (const raw of values) {
        const value = (raw ?? '').trim()
        if (value !== '' && !seen.has(value)) seen.set(value, value)
    }
    return [{ value: '', label: allLabel }, ...[...seen.entries()].map(([value, label]) => ({ value, label }))]
}

const divisionOptions = computed<SimpleSelectOption[]>(() =>
    uniqueOptions(entries.value.map((e) => e.division), 'Semua divisi'),
)

const roomOptions = computed<SimpleSelectOption[]>(() =>
    uniqueOptions(entries.value.map((e) => e.room), 'Semua ruang'),
)

const statusOptions = computed<SimpleSelectOption[]>(() => {
    const seen = new Map<string, string>()
    for (const entry of entries.value) {
        const value = (entry.status ?? '').trim()
        if (value !== '' && !seen.has(value)) seen.set(value, entryStatusLabel(entry))
    }
    return [{ value: '', label: 'Semua status' }, ...[...seen.entries()].map(([value, label]) => ({ value, label }))]
})

const filteredEntries = computed<QueueDisplayEntry[]>(() =>
    entries.value.filter((entry) => {
        if (divisionFilter.value !== '' && (entry.division ?? '').trim() !== divisionFilter.value) return false
        if (roomFilter.value !== '' && (entry.room ?? '').trim() !== roomFilter.value) return false
        if (statusFilter.value !== '' && (entry.status ?? '').trim() !== statusFilter.value) return false
        return true
    }),
)

const lastUpdatedLabel = computed<string>(() => {
    if (lastUpdatedAt.value === null) return 'Menampilkan data awal'
    return `Diperbarui ${lastUpdatedAt.value.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    })}`
})

async function refreshDisplay(): Promise<void> {
    if (isRefreshing || !props.pollUrl) return
    isRefreshing = true
    try {
        const response = await axios.get<QueueDisplaySnapshot>(props.pollUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        live.value = response.data
        loadError.value = false
        lastUpdatedAt.value = new Date()
    } catch {
        loadError.value = true
    } finally {
        isRefreshing = false
    }
}

function startPolling(): void {
    if (pollTimer !== null || !props.pollUrl) return
    pollTimer = setInterval((): void => {
        void refreshDisplay()
    }, POLL_INTERVAL_MS)
}

function stopPolling(): void {
    if (pollTimer !== null) {
        clearInterval(pollTimer)
        pollTimer = null
    }
}

function handleVisibilityChange(): void {
    if (document.hidden) {
        stopPolling()
        return
    }
    void refreshDisplay()
    startPolling()
}

function handleOnline(): void {
    isOffline.value = false
    void refreshDisplay()
}

function handleOffline(): void {
    isOffline.value = true
}

onMounted((): void => {
    isOffline.value = typeof navigator !== 'undefined' ? !navigator.onLine : false
    document.addEventListener('visibilitychange', handleVisibilityChange)
    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)
    startPolling()
})

onUnmounted((): void => {
    stopPolling()
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    window.removeEventListener('online', handleOnline)
    window.removeEventListener('offline', handleOffline)
})
</script>

<template>
    <Head title="Papan Antrean Interview" />

    <div class="relative">
        <section
            aria-label="Informasi sesi"
            class="border-border/30 bg-muted/20 border-b pt-28 pb-8 sm:pt-32 sm:pb-10 lg:pb-12"
        >
            <div class="mx-auto w-full max-w-5xl px-4 text-center sm:px-6 lg:px-10">
                <p class="text-primary text-xs font-semibold tracking-[0.2em] uppercase sm:text-sm">
                    OpenRecruitment DOSCOM · Papan antrean
                </p>
                <h1 class="font-display text-foreground mt-3 text-2xl font-bold tracking-tight text-balance sm:text-4xl lg:text-5xl">
                    {{ live.session?.name?.trim() || 'Antrean interview' }}
                </h1>
                <p class="text-muted-foreground mt-3 text-sm leading-relaxed sm:text-base">
                    {{ [live.session?.time, live.session?.room].filter((v) => (v ?? '').trim() !== '').join(' · ') || '—' }}
                </p>
            </div>
        </section>

        <div class="mx-auto w-full max-w-5xl px-4 pt-8 pb-16 sm:px-6 sm:pt-10 sm:pb-20 lg:px-10 lg:pb-24">
            <div
                v-if="isOffline || loadError"
                role="alert"
                class="mb-6 rounded-2xl border border-amber-200/80 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            >
                <p class="flex items-center gap-2 font-medium">
                    <WifiOff class="size-4 shrink-0" aria-hidden="true" />
                    {{
                        isOffline
                            ? 'Koneksi terputus. Data terakhir tetap ditampilkan.'
                            : 'Gagal memperbarui antrean. Data terakhir tetap ditampilkan.'
                    }}
                </p>
            </div>

            <section aria-label="Sedang dipanggil" class="mb-6 sm:mb-8">
                <Card class="rounded-2xl border-border/70">
                    <CardContent class="p-6 text-center sm:p-10">
                        <p class="text-muted-foreground flex items-center justify-center gap-2 text-xs font-semibold tracking-widest uppercase sm:text-sm">
                            <Megaphone class="size-4 sm:size-5" aria-hidden="true" />
                            Sedang dipanggil
                        </p>
                        <template v-if="live.current">
                            <p aria-live="polite" class="mt-4 text-7xl font-bold tabular-nums tracking-tight sm:text-8xl lg:text-9xl">
                                {{ queueNumberLabel(live.current.queue_number) }}
                            </p>
                            <p class="mt-3 text-2xl font-semibold sm:text-4xl">
                                {{ live.current.display_name?.trim() || '—' }}
                            </p>
                            <p
                                v-if="(live.current.division ?? '').trim() !== ''"
                                class="text-muted-foreground mt-2 text-sm sm:text-base"
                            >
                                {{ live.current.division }}
                            </p>
                        </template>
                        <p v-else class="text-muted-foreground mt-6 text-lg sm:text-2xl">
                            Belum ada yang dipanggil.
                        </p>
                    </CardContent>
                </Card>
            </section>

            <section v-if="live.next" aria-label="Berikutnya" class="mb-6 sm:mb-8">
                <Card class="rounded-2xl border-border/70 bg-muted/40">
                    <CardContent class="flex flex-wrap items-baseline justify-center gap-x-4 gap-y-1 p-5 text-center sm:p-6">
                        <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase sm:text-sm">
                            Berikutnya
                        </p>
                        <p class="text-3xl font-bold tabular-nums sm:text-5xl">
                            {{ queueNumberLabel(live.next.queue_number) }}
                        </p>
                        <p class="text-lg font-medium sm:text-2xl">
                            {{ live.next.display_name?.trim() || '—' }}
                        </p>
                    </CardContent>
                </Card>
            </section>

            <section aria-label="Daftar antrean">
                <div class="mb-4 flex flex-wrap items-end gap-3">
                    <div class="min-w-36 flex-1">
                        <SimpleSelect
                            v-model="divisionFilter"
                            :options="divisionOptions"
                            placeholder="Semua divisi"
                            aria-label="Filter divisi"
                        />
                    </div>
                    <div class="min-w-36 flex-1">
                        <SimpleSelect
                            v-model="roomFilter"
                            :options="roomOptions"
                            placeholder="Semua ruang"
                            aria-label="Filter ruang"
                        />
                    </div>
                    <div class="min-w-36 flex-1">
                        <SimpleSelect
                            v-model="statusFilter"
                            :options="statusOptions"
                            placeholder="Semua status"
                            aria-label="Filter status"
                        />
                    </div>
                </div>

                <Card class="rounded-2xl border-border/70">
                    <CardContent class="p-2 sm:p-4">
                        <ul v-if="filteredEntries.length > 0" class="divide-y divide-border/60">
                            <li
                                v-for="entry in filteredEntries"
                                :key="`${entry.queue_number}-${entry.display_name}`"
                                class="flex items-center gap-3 px-3 py-3 sm:gap-4 sm:px-4"
                            >
                                <span class="w-14 shrink-0 text-xl font-bold tabular-nums sm:w-20 sm:text-2xl">
                                    {{ queueNumberLabel(entry.queue_number) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-base font-medium sm:text-lg">
                                        {{ entry.display_name?.trim() || '—' }}
                                    </span>
                                    <span
                                        v-if="(entry.division ?? '').trim() !== ''"
                                        class="text-muted-foreground block truncate text-xs sm:text-sm"
                                    >
                                        {{ entry.division }}
                                    </span>
                                </span>
                                <Badge variant="outline" class="shrink-0">
                                    {{ entryStatusLabel(entry) }}
                                </Badge>
                            </li>
                        </ul>
                        <p v-else class="text-muted-foreground px-4 py-8 text-center text-sm sm:text-base">
                            Belum ada antrean untuk filter ini.
                        </p>
                    </CardContent>
                </Card>
            </section>

            <p class="text-muted-foreground mt-6 text-center text-xs sm:text-sm">
                {{ lastUpdatedLabel }} · Memperbarui otomatis setiap 15 detik.
            </p>
        </div>
    </div>
</template>
