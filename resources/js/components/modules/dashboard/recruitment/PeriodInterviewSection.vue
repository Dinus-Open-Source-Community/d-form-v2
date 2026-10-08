<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight, Download, Eye, Pencil, Plus, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
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
import InterviewSessionEditSheet, {
    type EditableInterviewSession,
} from '@/components/modules/dashboard/recruitment/InterviewSessionEditSheet.vue'
import { routes } from '@/lib/routes'
import { toast } from 'vue-sonner'

interface SessionRow {
    id: string
    session_date: string
    starts_at: string
    ends_at: string
    location: string
    room: string
    notes?: string | null
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
/** Sesi yang sedang diedit; null = sheet edit tertutup. */
const editingSession = ref<EditableInterviewSession | null>(null)
const editOpen = computed<boolean>(() => editingSession.value !== null)
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

function openEdit(session: SessionRow): void {
    editingSession.value = session
}

function closeEdit(): void {
    editingSession.value = null
}

function destroyPath(id: string): string {
    return routes.admin.recruitment.interviewSessions.destroy(id)
}

function handleDelete(session: SessionRow): void {
    const divisionName: string = session.division?.name ?? '-'
    const confirmed: boolean = window.confirm(
        `Sesi tanggal ${session.session_date} divisi ${divisionName} akan dihapus. Data jadwal tetap aman?`,
    )
    if (!confirmed) return
    router.delete(destroyPath(session.id), {
        preserveScroll: true,
        only: ['period', 'tab', 'query', 'sessions', 'interview_division_options', 'queue_counts'],
    })
}

type ExportScope = 'all' | 'evaluated' | 'pending'

const EXPORT_SCOPES: { value: ExportScope; label: string; hint: string }[] = [
    { value: 'all', label: 'Semua interview', hint: 'Seluruh jadwal interview periode ini.' },
    { value: 'evaluated', label: 'Sudah dinilai', hint: 'Hanya interview yang sudah ada nilainya.' },
    { value: 'pending', label: 'Belum dinilai', hint: 'Hanya interview yang belum dinilai.' },
]

interface ExportColumn {
    key: string
    label: string
}

interface ExportColumnGroup {
    label: string
    columns: ExportColumn[]
}

/** Urutan kunci kolom persis kontrak backend (columns[] dikirim dalam urutan ini). */
const EXPORT_COLUMN_GROUPS: ExportColumnGroup[] = [
    {
        label: 'Identitas',
        columns: [
            { key: 'registration_number', label: 'No. Registrasi' },
            { key: 'full_name', label: 'Nama' },
            { key: 'nim', label: 'NIM' },
            { key: 'semester', label: 'Semester' },
            { key: 'phone', label: 'Telepon' },
            { key: 'personal_email', label: 'Email' },
        ],
    },
    {
        label: 'Divisi & Sesi',
        columns: [
            { key: 'primary_division', label: 'Divisi primer' },
            { key: 'secondary_division', label: 'Divisi sekunder' },
            { key: 'interview_kind', label: 'Jenis interview' },
            { key: 'session_date', label: 'Tanggal sesi' },
            { key: 'session_time', label: 'Jam sesi' },
            { key: 'location', label: 'Lokasi' },
            { key: 'room', label: 'Ruang' },
            { key: 'session_division', label: 'Divisi sesi' },
        ],
    },
    {
        label: 'Status',
        columns: [
            { key: 'interview_status', label: 'Status interview' },
            { key: 'checked_in_at', label: 'Waktu check-in' },
            { key: 'attendance_method', label: 'Metode absensi' },
            { key: 'interviewer_name', label: 'Interviewer' },
        ],
    },
    {
        label: 'Penilaian',
        columns: [
            { key: 'speaking_score', label: 'Speaking' },
            { key: 'technical_score', label: 'Teknis' },
            { key: 'attitude_score', label: 'Attitude' },
            { key: 'recommendation', label: 'Rekomendasi' },
            { key: 'save_count', label: 'Jumlah simpan' },
            { key: 'evaluated_at', label: 'Dinilai pada' },
        ],
    },
    {
        label: 'Hasil',
        columns: [
            { key: 'application_stage', label: 'Tahap' },
            { key: 'application_result', label: 'Hasil' },
            { key: 'final_division', label: 'Divisi final' },
            { key: 'membership_type', label: 'Tipe keanggotaan' },
        ],
    },
]

const ALL_EXPORT_COLUMN_KEYS: string[] = EXPORT_COLUMN_GROUPS.flatMap((group) =>
    group.columns.map((column) => column.key),
)

const exportOpen = ref<boolean>(false)
const exportScope = ref<ExportScope>('all')
const exportIncludeSecondary = ref<boolean>(true)
const exportColumns = ref<string[]>([...ALL_EXPORT_COLUMN_KEYS])

const exportColumnCountLabel = computed<string>(
    () => `${exportColumns.value.length} dari ${ALL_EXPORT_COLUMN_KEYS.length} kolom dipilih`,
)

function openExport(): void {
    exportScope.value = 'all'
    exportIncludeSecondary.value = true
    exportColumns.value = [...ALL_EXPORT_COLUMN_KEYS]
    exportOpen.value = true
}

function closeExport(): void {
    exportOpen.value = false
}

function isExportColumnChecked(key: string): boolean {
    return exportColumns.value.includes(key)
}

function setExportColumn(key: string, checked: boolean | 'indeterminate'): void {
    if (checked === true) {
        if (!exportColumns.value.includes(key)) exportColumns.value = [...exportColumns.value, key]
        return
    }
    exportColumns.value = exportColumns.value.filter((item) => item !== key)
}

function groupExportKeys(group: ExportColumnGroup): string[] {
    return group.columns.map((column) => column.key)
}

function isExportGroupComplete(group: ExportColumnGroup): boolean {
    return groupExportKeys(group).every((key) => exportColumns.value.includes(key))
}

function setExportGroup(group: ExportColumnGroup, checked: boolean): void {
    const keys = groupExportKeys(group)
    if (checked) {
        exportColumns.value = Array.from(new Set([...exportColumns.value, ...keys]))
        return
    }
    exportColumns.value = exportColumns.value.filter((key) => !keys.includes(key))
}

function submitExport(): void {
    if (exportColumns.value.length === 0) return
    const params = new URLSearchParams()
    params.set('scope', exportScope.value)
    if (exportIncludeSecondary.value) params.set('include_secondary', '1')
    const selected = new Set(exportColumns.value)
    for (const key of ALL_EXPORT_COLUMN_KEYS) {
        if (selected.has(key)) params.append('columns[]', key)
    }
    toast.info('Mengekspor…')
    exportOpen.value = false
    window.location.href = `${routes.admin.recruitment.periods.exportInterviews(props.periodId)}?${params.toString()}`
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold tracking-wide uppercase text-muted-foreground">
                Interview periode ini
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <Button size="sm" variant="outline" class="gap-1.5" @click="openExport">
                    <Download class="size-4" aria-hidden="true" />
                    Export CSV
                </Button>
                <Button size="sm" class="gap-1.5" @click="createOpen = true">
                    <Plus class="size-4" aria-hidden="true" />
                    Buat sesi
                </Button>
            </div>
        </div>

        <Dialog v-model:open="exportOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Export interview ke CSV</DialogTitle>
                    <DialogDescription>
                        Unduh rekap interview periode ini sebagai berkas CSV.
                    </DialogDescription>
                </DialogHeader>

                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium">Cakupan data</legend>
                    <div class="grid gap-2">
                        <label
                            v-for="option in EXPORT_SCOPES"
                            :key="option.value"
                            class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-border/70 px-3 py-2.5"
                        >
                            <input
                                v-model="exportScope"
                                type="radio"
                                name="export-scope"
                                :value="option.value"
                                class="mt-0.5 size-4 shrink-0 accent-primary"
                            />
                            <span class="text-sm leading-snug">
                                {{ option.label }}
                                <span class="block text-xs text-muted-foreground">{{ option.hint }}</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <label
                    for="export-include-secondary"
                    class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-border/70 px-3 py-2.5"
                >
                    <Checkbox id="export-include-secondary" v-model:checked="exportIncludeSecondary" class="mt-0.5" />
                    <span class="text-sm leading-snug">
                        Sertakan interview secondary
                        <span class="block text-xs text-muted-foreground">
                            Selain interview primer, jadwal secondary ikut diekspor.
                        </span>
                    </span>
                </label>

                <div class="space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-medium">Kolom</p>
                        <p class="text-xs tabular-nums text-muted-foreground">{{ exportColumnCountLabel }}</p>
                    </div>
                    <div class="max-h-64 space-y-4 overflow-y-auto rounded-xl border border-border/70 p-3">
                        <section v-for="group in EXPORT_COLUMN_GROUPS" :key="group.label" class="space-y-1.5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    {{ group.label }}
                                </h4>
                                <div class="flex items-center gap-3">
                                    <button
                                        v-if="!isExportGroupComplete(group)"
                                        type="button"
                                        class="text-xs font-medium text-foreground underline-offset-4 hover:underline"
                                        @click="setExportGroup(group, true)"
                                    >
                                        Pilih semua
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="text-xs font-medium text-muted-foreground underline-offset-4 hover:underline"
                                        @click="setExportGroup(group, false)"
                                    >
                                        Kosongkan
                                    </button>
                                </div>
                            </div>
                            <ul class="grid gap-1 sm:grid-cols-2">
                                <li v-for="column in group.columns" :key="column.key">
                                    <label
                                        :for="`export-col-${column.key}`"
                                        class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-muted/40"
                                    >
                                        <Checkbox
                                            :id="`export-col-${column.key}`"
                                            :checked="isExportColumnChecked(column.key)"
                                            @update:checked="(checked) => setExportColumn(column.key, checked)"
                                        />
                                        <span class="min-w-0 truncate">{{ column.label }}</span>
                                    </label>
                                </li>
                            </ul>
                        </section>
                    </div>
                    <p v-if="exportColumns.length === 0" role="alert" class="text-xs text-destructive">
                        Pilih minimal satu kolom untuk mengekspor.
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeExport">
                        Batal
                    </Button>
                    <Button type="button" :disabled="exportColumns.length === 0" @click="submitExport">
                        <Download class="mr-2 size-4" aria-hidden="true" />
                        Export CSV
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <InterviewSessionCreateSheet
            :open="createOpen"
            :period-id="periodId"
            :divisions="divisionOptions"
            @close="createOpen = false"
        />

        <InterviewSessionEditSheet
            :open="editOpen"
            :session="editingSession"
            :divisions="divisionOptions"
            @close="closeEdit"
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
                                    <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                                        <Button as-child size="icon-sm" variant="ghost" aria-label="Detail sesi">
                                            <Link
                                                :href="routes.admin.recruitment.interviewSessions.show(session.id)"
                                            >
                                                <Eye class="size-4" aria-hidden="true" />
                                            </Link>
                                        </Button>
                                        <Button
                                            size="icon-sm"
                                            variant="ghost"
                                            aria-label="Edit sesi"
                                            @click="openEdit(session)"
                                        >
                                            <Pencil class="size-4" aria-hidden="true" />
                                        </Button>
                                        <Button
                                            size="icon-sm"
                                            variant="ghost"
                                            aria-label="Hapus sesi"
                                            class="text-destructive hover:text-destructive"
                                            @click="handleDelete(session)"
                                        >
                                            <Trash2 class="size-4" aria-hidden="true" />
                                        </Button>
                                    </div>
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
