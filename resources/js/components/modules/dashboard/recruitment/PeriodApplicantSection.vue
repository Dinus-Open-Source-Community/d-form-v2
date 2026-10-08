<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { type IPaginatorLink, type IPaginatorMeta } from '@/lib/paginatorLinks'
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { ArrowRight, Check, ChevronLeft, ChevronRight, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select'
import { routes } from '@/lib/routes'

interface ApplicationRow {
    id: string
    registration_number: string
    full_name: string
    nim: string
    semester: number
    stage: string
    stage_label: string
    result: string
    result_label: string
    revision_required: boolean
    submitted_at: string | null
    /** Status kirim link grup terakhir: sent | failed | null (belum pernah). */
    group_link_status?: string | null
    /** Status kirim QR terakhir: sent | failed | queued | null (belum pernah). */
    qr_status?: 'sent' | 'failed' | 'queued' | string | null
    /** Pesan error kirim QR terakhir (bila gagal). */
    qr_error?: string | null
    primary_division: { id: string; name: string } | null
    secondary_division: { id: string; name: string } | null
    period: { id: string; name: string } | null
}

interface ApplicationPaginator extends IPaginatorMeta {
    data: ApplicationRow[]
}

const QUEUE_OPTIONS = [
    { key: '', label: 'Semua antrean' },
    { key: 'screening', label: 'Screening' },
    { key: 'revision', label: 'Revisi' },
    { key: 'interview', label: 'Interview' },
    { key: 'final', label: 'Final' },
    { key: 'done', label: 'Selesai' },
] as const

const props = withDefaults(
    defineProps<{
        periodId: string
        /** Paginator kontrak 8a FINAL; null = tanpa izin, undefined = key absent (tab lain). */
        applications: ApplicationPaginator | null | undefined
        queueCounts: Record<string, number>
        divisionOptions: { id: string; name: string; code: string }[]
        stageOptions: { value: string; label: string }[]
        semesterOptions?: { value: string; label: string }[]
        tab: string
        canScreen?: boolean
        /** Link grup WA periode (null = belum diisi di Settings). */
        whatsappGroupUrl?: string | null
        /** Jumlah applicant eligible kirim link grup (lolos, bukan rejected). */
        groupLinkEligibleCount?: number
        selectedId?: string | null
        query?: {
            search?: string
            division_id?: string
            stage?: string
            queue?: string
            semester?: string
            per_page?: string | number
        }
    }>(),
    { semesterOptions: () => [], canScreen: false, query: () => ({}), whatsappGroupUrl: null, groupLinkEligibleCount: 0 },
)

const emit = defineEmits<{
    select: [id: string]
    deselect: []
}>()

const rowRefs = ref<Record<string, HTMLElement | null>>({})
let lastSelectedId: string | null = null

function setRowRef(id: string, el: unknown): void {
    rowRefs.value[id] = (el as HTMLElement | null) ?? null
}

const rowIds = computed<string[]>(() => serverRows.value.map((row) => row.id))

function focusRowAt(index: number): void {
    const ids = rowIds.value
    if (ids.length === 0) return
    const clamped = Math.max(0, Math.min(ids.length - 1, index))
    rowRefs.value[ids[clamped] ?? '']?.focus()
}

function moveRowFocus(currentId: string, delta: number): void {
    const index = rowIds.value.indexOf(currentId)
    if (index === -1) return
    focusRowAt(index + delta)
}

watch(
    () => props.selectedId,
    (value) => {
        if (value) {
            lastSelectedId = value
            return
        }
        const target = lastSelectedId ? rowRefs.value[lastSelectedId] : null
        if (target) {
            target.focus()
        } else {
            ;(document.querySelector('[data-applicant-list]') as HTMLElement | null)?.focus()
        }
        lastSelectedId = null
    },
)

const search = ref<string>(String(props.query?.search ?? ''))
const divisionId = ref<string>(String(props.query?.division_id ?? ''))
const stage = ref<string>(String(props.query?.stage ?? ''))
const queue = ref<string>(String(props.query?.queue ?? ''))
const semester = ref<string>(String(props.query?.semester ?? ''))
/** Navigasi halaman server (?tab=peserta&page=N, replace agar tak menumpuk riwayat). */
const isNavigating = ref<boolean>(false)

const divisionSelectOptions = computed<SimpleSelectOption[]>(() => [
    { value: '', label: 'Semua divisi' },
    ...props.divisionOptions.map((division) => ({ value: division.id, label: division.name })),
])

const queueSelectOptions = computed<SimpleSelectOption[]>(() =>
    QUEUE_OPTIONS.map((option) => {
        const count =
            option.key === '' ? (props.queueCounts.all ?? 0) : (props.queueCounts[option.key] ?? 0)
        return {
            value: option.key,
            label: count > 0 ? `${option.label} (${count})` : option.label,
        }
    }),
)

const stageSelectOptions = computed<SimpleSelectOption[]>(() => [
    { value: '', label: 'Semua tahap' },
    ...props.stageOptions.map((option) => ({ value: option.value, label: option.label })),
])

const semesterSelectOptions = computed<SimpleSelectOption[]>(() => {
    const options: SimpleSelectOption[] = [
        { value: '', label: 'Semua semester' },
        ...(props.semesterOptions ?? []).map((option) => ({ value: option.value, label: option.label })),
    ]

    const active = semester.value
    if (active === '' || options.some((option) => option.value === active)) {
        return options
    }

    return [...options, { value: active, label: /^\d+$/.test(active) ? `Semester ${active}` : active }]
})

const queueModel = computed<string>({
    get: () => queue.value,
    set: (value: string) => {
        queue.value = value
        if (value) {
            stage.value = ''
        }
    },
})

const serverRows = computed<ApplicationRow[]>(() => props.applications?.data ?? [])

/** Filter dikirim ke server sebagai query param (partial reload); bukan filter klien. */
function serverFilterParams(): Record<string, string> {
    const params: Record<string, string> = { tab: 'peserta' }
    const needle: string = search.value.trim()
    if (needle !== '') params.search = needle
    if (divisionId.value !== '') params.division_id = divisionId.value
    if (queue.value !== '') {
        params.queue = queue.value
    } else if (stage.value !== '') {
        params.stage = stage.value
    }
    if (semester.value !== '') params.semester = semester.value
    return params
}

function applyServerFilters(): void {
    if (isNavigating.value) return
    router.get(routes.admin.recruitment.periods.show(props.periodId), serverFilterParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['tab', 'query', 'applications'],
    })
}

let searchTimer: ReturnType<typeof setTimeout> | null = null

function clearSearchTimer(): void {
    if (searchTimer !== null) {
        clearTimeout(searchTimer)
        searchTimer = null
    }
}

watch(search, (): void => {
    clearSearchTimer()
    searchTimer = setTimeout((): void => {
        applyServerFilters()
    }, 300)
})

watch([divisionId, stage, queue, semester], (): void => {
    applyServerFilters()
})

watch(
    () => props.query,
    (next): void => {
        search.value = String(next?.search ?? '')
        divisionId.value = String(next?.division_id ?? '')
        stage.value = String(next?.stage ?? '')
        queue.value = String(next?.queue ?? '')
        semester.value = String(next?.semester ?? '')
    },
)

onBeforeUnmount((): void => {
    clearSearchTimer()
})

/** Meta paginator server; key absent (tab lain) dianggap halaman kosong. */
const serverMeta = computed<{
    currentPage: number
    lastPage: number
    total: number
    from: number | null
    to: number | null
    links: IPaginatorLink[]
}>(() => {
    const value: ApplicationPaginator | null | undefined = props.applications
    if (value === null || value === undefined) {
        return { currentPage: 1, lastPage: 1, total: 0, from: null, to: null, links: [] }
    }
    return {
        currentPage: value.current_page,
        lastPage: value.last_page,
        total: value.total,
        from: value.from ?? null,
        to: value.to ?? null,
        links: value.links ?? [],
    }
})

const hasActiveFilter = computed<boolean>(() => {
    return (
        search.value.trim() !== '' ||
        divisionId.value !== '' ||
        stage.value !== '' ||
        queue.value !== '' ||
        semester.value !== ''
    )
})

const rangeLabel = computed<string>(() => {
    const meta = serverMeta.value
    const total: string = meta.total.toLocaleString('id-ID')
    const from: string = (meta.from ?? (meta.total > 0 ? 1 : 0)).toLocaleString('id-ID')
    const to: string = (meta.to ?? meta.total).toLocaleString('id-ID')
    return `Menampilkan ${from}–${to} dari ${total} applicant`
})

/** Baris per halaman paginator server (kontrak: paginate 15). */
const perPage = computed<number>(() => props.applications?.per_page ?? 15)

/**
 * Pindah halaman via links[] paginator (url sudah membawa ?tab=peserta&page=N).
 * Partial reload + replace agar riwayat tak menumpuk.
 */
function goToUrl(url: string | null): void {
    if (url === null || props.applications == null || isNavigating.value) return
    router.get(
        url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: [
                'period',
                'tab',
                'query',
                'applications',
                'queue_counts',
                'screening_reason_options',
                'division_options',
                'membership_type_options',
                'divisionOptions',
                'semesterOptions',
                'stageOptions',
            ],
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
    if (pageNumber === serverMeta.value.currentPage || isNavigating.value) return
    const target: string | null =
        serverMeta.value.links.find((link) => link.label === String(pageNumber))?.url ?? null
    goToUrl(target)
}

interface RejectReasonOption {
    value: string
    label: string
}

const REJECT_REASONS: RejectReasonOption[] = [
    { value: 'incomplete_data', label: 'Data tidak lengkap' },
    { value: 'invalid_data', label: 'Data tidak valid' },
    { value: 'document_mismatch', label: 'Dokumen tidak sesuai' },
    { value: 'document_unreadable', label: 'Dokumen tidak dapat dibaca' },
    { value: 'info_mismatch', label: 'Informasi tidak sesuai' },
    { value: 'requirements_not_met', label: 'Persyaratan tidak terpenuhi' },
    { value: 'other', label: 'Lainnya' },
]

function canDecide(row: ApplicationRow): boolean {
    if (!props.canScreen) return false
    if (row.revision_required) return false
    if (row.result !== 'pending') return false
    return row.stage === 'submitted' || row.stage === 'screening'
}

const processingId = ref<string | null>(null)
const passTarget = ref<ApplicationRow | null>(null)
const passDialogOpen = ref(false)

const rejectTarget = ref<ApplicationRow | null>(null)
const rejectDialogOpen = ref(false)
const rejectLocalError = ref<string | null>(null)
const rejectForm = useForm({
    reason: '',
    notes: '',
    public_message: '',
})

function openPass(row: ApplicationRow): void {
    if (!canDecide(row) || processingId.value !== null) return
    passTarget.value = row
    passDialogOpen.value = true
}

function cancelPass(): void {
    passDialogOpen.value = false
}

function confirmPass(): void {
    const target = passTarget.value
    if (target === null || processingId.value !== null) return
    if (!hasGroupLink.value) {
        passDialogOpen.value = false
        passGroupLinkInput.value = ''
        passGroupLinkInclude.value = true
        passGroupLinkError.value = null
        passGroupLinkDialogOpen.value = true
        return
    }
    processingId.value = target.id
    router.post(
        routes.admin.recruitment.applications.screening.pass(target.id),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Applicant lolos screening.')
            },
            onError: () => {
                toast.error('Gagal meloloskan applicant.')
            },
            onFinish: () => {
                processingId.value = null
                passTarget.value = null
                passDialogOpen.value = false
            },
        },
    )
}

const passGroupLinkDialogOpen = ref(false)
const passGroupLinkInput = ref('')
const passGroupLinkInclude = ref(true)
const passGroupLinkError = ref<string | null>(null)

function cancelPassGroupLink(): void {
    passGroupLinkDialogOpen.value = false
    passGroupLinkError.value = null
}

function submitPassGroupLink(): void {
    const target = passTarget.value
    if (target === null || processingId.value !== null) return
    if (!passGroupLinkInclude.value) {
        passGroupLinkError.value = null
        processingId.value = target.id
        router.post(
            routes.admin.recruitment.applications.screening.pass(target.id),
            { include_group_link: false },
            {
                preserveState: true,
                preserveScroll: true,
                onError: () => {
                    passGroupLinkError.value = 'Gagal meloloskan applicant.'
                    toast.error('Gagal meloloskan applicant.')
                },
                onSuccess: () => {
                    passGroupLinkDialogOpen.value = false
                    passTarget.value = null
                    toast.success('Applicant lolos screening tanpa link grup.')
                },
                onFinish: () => {
                    processingId.value = null
                },
            },
        )
        return
    }
    const value = passGroupLinkInput.value.trim()
    if (value === '') {
        passGroupLinkError.value = 'Link grup WA wajib diisi bila toggle menyertakan link aktif.'
        return
    }
    if (!value.startsWith('https://')) {
        passGroupLinkError.value = 'Link grup WA harus diawali https://.'
        return
    }
    passGroupLinkError.value = null
    processingId.value = target.id
    router.post(
        routes.admin.recruitment.applications.screening.pass(target.id),
        { whatsapp_group_url: value, include_group_link: true },
        {
            preserveState: true,
            preserveScroll: true,
            onError: (errors: Record<string, string | string[]>) => {
                const first = errors['whatsapp_group_url'] ?? errors['application']
                passGroupLinkError.value =
                    (Array.isArray(first) ? first[0] : first) ?? 'Gagal menyimpan link grup.'
                toast.error(passGroupLinkError.value)
            },
            onSuccess: () => {
                passGroupLinkDialogOpen.value = false
                passTarget.value = null
                toast.success('Link grup tersimpan. Applicant lolos screening.')
            },
            onFinish: () => {
                processingId.value = null
            },
        },
    )
}

/** Link grup WA tersedia bila periode menyimpannya (diisi di tab Settings). */
const hasGroupLink = computed<boolean>(
    () => props.whatsappGroupUrl !== null && props.whatsappGroupUrl !== '',
)

function openReject(row: ApplicationRow): void {
    if (!canDecide(row) || processingId.value !== null) return
    rejectTarget.value = row
    rejectForm.reset()
    rejectForm.clearErrors()
    rejectLocalError.value = null
    rejectDialogOpen.value = true
}

function closeReject(): void {
    rejectDialogOpen.value = false
    rejectTarget.value = null
    rejectLocalError.value = null
    rejectForm.reset()
    rejectForm.clearErrors()
}

function submitReject(): void {
    const target = rejectTarget.value
    if (target === null || rejectForm.processing) return
    if (rejectForm.reason === '') {
        rejectLocalError.value = 'Alasan penolakan wajib diisi.'
        return
    }
    if (rejectForm.reason === 'other' && rejectForm.notes.trim() === '') {
        rejectLocalError.value = 'Catatan wajib diisi jika alasan "Lainnya".'
        return
    }
    rejectLocalError.value = null
    processingId.value = target.id
    rejectForm.post(
        routes.admin.recruitment.applications.screening.reject(target.id),
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                closeReject()
                toast.success('Applicant ditolak pada tahap screening.')
            },
            onError: () => {
                toast.error('Gagal menolak applicant.')
            },
            onFinish: () => {
                processingId.value = null
            },
        },
    )
}
</script>

<template>
    <section class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold tracking-wide uppercase text-muted-foreground">
                Applicant periode ini
            </h2>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <Input v-model="search" placeholder="Cari nama, NIM, nomor pendaftaran..." class="max-w-xs" />
            <div class="flex min-w-0 flex-col gap-1.5">
                <SimpleSelect
                    v-model="divisionId"
                    :options="divisionSelectOptions"
                    id="filter-divisi"
                    class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                    aria-label="Filter divisi"
                />
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
                <SimpleSelect
                    v-model="queueModel"
                    :options="queueSelectOptions"
                    id="filter-antrean"
                    class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                    aria-label="Filter antrean"
                />
            </div>
            <div v-if="!queue" class="flex min-w-0 flex-col gap-1.5">
                <SimpleSelect
                    v-model="stage"
                    :options="stageSelectOptions"
                    id="filter-tahap"
                    class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                    aria-label="Filter tahap"
                />
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
                <SimpleSelect
                    v-model="semester"
                    :options="semesterSelectOptions"
                    id="filter-semester"
                    class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                    aria-label="Filter semester"
                />
            </div>
        </div>

        <Card
            v-if="applications"
            data-applicant-list
            tabindex="-1"
            class="rounded-2xl border-border/70 overflow-hidden focus-visible:outline-none"
            :aria-busy="isNavigating"
        >
            <CardContent class="p-0" :class="isNavigating && 'opacity-60 transition-opacity'">
                <p v-if="isNavigating" role="status" class="border-b px-4 py-2 text-xs text-muted-foreground">
                    Memuat halaman {{ serverMeta.currentPage }}…
                </p>
                <div class="overflow-x-auto overflow-y-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 border-b text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nomor</th>
                                <th class="px-4 py-3 font-medium">Nama</th>
                                <th class="px-4 py-3 font-medium">NIM</th>
                                <th class="px-4 py-3 font-medium">Divisi</th>
                                <th class="px-4 py-3 font-medium">Tahap</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Link Grup</th>
                                <th class="px-4 py-3 font-medium">QR</th>
                                <th class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in serverRows"
                                :key="row.id"
                                :ref="(el) => setRowRef(row.id, el)"
                                tabindex="0"
                                :aria-current="selectedId === row.id ? 'true' : undefined"
                                class="border-b last:border-0 cursor-pointer transition-colors hover:bg-muted/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring/40"
                                :class="[selectedId === row.id ? 'bg-muted/40' : '', row.qr_status === 'failed' ? 'bg-destructive/[0.04]' : '']"
                                @click="emit('select', row.id)"
                                @keydown.enter.prevent="emit('select', row.id)"
                                @keydown.arrow-down.prevent="moveRowFocus(row.id, 1)"
                                @keydown.arrow-up.prevent="moveRowFocus(row.id, -1)"
                            >
                                <td class="px-4 py-3 font-mono text-xs">{{ row.registration_number }}</td>
                                <td class="px-4 py-3 font-medium">{{ row.full_name }}</td>
                                <td class="px-4 py-3">{{ row.nim }}</td>
                                <td class="px-4 py-3">{{ row.primary_division?.name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="row.revision_required ? 'bg-amber-100 text-amber-800' : 'bg-muted text-muted-foreground'"
                                    >
                                        {{ row.stage_label }}
                                        <span v-if="row.revision_required"> · Revisi</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.result_label }}</td>
                                <td class="px-4 py-3">
                                    <Badge
                                        v-if="row.group_link_status === 'sent'"
                                        variant="secondary"
                                    >
                                        Terkirim
                                    </Badge>
                                    <Badge
                                        v-else-if="row.group_link_status === 'failed'"
                                        variant="destructive"
                                    >
                                        Gagal
                                    </Badge>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        v-if="row.qr_status === 'sent'"
                                        variant="secondary"
                                    >
                                        Terkirim
                                    </Badge>
                                    <Badge
                                        v-else-if="row.qr_status === 'failed'"
                                        variant="destructive"
                                        :title="row.qr_error ?? 'Pengiriman gagal. Kirim ulang QR untuk mencoba lagi.'"
                                    >
                                        Gagal
                                    </Badge>
                                    <span
                                        v-else-if="row.qr_status === 'queued'"
                                        class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                        title="Menunggu giliran pengiriman QR."
                                    >
                                        Antrean
                                    </span>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-0.5">
                                        <Button
                                            v-if="canDecide(row)"
                                            radius="icon"
                                            variant="ghost"
                                            size="icon-sm"
                                            class="text-success hover:text-success"
                                            :aria-label="`Loloskan ${row.full_name}`"
                                            :disabled="processingId === row.id"
                                            @click.stop="openPass(row)"
                                        >
                                            <Check class="size-4" aria-hidden="true" />
                                        </Button>
                                        <Button
                                            v-if="canDecide(row)"
                                            radius="icon"
                                            variant="ghost"
                                            size="icon-sm"
                                            class="text-destructive hover:text-destructive"
                                            :aria-label="`Tolak ${row.full_name}`"
                                            :disabled="processingId === row.id"
                                            @click.stop="openReject(row)"
                                        >
                                            <X class="size-4" aria-hidden="true" />
                                        </Button>
                                        <Button
                                            radius="icon"
                                            variant="ghost"
                                            size="icon-sm"
                                            :aria-label="`Lihat detail ${row.full_name}`"
                                            @click.stop="emit('select', row.id)"
                                        >
                                            <ArrowRight class="size-4" aria-hidden="true" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="serverRows.length === 0">
                                <td colspan="9" class="text-muted-foreground px-4 py-10 text-center">
                                    {{
                                        hasActiveFilter
                                            ? 'Belum ada applicant untuk periode ini yang cocok dengan filter.'
                                            : 'Belum ada applicant untuk periode ini.'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="applications"
            class="flex flex-col items-center gap-3 text-sm"
        >
            <Pagination
                v-if="serverMeta.lastPage > 1"
                :page="serverMeta.currentPage"
                :total="serverMeta.total"
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
                            :is-active="item.value === serverMeta.currentPage"
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
            <p class="text-muted-foreground">{{ rangeLabel }}</p>
        </div>

        <ConfirmationModal
            :open="passDialogOpen"
            title="Loloskan applicant?"
            :description="
                passTarget
                    ? `${passTarget.full_name} akan dipindahkan ke tahap interview.`
                    : 'Applicant akan dipindahkan ke tahap interview.'
            "
            confirm-text="Loloskan"
            :loading="passTarget !== null && processingId === passTarget.id"
            @confirm="confirmPass"
            @cancel="cancelPass"
            @update:open="(v: boolean) => { passDialogOpen = v }"
        />

        <Dialog v-model:open="passGroupLinkDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Link grup WA belum diisi</DialogTitle>
                    <DialogDescription>
                        {{
                            passTarget
                                ? `Periode ${passTarget.full_name} belum punya link grup. Isi sekarang atau matikan toggle bila tidak pakai grup.`
                                : 'Periode ini belum punya link grup. Isi sekarang atau matikan toggle bila tidak pakai grup.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="flex items-center justify-between gap-3 rounded-xl border p-3">
                    <div class="space-y-0.5">
                        <Label for="quick-pass-include-group">Sertakan link grup di email</Label>
                        <p class="text-muted-foreground text-xs">
                            {{
                                passGroupLinkInclude
                                    ? 'Email lolos akan ada tombol Gabung Grup WA.'
                                    : 'Email lolos dikirim tanpa blok link grup.'
                            }}
                        </p>
                    </div>
                    <Switch id="quick-pass-include-group" v-model="passGroupLinkInclude" />
                </div>

                <div class="space-y-2">
                    <Label for="quick-pass-wa-link">Link grup WA</Label>
                    <input
                        id="quick-pass-wa-link"
                        v-model="passGroupLinkInput"
                        type="url"
                        inputmode="url"
                        placeholder="https://chat.whatsapp.com/..."
                        :disabled="!passGroupLinkInclude || (passTarget !== null && processingId === passTarget.id)"
                        class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <p v-if="passGroupLinkError" class="text-destructive text-xs">
                        {{ passGroupLinkError }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="cancelPassGroupLink">
                        Batal
                    </Button>
                    <Button
                        type="button"
                        :disabled="passTarget !== null && processingId === passTarget.id"
                        @click="submitPassGroupLink"
                    >
                        {{
                            passTarget !== null && processingId === passTarget.id
                                ? 'Menyimpan…'
                                : passGroupLinkInclude
                                  ? 'Simpan & loloskan'
                                  : 'Loloskan tanpa link grup'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="rejectDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Tolak applicant</DialogTitle>
                    <DialogDescription>
                        {{
                            rejectTarget
                                ? `Tolak ${rejectTarget.full_name}? Alasan wajib diisi.`
                                : 'Alasan penolakan wajib diisi.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitReject">
                    <div class="space-y-2">
                        <Label for="quick-reject-reason">Alasan</Label>
                        <select
                            id="quick-reject-reason"
                            v-model="rejectForm.reason"
                            class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            required
                        >
                            <option value="" disabled>Pilih alasan</option>
                            <option
                                v-for="opt in REJECT_REASONS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <p v-if="rejectForm.errors.reason" class="text-destructive text-xs">
                            {{ rejectForm.errors.reason }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="quick-reject-notes">Catatan</Label>
                        <textarea
                            id="quick-reject-notes"
                            v-model="rejectForm.notes"
                            rows="3"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Catatan internal untuk tim..."
                        />
                        <p v-if="rejectForm.errors.notes" class="text-destructive text-xs">
                            {{ rejectForm.errors.notes }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="quick-reject-public-message">Pesan untuk applicant (opsional)</Label>
                        <textarea
                            id="quick-reject-public-message"
                            v-model="rejectForm.public_message"
                            rows="2"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                        />
                    </div>

                    <p v-if="rejectLocalError" class="text-destructive text-xs">
                        {{ rejectLocalError }}
                    </p>
                    <p v-if="rejectForm.errors.application" class="text-destructive text-xs">
                        {{ rejectForm.errors.application }}
                    </p>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="closeReject">
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="rejectForm.processing"
                        >
                            Tolak applicant
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>
