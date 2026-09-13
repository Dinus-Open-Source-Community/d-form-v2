<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

interface Period {
    id: string
    name: string
    description: string | null
    registration_opens_at: string | null
    registration_closes_at: string | null
    interview_starts_at: string | null
    interview_ends_at: string | null
    finalization_deadline_at: string | null
}

const props = defineProps<{ period: Period }>()

function toDatetimeLocal(value: string | null): string {
    if (!value) return ''
    const d = new Date(value)
    if (Number.isNaN(d.getTime())) return ''
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function toDateInput(value: string | null): string {
    if (!value) return ''
    return value.slice(0, 10)
}

const form = useForm({
    name: props.period.name,
    description: props.period.description ?? '',
    registration_opens_at: toDatetimeLocal(props.period.registration_opens_at),
    registration_closes_at: toDatetimeLocal(props.period.registration_closes_at),
    interview_starts_at: toDateInput(props.period.interview_starts_at),
    interview_ends_at: toDateInput(props.period.interview_ends_at),
    finalization_deadline_at: toDateInput(props.period.finalization_deadline_at),
})

onMounted(() => {
    setTopbar({ title: 'Edit periode', subtitle: props.period.name })
})

function submit() {
    form.put(routes.admin.recruitment.periods.update(props.period.id))
}
</script>

<template>
    <Head title="Edit periode OpRec" />

    <div class="mx-auto flex max-w-2xl flex-col gap-6">
        <PageHeader
            title="Edit periode"
            :subtitle="period.name"
            :back-href="routes.admin.recruitment.periods.show(period.id)"
        />

        <Card class="rounded-2xl border-border/70">
            <CardContent class="space-y-4 p-6">
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="name">Nama periode</Label>
                        <Input id="name" v-model="form.name" required />
                        <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="description">Deskripsi</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="registration_opens_at">Buka pendaftaran</Label>
                            <Input id="registration_opens_at" v-model="form.registration_opens_at" type="datetime-local" />
                        </div>
                        <div class="space-y-2">
                            <Label for="registration_closes_at">Tutup pendaftaran</Label>
                            <Input id="registration_closes_at" v-model="form.registration_closes_at" type="datetime-local" />
                        </div>
                        <div class="space-y-2">
                            <Label for="interview_starts_at">Mulai interview</Label>
                            <Input id="interview_starts_at" v-model="form.interview_starts_at" type="date" />
                        </div>
                        <div class="space-y-2">
                            <Label for="interview_ends_at">Akhir interview</Label>
                            <Input id="interview_ends_at" v-model="form.interview_ends_at" type="date" />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="finalization_deadline_at">Target finalisasi</Label>
                            <Input id="finalization_deadline_at" v-model="form.finalization_deadline_at" type="date" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="submit" :disabled="form.processing">Simpan perubahan</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
