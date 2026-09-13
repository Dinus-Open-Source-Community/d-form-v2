<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import { Plus } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

interface PeriodRow {
    id: string
    name: string
    slug: string
    status: string
    status_label: string
    registration_opens_at: string | null
    registration_closes_at: string | null
    applications_count: number
}

interface Paginator {
    data: PeriodRow[]
    current_page: number
    last_page: number
    total: number
}

const props = defineProps<{
    periods: Paginator
    query: { search?: string; status?: string }
    statusOptions: { value: string; label: string }[]
}>()

const search = ref(props.query.search ?? '')
const status = ref(props.query.status ?? '')

onMounted(() => {
    setTopbar({ title: 'Periode OpRec', subtitle: 'Kelola recruitment period' })
})

function applyFilters(page = 1) {
    router.get(
        routes.admin.recruitment.periods.index,
        {
            search: search.value || undefined,
            status: status.value || undefined,
            page: page > 1 ? page : undefined,
        },
        { preserveState: true, replace: true },
    )
}

watch([search, status], () => applyFilters())
</script>

<template>
    <Head title="Periode OpRec" />

    <div class="flex flex-col gap-6">
        <PageHeader
            title="Periode OpenRecruitment"
            subtitle="Buat dan kelola periode recruitment."
            :back-href="routes.admin.recruitment.index"
        >
            <template #actions>
                <Button as-child size="sm">
                    <Link :href="routes.admin.recruitment.periods.create">
                        <Plus class="mr-2 size-4" />
                        Periode baru
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-3">
            <Input v-model="search" placeholder="Cari nama periode..." class="max-w-xs" />
            <select
                v-model="status"
                class="border-input bg-background h-9 rounded-md border px-3 text-sm"
            >
                <option value="">Semua status</option>
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>
        </div>

        <Card class="rounded-2xl border-border/70 overflow-hidden">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 border-b text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nama</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Pendaftaran</th>
                                <th class="px-4 py-3 font-medium">Applicant</th>
                                <th class="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="period in periods.data"
                                :key="period.id"
                                class="border-b last:border-0 hover:bg-muted/20"
                            >
                                <td class="px-4 py-3 font-medium">{{ period.name }}</td>
                                <td class="px-4 py-3">{{ period.status_label }}</td>
                                <td class="text-muted-foreground px-4 py-3 text-xs">
                                    <span v-if="period.registration_opens_at">
                                        {{ period.registration_opens_at?.slice(0, 10) }}
                                        —
                                        {{ period.registration_closes_at?.slice(0, 10) ?? '…' }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3 tabular-nums">{{ period.applications_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Button as-child variant="ghost" size="sm">
                                        <Link :href="routes.admin.recruitment.periods.show(period.id)">
                                            Detail
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="periods.data.length === 0">
                                <td colspan="5" class="text-muted-foreground px-4 py-10 text-center">
                                    Belum ada periode recruitment.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div v-if="periods.last_page > 1" class="flex justify-center gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="periods.current_page <= 1"
                @click="applyFilters(periods.current_page - 1)"
            >
                Sebelumnya
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="periods.current_page >= periods.last_page"
                @click="applyFilters(periods.current_page + 1)"
            >
                Berikutnya
            </Button>
        </div>
    </div>
</template>
