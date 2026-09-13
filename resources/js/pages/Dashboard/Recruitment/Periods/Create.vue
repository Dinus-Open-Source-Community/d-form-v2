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

const form = useForm({
    name: '',
    description: '',
    registration_opens_at: '',
    registration_closes_at: '',
    interview_starts_at: '',
    interview_ends_at: '',
    finalization_deadline_at: '',
})

onMounted(() => {
    setTopbar({ title: 'Periode baru', subtitle: 'OpRec' })
})

function submit() {
    form.post(routes.admin.recruitment.periods.store)
}
</script>

<template>
    <Head title="Periode baru" />

    <div class="mx-auto flex max-w-2xl flex-col gap-6">
        <PageHeader
            title="Buat periode OpRec"
            subtitle="Periode baru dimulai dengan status Draft."
            :back-href="routes.admin.recruitment.periods.index"
        />

        <Card class="rounded-2xl border-border/70">
            <CardContent class="space-y-4 p-6">
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="name">Nama periode</Label>
                        <Input id="name" v-model="form.name" placeholder="OpRec 2026" required />
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
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
