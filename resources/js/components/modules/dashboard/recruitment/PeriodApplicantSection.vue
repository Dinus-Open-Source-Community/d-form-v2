<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router, useForm, Link } from '@inertiajs/vue3'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { ArrowRight, Check, Megaphone, X } from 'lucide-vue-next'
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
    primary_division: { id: string; name: string } | null
    secondary_division: { id: string; name: string } | null
    period: { id: string; name: string } | null
}

interface ApplicationPaginator {
    data: ApplicationRow[]
    current_page: number
    last_page: number
    total: number
    per_page?: number
    from?: number | null
    to?: number | null
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
        /** Array penuh (BE kini) atau paginator (BE 8a kelak) — dinormalkan ke rows. */
        applications: ApplicationRow[] | ApplicationPaginator | null
        queueCounts: Record<string, number>
        divisionOptions: { id: string; name: string; code: string }[]
        stageOptions: { value: string; label: string }[]
        semesterOptions?: { value: string; label: string }[]
        tab: string
        canScreen?: boolean
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
    { semesterOptions: () => [], canScreen: false, query: () => ({}) },
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

const rowIds = computed<string[]>(() => filteredRows.value.map((row) => row.id))

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

const search = ref<string>('')
const divisionId = ref<string>('')
const stage = ref<string>('')
const queue = ref<string>('')
const semester = ref<string>('')
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

function matchesQueue(row: ApplicationRow, activeQueue: string): boolean {
    switch (activeQueue) {
        case 'screening':
            return (
                (row.stage === 'submitted' || row.stage === 'screening') &&
                row.result === 'pending' &&
                !row.revision_required
            )
        case 'revision':
            return row.revision_required
        case 'interview':
            return row.stage === 'interview'
        case 'final':
            return row.stage === 'final_review'
        case 'done':
            return row.stage === 'completed'
        default:
            return true
    }
}

const allRows = computed<ApplicationRow[]>(() => {
    const value = props.applications
    if (value === null) return []
    return Array.isArray(value) ? value : (value.data ?? [])
})

const filteredRows = computed<ApplicationRow[]>(() => {
    const needle: string = search.value.trim().toLowerCase()
    return allRows.value.filter((row) => {
        if (needle !== '') {
            const haystack: string =
                `${row.full_name} ${row.nim} ${row.registration_number}`.toLowerCase()
            if (!haystack.includes(needle)) return false
        }
        if (
            divisionId.value !== '' &&
            row.primary_division?.id !== divisionId.value &&
            row.secondary_division?.id !== divisionId.value
        )
            return false
        if (queue.value !== '') {
            if (!matchesQueue(row, queue.value)) return false
        } else if (stage.value !== '' && row.stage !== stage.value) {
            return false
        }
        if (semester.value !== '' && String(row.semester) !== semester.value) return false
        return true
    })
})

/** Meta paginator server; bentuk array lawas dianggap satu halaman penuh. */
const serverMeta = computed<{
    currentPage: number
    lastPage: number
    total: number
    from: number | null
    to: number | null
}>(() => {
    const value = props.applications
    if (value !== null && !Array.isArray(value)) {
        return {
            currentPage: value.current_page,
            lastPage: value.last_page,
            total: value.total,
            from: value.from ?? null,
            to: value.to ?? null,
        }
    }
    const total: number = Array.isArray(value) ? value.length : 0
    return {
        currentPage: 1,
        lastPage: 1,
        total,
        from: total > 0 ? 1 : null,
        to: total > 0 ? total : null,
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

const perPage = computed<number>(() => {
    const value = props.applications
    if (value !== null && !Array.isArray(value) && value.per_page !== undefined) return value.per_page
    return 15
})

const rangeLabel = computed<string>(() => {
    if (hasActiveFilter.value) {
        const count: string = filteredRows.value.length.toLocaleString('id-ID')
        const page: string = serverMeta.value.currentPage.toLocaleString('id-ID')
        return `${count} cocok filter di halaman ${page}`
    }
    const meta = serverMeta.value
    const total: string = meta.total.toLocaleString('id-ID')
    const from: string = (meta.from ?? (meta.total > 0 ? 1 : 0)).toLocaleString('id-ID')
    const to: string = (meta.to ?? meta.total).toLocaleString('id-ID')
    return `Menampilkan ${from}–${to} dari ${total} applicant`
})

function goToPage(pageNumber: number): void {
    const meta = serverMeta.value
    if (props.applications === null || isNavigating.value) return
    const target: number = Math.max(1, Math.min(pageNumber, meta.lastPage))
    if (target === meta.currentPage) return
    router.get(
        routes.admin.recruitment.periods.show(props.periodId),
        { tab: 'peserta', page: target > 1 ? target : undefined },
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
    processingId.value = target.id
    router.post(
        routes.admin.recruitment.applications.screening.pass(target.id),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                processingId.value = null
                passTarget.value = null
                passDialogOpen.value = false
            },
        },
    )
}

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
            onSuccess: () => closeReject(),
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
            <Button as-child variant="outline" size="sm">
                <Link
                    :href="routes.admin.broadcasts.create({ periodId: props.periodId })"
                    aria-label="Kirim broadcast ke pelamar periode ini"
                >
                    <Megaphone class="mr-2 size-4" aria-hidden="true" />
                    Kirim Broadcast
                </Link>
            </Button>
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
                                <th class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in filteredRows"
                                :key="row.id"
                                :ref="(el) => setRowRef(row.id, el)"
                                tabindex="0"
                                :aria-current="selectedId === row.id ? 'true' : undefined"
                                class="border-b last:border-0 cursor-pointer transition-colors hover:bg-muted/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring/40"
                                :class="selectedId === row.id ? 'bg-muted/40' : ''"
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
                            <tr v-if="filteredRows.length === 0">
                                <td colspan="7" class="text-muted-foreground px-4 py-10 text-center">
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
                    <template v-for="(item, index) in items" :key="`${item.type}-${index}`">
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
