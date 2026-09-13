<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import { Plus } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

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

interface Paginator {
    data: SessionRow[]
    current_page: number
    last_page: number
    total: number
}

const props = defineProps<{
    sessions: Paginator
    query: { period_id?: string; division_id?: string; is_active?: string }
    periodOptions: { id: string; name: string }[]
    divisionOptions: { id: string; name: string; code: string }[]
}>()

const periodId = ref(props.query.period_id ?? '')
const divisionId = ref(props.query.division_id ?? '')
const createOpen = ref(false)

const createForm = useForm({
    recruitment_period_id: props.periodOptions[0]?.id ?? '',
    recruitment_division_id: props.divisionOptions[0]?.id ?? '',
    session_date: '',
    starts_at: '09:00',
    ends_at: '12:00',
    location: '',
    room: '',
    notes: '',
})

onMounted(() => {
    setTopbar({ title: 'Sesi Interview', subtitle: 'Kelola jadwal interview OpRec' })
})

function applyFilters(page = 1) {
    router.get(
        routes.admin.recruitment.interviewSessions.index,
        {
            period_id: periodId.value || undefined,
            division_id: divisionId.value || undefined,
            page: page > 1 ? page : undefined,
        },
        { preserveState: true, replace: true },
    )
}

watch([periodId, divisionId], () => applyFilters())

function submitCreate() {
    createForm.post(routes.admin.recruitment.interviewSessions.store, {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false
            createForm.reset('location', 'room', 'notes', 'session_date')
        },
    })
}
</script>

<template>
    <Head title="Sesi Interview OpRec" />

    <div class="flex flex-col gap-6">
        <PageHeader
            title="Sesi Interview"
            subtitle="Buat sesi per divisi dan jadwalkan applicant yang lolos screening."
            :back-href="routes.admin.recruitment.index"
        >
            <template #actions>
                <Button size="sm" @click="createOpen = true">
                    <Plus class="mr-2 size-4" />
                    Sesi baru
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-3">
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
        </div>

        <Card class="rounded-2xl border-border/70 overflow-hidden">
            <CardContent class="p-0">
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
                                v-for="row in sessions.data"
                                :key="row.id"
                                class="border-b last:border-0 hover:bg-muted/20"
                            >
                                <td class="px-4 py-3">{{ row.session_date }}</td>
                                <td class="px-4 py-3">{{ row.starts_at }}–{{ row.ends_at }}</td>
                                <td class="px-4 py-3">{{ row.division?.name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ row.location }} · {{ row.room }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ row.interviews_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Button as-child variant="ghost" size="sm">
                                        <Link :href="routes.admin.recruitment.interviewSessions.show(row.id)">
                                            Detail
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="sessions.data.length === 0">
                                <td colspan="6" class="text-muted-foreground px-4 py-10 text-center">
                                    Belum ada sesi interview.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="createOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Buat sesi interview</DialogTitle>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitCreate">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2 sm:col-span-2">
                        <Label>Periode</Label>
                        <select
                            v-model="createForm.recruitment_period_id"
                            class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            required
                        >
                            <option v-for="period in periodOptions" :key="period.id" :value="period.id">
                                {{ period.name }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label>Divisi</Label>
                        <select
                            v-model="createForm.recruitment_division_id"
                            class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            required
                        >
                            <option v-for="division in divisionOptions" :key="division.id" :value="division.id">
                                {{ division.name }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label>Tanggal</Label>
                        <Input v-model="createForm.session_date" type="date" required />
                    </div>
                    <div class="space-y-2">
                        <Label>Ruang</Label>
                        <Input v-model="createForm.room" required />
                    </div>
                    <div class="space-y-2">
                        <Label>Mulai</Label>
                        <Input v-model="createForm.starts_at" type="time" required />
                    </div>
                    <div class="space-y-2">
                        <Label>Selesai</Label>
                        <Input v-model="createForm.ends_at" type="time" required />
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label>Lokasi</Label>
                        <Input v-model="createForm.location" required />
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label>Catatan</Label>
                        <Input v-model="createForm.notes" />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="createOpen = false">Batal</Button>
                    <Button type="submit" :disabled="createForm.processing">Simpan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
