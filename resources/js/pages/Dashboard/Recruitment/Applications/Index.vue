<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

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

const QUEUE_TABS = [
    { key: '', label: 'Semua' },
    { key: 'screening', label: 'Screening' },
    { key: 'revision', label: 'Revisi' },
    { key: 'interview', label: 'Interview' },
    { key: 'final', label: 'Final' },
    { key: 'done', label: 'Selesai' },
] as const

const props = defineProps<{
    applications: Paginator
    query: {
        search?: string
        period_id?: string
        division_id?: string
        stage?: string
        queue?: string
        semester?: string
    }
    queue_counts: Record<string, number>
    periodOptions: { id: string; name: string }[]
    divisionOptions: { id: string; name: string; code: string }[]
    stageOptions: { value: string; label: string }[]
}>()

const search = ref(props.query.search ?? '')
const periodId = ref(props.query.period_id ?? '')
const divisionId = ref(props.query.division_id ?? '')
const stage = ref(props.query.stage ?? '')
const queue = ref(props.query.queue ?? '')
const semester = ref(props.query.semester ?? '')

const activeQueueLabel = computed(
    () => QUEUE_TABS.find((tab) => tab.key === queue.value)?.label ?? 'Semua',
)

function queueBadgeCount(key: string): number | null {
    if (key === '') {
        return props.queue_counts.all ?? null
    }
    const count = props.queue_counts[key]
    return count !== undefined ? count : null
}

onMounted(() => {
    setTopbar({ title: 'Applicant OpRec', subtitle: 'Kelola pendaftaran & screening' })
})

function applyFilters(page = 1) {
    router.get(
        routes.admin.recruitment.applications.index,
        {
            search: search.value || undefined,
            period_id: periodId.value || undefined,
            division_id: divisionId.value || undefined,
            stage: queue.value ? undefined : stage.value || undefined,
            queue: queue.value || undefined,
            semester: semester.value || undefined,
            page: page > 1 ? page : undefined,
        },
        { preserveState: true, replace: true },
    )
}

function selectQueue(key: string) {
    queue.value = key
    if (key) {
        stage.value = ''
    }
    applyFilters()
}

watch([search, periodId, divisionId, stage, semester], () => applyFilters())
</script>

<template>
    <Head title="Applicant OpRec" />

    <div class="flex flex-col gap-6">
        <PageHeader
            :title="`Applicant · ${activeQueueLabel}`"
            subtitle="Antrean kerja berdasarkan tahap — klik tab untuk fokus."
            :back-href="routes.admin.recruitment.index"
        />

        <div class="flex flex-wrap gap-2">
            <button
                v-for="tab in QUEUE_TABS"
                :key="tab.key || 'all'"
                type="button"
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-colors"
                :class="
                    queue === tab.key
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-background hover:bg-muted/50'
                "
                @click="selectQueue(tab.key)"
            >
                {{ tab.label }}
                <Badge
                    v-if="queueBadgeCount(tab.key) !== null && queueBadgeCount(tab.key)! > 0"
                    variant="secondary"
                    class="tabular-nums"
                    :class="queue === tab.key ? 'bg-primary-foreground/20 text-primary-foreground' : ''"
                >
                    {{ queueBadgeCount(tab.key) }}
                </Badge>
            </button>
        </div>

        <div class="flex flex-wrap gap-3">
            <Input v-model="search" placeholder="Cari nama, NIM, nomor pendaftaran..." class="max-w-xs" />
            <select
                v-model="periodId"
                class="border-input bg-background h-9 rounded-md border px-3 text-sm"
            >
                <option value="">Semua periode</option>
                <option v-for="period in periodOptions" :key="period.id" :value="period.id">
                    {{ period.name }}
                </option>
            </select>
            <select
                v-model="divisionId"
                class="border-input bg-background h-9 rounded-md border px-3 text-sm"
            >
                <option value="">Semua divisi</option>
                <option v-for="division in divisionOptions" :key="division.id" :value="division.id">
                    {{ division.name }}
                </option>
            </select>
            <select
                v-if="!queue"
                v-model="stage"
                class="border-input bg-background h-9 rounded-md border px-3 text-sm"
            >
                <option value="">Semua tahap</option>
                <option v-for="opt in stageOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>
            <Input v-model="semester" type="number" min="1" max="14" placeholder="Semester" class="w-28" />
        </div>

        <Card class="rounded-2xl border-border/70 overflow-hidden">
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
                                    Belum ada applicant yang cocok dengan filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div v-if="applications.last_page > 1" class="flex items-center justify-between text-sm">
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
    </div>
</template>
