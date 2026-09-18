<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import axios from 'axios'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import type { QueueSnapshot } from '@/utils/composables/useRecruitmentQueue'
import { ChevronLeft, ChevronRight, RefreshCw, WifiOff } from 'lucide-vue-next'

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

const POLL_INTERVAL_MS = 10_000
const PAGE_SIZE = 20

const props = defineProps<{
    session: SessionDetail
    queue: QueueSnapshot
    pollUrl: string
}>()

const snapshot = ref<QueueSnapshot>(props.queue)
const loadError = ref<boolean>(false)
const isRefreshing = ref<boolean>(false)
const isOffline = ref<boolean>(false)
const lastUpdatedAt = ref<Date | null>(null)
const entryPage = ref<number>(1)

let pollTimer: ReturnType<typeof setInterval> | null = null

function formatInt(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value)
}

function formatSessionDay(value: string): string {
    const parsed: Date = new Date(`${value}T00:00:00`)
    if (Number.isNaN(parsed.getTime())) return value
    return parsed.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    })
}

function queueNumberLabel(value: number): string {
    return `#${String(value).padStart(2, '0')}`
}

async function refreshQueue(): Promise<void> {
    if (isRefreshing.value) return
    isRefreshing.value = true
    try {
        const response = await axios.get<QueueSnapshot>(props.pollUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        snapshot.value = response.data
        loadError.value = false
        lastUpdatedAt.value = new Date()
        if (entryPage.value > entryLastPage.value) entryPage.value = entryLastPage.value
    } catch {
        loadError.value = true
    } finally {
        isRefreshing.value = false
    }
}

function startPolling(): void {
    if (pollTimer !== null) return
    pollTimer = setInterval((): void => {
        void refreshQueue()
    }, POLL_INTERVAL_MS)
}

function stopPolling(): void {
    if (pollTimer !== null) {
        clearInterval(pollTimer)
        pollTimer = null
    }
}

function handleOnline(): void {
    isOffline.value = false
    void refreshQueue()
}

function handleOffline(): void {
    isOffline.value = true
}

const totalEntries = computed<number>((): number => snapshot.value.entries.length)

const entryLastPage = computed<number>((): number =>
    Math.max(1, Math.ceil(totalEntries.value / PAGE_SIZE)),
)

const pagedEntries = computed<QueueSnapshot['entries']>((): QueueSnapshot['entries'] => {
    const start: number = (entryPage.value - 1) * PAGE_SIZE
    return snapshot.value.entries.slice(start, start + PAGE_SIZE)
})

function goToEntryPage(page: number): void {
    if (page < 1 || page > entryLastPage.value) return
    entryPage.value = page
}

const entryRangeStart = computed<number>((): number => {
    if (totalEntries.value === 0) return 0
    return (entryPage.value - 1) * PAGE_SIZE + 1
})

const entryRangeEnd = computed<number>((): number =>
    Math.min(totalEntries.value, entryPage.value * PAGE_SIZE),
)

const entryRangeLabel = computed<string>(
    (): string =>
        `Menampilkan ${formatInt(entryRangeStart.value)}–${formatInt(entryRangeEnd.value)} dari ${formatInt(totalEntries.value)}`,
)

const lastUpdatedLabel = computed<string>((): string => {
    if (lastUpdatedAt.value === null) return 'Belum pernah diperbarui'
    return `Terakhir diperbarui ${lastUpdatedAt.value.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    })}`
})

onMounted((): void => {
    isOffline.value = typeof navigator !== 'undefined' ? !navigator.onLine : false
    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)
    startPolling()
    setTopbar({
        title: 'Antrean sesi',
        subtitle: props.session.division?.name ?? '',
    })
})

onUnmounted((): void => {
    stopPolling()
    window.removeEventListener('online', handleOnline)
    window.removeEventListener('offline', handleOffline)
})
</script>

<template>
    <Head title="Antrean Interview Saya" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <Card class="rounded-2xl border-border/70">
            <CardContent class="flex flex-wrap items-center justify-between gap-4 p-5">
                <div class="min-w-0">
                    <p class="text-base font-semibold">{{ session.division?.name ?? 'Interview' }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ formatSessionDay(session.session_date) }} · {{ session.starts_at }}–{{
                            session.ends_at
                        }}
                    </p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ session.location }} · {{ session.room }}
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="isRefreshing"
                    @click="refreshQueue"
                >
                    <RefreshCw
                        class="mr-2 size-4"
                        :class="isRefreshing ? 'animate-spin' : ''"
                        aria-hidden="true"
                    />
                    {{ isRefreshing ? 'Memuat…' : 'Muat ulang' }}
                </Button>
            </CardContent>
        </Card>

        <div
            v-if="isOffline || loadError"
            role="alert"
            class="rounded-2xl border border-warning/25 bg-warning/10 px-4 py-3 text-sm"
        >
            <p class="flex items-center gap-2 font-medium">
                <WifiOff v-if="isOffline" class="size-4 shrink-0" aria-hidden="true" />
                {{
                    isOffline
                        ? 'Kamu sedang offline. Data terakhir tetap ditampilkan.'
                        : 'Gagal memperbarui antrean. Periksa koneksi lalu coba lagi.'
                }}
            </p>
            <Button variant="outline" size="sm" class="mt-2" :disabled="isRefreshing" @click="refreshQueue">
                <RefreshCw
                    class="mr-2 size-4"
                    :class="isRefreshing ? 'animate-spin' : ''"
                    aria-hidden="true"
                />
                Coba lagi
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <Card class="rounded-2xl">
                <CardHeader class="pb-2"><CardTitle class="text-sm">Menunggu</CardTitle></CardHeader>
                <CardContent
                    ><p class="text-2xl font-bold tabular-nums">{{ formatInt(snapshot.stats.waiting) }}</p></CardContent
                >
            </Card>
            <Card class="rounded-2xl">
                <CardHeader class="pb-2"><CardTitle class="text-sm">Dipanggil</CardTitle></CardHeader>
                <CardContent
                    ><p class="text-2xl font-bold tabular-nums">{{ formatInt(snapshot.stats.called) }}</p></CardContent
                >
            </Card>
            <Card class="rounded-2xl">
                <CardHeader class="pb-2"><CardTitle class="text-sm">Selesai</CardTitle></CardHeader>
                <CardContent
                    ><p class="text-2xl font-bold tabular-nums">
                        {{ formatInt(snapshot.stats.completed) }}
                    </p></CardContent
                >
            </Card>
        </div>

        <Card class="rounded-2xl border-border/70">
            <CardHeader>
                <CardTitle class="text-base">Sedang dilayani</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <template v-if="snapshot.current?.application">
                    <p class="text-2xl font-bold" aria-live="polite">
                        {{ queueNumberLabel(snapshot.current.queue_number) }}
                    </p>
                    <p class="font-medium">{{ snapshot.current.application.full_name }}</p>
                    <Button as-child size="sm">
                        <Link
                            :href="
                                routes.admin.recruitment.myInterviews.show(
                                    snapshot.current.application.id,
                                )
                            "
                        >
                            Buka &amp; nilai
                        </Link>
                    </Button>
                </template>
                <p v-else class="text-sm text-muted-foreground">Belum ada yang dipanggil.</p>

                <div
                    v-if="snapshot.next?.application"
                    class="rounded-xl border border-border/60 p-4 text-sm"
                >
                    <p class="mb-1 text-muted-foreground">Berikutnya</p>
                    <p class="font-medium">
                        {{ queueNumberLabel(snapshot.next.queue_number) }} —
                        {{ snapshot.next.application.full_name }}
                    </p>
                </div>

                <div class="overflow-x-auto pt-2">
                    <table v-if="pagedEntries.length > 0" class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="pr-4 pb-2">No.</th>
                                <th class="pr-4 pb-2">Applicant</th>
                                <th class="pb-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="entry in pagedEntries"
                                :key="entry.id"
                                class="border-b border-border/50"
                            >
                                <td class="py-2 pr-4 font-mono">{{ queueNumberLabel(entry.queue_number) }}</td>
                                <td class="py-2 pr-4">{{ entry.application?.full_name ?? '—' }}</td>
                                <td class="py-2">
                                    <Badge variant="outline">{{ entry.status_label }}</Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-muted-foreground">Belum ada antrean pada sesi ini.</p>
                </div>
                <div v-if="totalEntries > PAGE_SIZE" class="flex flex-col items-center gap-3 pt-2">
                    <Pagination
                        :page="entryPage"
                        :total="totalEntries"
                        :items-per-page="PAGE_SIZE"
                        :sibling-count="1"
                        @update:page="goToEntryPage"
                    >
                        <PaginationContent v-slot="{ items }">
                            <PaginationPrevious>
                                <ChevronLeft class="size-4" aria-hidden="true" />
                                <span class="hidden sm:block">Sebelumnya</span>
                            </PaginationPrevious>
                            <template v-for="(item, index) in items" :key="index">
                                <PaginationItem
                                    v-if="item.type === 'page'"
                                    :value="item.value"
                                    :is-active="item.value === entryPage"
                                    :aria-label="`Ke halaman ${item.value}`"
                                >
                                    {{ item.value }}
                                </PaginationItem>
                                <PaginationEllipsis v-else :index="index" />
                            </template>
                            <PaginationNext>
                                <span class="hidden sm:block">Berikutnya</span>
                                <ChevronRight class="size-4" aria-hidden="true" />
                            </PaginationNext>
                        </PaginationContent>
                    </Pagination>
                    <p class="text-sm text-muted-foreground">{{ entryRangeLabel }}</p>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ lastUpdatedLabel }} · Memperbarui otomatis setiap 10 detik.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
