<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import { Trash2 } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

interface Division {
    id: string
    code: string
    name: string
    description: string | null
    is_active: boolean
    sort_order: number
    interviewer_assignments_count: number
}

interface Assignment {
    id: string
    user_id: string
    user_name: string
    user_email: string
    division_id: string
    division_name: string
    division_code: string
}

interface Candidate {
    id: string
    name: string
    email: string
}

const props = defineProps<{
    divisions: Division[]
    assignments: Assignment[]
    interviewerCandidates: Candidate[]
}>()

const assignForm = useForm({
    user_id: '',
    recruitment_division_id: '',
})

onMounted(() => {
    setTopbar({ title: 'Divisi OpRec', subtitle: 'Kelola divisi & interviewer' })
})

function submitAssign() {
    assignForm.post(routes.admin.recruitment.interviewers.assign, {
        onSuccess: () => {
            assignForm.reset('user_id')
        },
    })
}

function unassign(id: string) {
    router.delete(routes.admin.recruitment.interviewers.unassign(id))
}

const editingId = ref<string | null>(null)
const editForms = ref<Record<string, { name: string; is_active: boolean }>>({})

function startEdit(division: Division) {
    editingId.value = division.id
    editForms.value[division.id] = {
        name: division.name,
        is_active: division.is_active,
    }
}

function saveDivision(division: Division) {
    const data = editForms.value[division.id]
    if (!data) return

    router.put(routes.admin.recruitment.divisions.update(division.id), data, {
        onSuccess: () => {
            editingId.value = null
        },
    })
}
</script>

<template>
    <Head title="Divisi OpRec" />

    <div class="flex flex-col gap-6">
        <PageHeader
            title="Divisi & Interviewer"
            subtitle="Empat divisi default OpRec dan penugasan interviewer."
            :back-href="routes.admin.recruitment.index"
        />

        <Card class="rounded-2xl border-border/70">
            <CardHeader>
                <CardTitle class="text-base">Divisi</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-for="division in divisions"
                    :key="division.id"
                    class="border-border/60 flex flex-wrap items-start justify-between gap-4 rounded-xl border p-4"
                >
                    <div class="min-w-0 flex-1 space-y-2">
                        <template v-if="editingId === division.id">
                            <Input v-model="editForms[division.id].name" />
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="editForms[division.id].is_active" type="checkbox" />
                                Aktif
                            </label>
                            <div class="flex gap-2">
                                <Button size="sm" @click="saveDivision(division)">Simpan</Button>
                                <Button size="sm" variant="ghost" @click="editingId = null">Batal</Button>
                            </div>
                        </template>
                        <template v-else>
                            <p class="font-medium">{{ division.name }}</p>
                            <p class="text-muted-foreground text-xs font-mono">{{ division.code }}</p>
                            <p class="text-muted-foreground text-sm">
                                {{ division.interviewer_assignments_count }} interviewer ·
                                {{ division.is_active ? 'Aktif' : 'Nonaktif' }}
                            </p>
                            <Button size="sm" variant="outline" @click="startEdit(division)">Edit</Button>
                        </template>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border-border/70">
            <CardHeader>
                <CardTitle class="text-base">Assign interviewer</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitAssign">
                    <div class="space-y-2">
                        <Label>Interviewer</Label>
                        <select
                            v-model="assignForm.user_id"
                            class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            required
                        >
                            <option value="">Pilih user</option>
                            <option v-for="u in interviewerCandidates" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.email }})
                            </option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label>Divisi</Label>
                        <select
                            v-model="assignForm.recruitment_division_id"
                            class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            required
                        >
                            <option value="">Pilih divisi</option>
                            <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <Button type="submit" size="sm" :disabled="assignForm.processing">Tugaskan</Button>
                    </div>
                </form>

                <ul class="divide-border divide-y rounded-lg border">
                    <li
                        v-for="row in assignments"
                        :key="row.id"
                        class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                    >
                        <div>
                            <p class="font-medium">{{ row.user_name }}</p>
                            <p class="text-muted-foreground text-xs">
                                {{ row.division_name }} · {{ row.user_email }}
                            </p>
                        </div>
                        <Button variant="ghost" size="icon" @click="unassign(row.id)">
                            <Trash2 class="size-4" />
                        </Button>
                    </li>
                    <li v-if="assignments.length === 0" class="text-muted-foreground px-4 py-6 text-center text-sm">
                        Belum ada interviewer yang ditugaskan.
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
