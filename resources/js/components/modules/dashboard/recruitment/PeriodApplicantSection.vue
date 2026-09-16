<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select'
import { Card, CardContent } from '@/components/ui/card'
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
    period: { id: string; name: string } | null
}

interface Paginator {
    data: ApplicationRow[]
    current_page: number
    last_page: number
    total: number
}

const QUEUE_OPTIONS = [
    { key: '', label: 'Semua antrean' },
    { key: 'screening', label: 'Screening' },
    { key: 'revision', label: 'Revisi' },
    { key: 'interview', label: 'Interview' },
    { key: 'final', label: 'Final' },
    { key: 'done', label: 'Selesai' },
] as const

const props = defineProps<{
    periodId: string
    applications: Paginator | null
    queueCounts: Record<string, number>
    divisionOptions: { id: string; name: string; code: string }[]
    stageOptions: { value: string; label: string }[]
    query: {
        search?: string
        division_id?: string
        stage?: string
        queue?: string
        semester?: string
    }
}>()

const search = ref(props.query.search ?? '')
const divisionId = ref(props.query.division_id ?? '')
const stage = ref(props.query.stage ?? '')
const queue = ref(props.query.queue ?? '')
const semester = ref(props.query.semester ?? '')

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

const queueModel = computed<string>({
    get: () => queue.value,
    set: (value: string) => {
        queue.value = value
        if (value) {
            stage.value = ''
        }
    },
})

/** Mencegah permintaan ganda saat state filter disamakan ulang dari URL/Inertia. */
let suppressFilterApply = false

function readQueryFromProps(): void {
    suppressFilterApply = true
    search.value = props.query.search ?? ''
    divisionId.value = props.query.division_id ?? ''
    stage.value = props.query.stage ?? ''
    queue.value = props.query.queue ?? ''
    semester.value = props.query.semester ?? ''
    void nextTick(() => {
        suppressFilterApply = false
    })
}

readQueryFromProps()

function applyFilters(page = 1) {
    if (suppressFilterApply) return
    router.get(
        routes.admin.recruitment.periods.show(props.periodId),
        {
            search: search.value || undefined,
            division_id: divisionId.value || undefined,
            stage: queue.value ? undefined : stage.value || undefined,
            queue: queue.value || undefined,
            semester: semester.value || undefined,
            page: page > 1 ? page : undefined,
        },
        { preserveState: true, replace: true },
    )
}

watch([search, divisionId, stage, semester, queue], () => applyFilters())
watch(() => props.query, readQueryFromProps, { deep: true })
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
            <Input v-model="semester" type="number" min="1" max="14" placeholder="Semester" class="w-28" />
        </div>

        <Card v-if="applications" class="rounded-2xl border-border/70 overflow-hidden">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 border-b text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nomor</th>
                                <th class="px-4 py-3 font-medium">Nama</th>
                                <th class="px-4 py-3 font-medium">NIM</th>
                                <th class="px-4 py-3 font-medium">Divisi</th>
                                <th class="px-4 py-3 font-medium">Tahap</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in applications.data"
                                :key="row.id"
                                class="border-b last:border-0 hover:bg-muted/20"
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
                                <td class="px-4 py-3 text-right">
                                    <Button as-child variant="ghost" size="sm">
                                        <Link :href="routes.admin.recruitment.applications.show(row.id)">
                                            Proses
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="applications.data.length === 0">
                                <td colspan="7" class="text-muted-foreground px-4 py-10 text-center">
                                    Belum ada applicant untuk periode ini yang cocok dengan filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div v-if="applications && applications.last_page > 1" class="flex items-center justify-between text-sm">
            <p class="text-muted-foreground">{{ applications.total }} applicant</p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="applications.current_page <= 1"
                    @click="applyFilters(applications.current_page - 1)"
                >
                    Sebelumnya
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="applications.current_page >= applications.last_page"
                    @click="applyFilters(applications.current_page + 1)"
                >
                    Berikutnya
                </Button>
            </div>
        </div>
    </section>
</template>
