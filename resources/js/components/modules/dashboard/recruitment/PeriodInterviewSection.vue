<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight, Plus } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { type IPaginatorMeta } from '@/lib/paginatorLinks'
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination'
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

interface SessionPaginator extends IPaginatorMeta {
    data: SessionRow[]
}

const props = defineProps<{
    /** Paginator kontrak 8a FINAL; null = key absent (tab lain) atau tanpa data. */
    sessions: SessionPaginator | null | undefined
    periodId: string
    divisionOptions: InterviewDivisionChoice[]
}>()

const createOpen = ref<boolean>(false)
/** Navigasi halaman sesi via links[] paginator (replace agar tak menumpuk riwayat). */
const isNavigating = ref<boolean>(false)

const rangeStart = computed<number>(() => {
    const sessions = props.sessions
    if (!sessions || sessions.total === 0) return 0
    if (sessions.from !== undefined && sessions.from !== null) return sessions.from
    const perPage: number = sessions.per_page ?? 15
    return (sessions.current_page - 1) * perPage + 1
})

const rangeEnd = computed<number>(() => {
    const sessions = props.sessions
    if (!sessions || sessions.total === 0) return 0
    if (sessions.to !== undefined && sessions.to !== null) return sessions.to
    const perPage: number = sessions.per_page ?? 15
    return Math.min(sessions.total, sessions.current_page * perPage)
})

const rangeLabel = computed<string>(() => {
    const total = (props.sessions?.total ?? 0).toLocaleString('id-ID')
    const start = rangeStart.value.toLocaleString('id-ID')
    const end = rangeEnd.value.toLocaleString('id-ID')
    return `Menampilkan ${start}–${end} dari ${total} sesi`
})

/** Baris per halaman paginator server. */
const perPage = computed<number>(() => props.sessions?.per_page ?? 15)

function goToUrl(url: string | null): void {
    if (url === null || props.sessions == null || isNavigating.value) return
    router.get(
        url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['period', 'tab', 'query', 'sessions', 'interview_division_options', 'queue_counts'],
            onStart: () => {
                isNavigating.value = true
            },
            onFinish: () => {
                isNavigating.value = false
            },
        },
    )
}

/**
 * Dipakai shadcn Pagination (@update:page): nomor halaman dipetakan ke URL
 * links[] paginator lalu didelegasikan ke goToUrl agar opsi navigasi sama.
 */
function goToPage(pageNumber: number): void {
    const sessions = props.sessions
    if (sessions === null || sessions === undefined) return
    if (pageNumber === sessions.current_page || isNavigating.value) return
    const target: string | null =
        sessions.links.find((link) => link.label === String(pageNumber))?.url ?? null
    goToUrl(target)
}
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

        <Card v-if="sessions" class="overflow-hidden rounded-2xl border-border/70" :aria-busy="isNavigating">
            <CardContent class="p-0" :class="isNavigating && 'opacity-60 transition-opacity'">
                <p v-if="isNavigating" role="status" class="border-b px-4 py-2 text-xs text-muted-foreground">
                    Memuat halaman {{ sessions.current_page }}…
                </p>
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
                                        <Link :href="routes.admin.recruitment.interviewSessions.show(session.id)">Detail</Link>
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="sessions.data.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                    Belum ada sesi interview untuk periode ini. Buat sesi pertama lewat tombol di
                                    atas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div v-if="sessions && sessions.last_page > 1" class="flex flex-col items-center gap-3">
            <Pagination
                :page="sessions.current_page"
                :total="sessions.total"
                :items-per-page="perPage"
                :sibling-count="1"
                @update:page="goToPage"
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
                            :is-active="item.value === sessions.current_page"
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
            <p class="text-sm text-muted-foreground">{{ rangeLabel }}</p>
        </div>
        <p v-else-if="sessions && sessions.data.length > 0" class="text-center text-sm text-muted-foreground">
            {{ rangeLabel }}
        </p>

        <p v-else-if="!sessions" class="text-muted-foreground text-sm">Data sesi tidak tersedia untuk tab ini.</p>
    </div>
</template>
