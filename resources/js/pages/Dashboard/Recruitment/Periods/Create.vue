<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent } from '@/components/ui/card'
import { DatePicker, SplitDateTimeField } from '@/components/ui/date-picker'
import { routes } from '@/lib/routes'
import { cn } from '@/lib/utils'
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

const dateErrorClass =
    'border-destructive/70 bg-red-50 focus-visible:border-destructive focus-visible:ring-destructive/20 dark:bg-red-500/10'

onMounted(() => {
    setTopbar({ title: 'Periode baru', subtitle: 'Open Recruitment' })
})

function submit() {
    form.post(routes.admin.recruitment.periods.store)
}
</script>

<template>
    <Head title="Periode baru" />

    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="font-display text-foreground text-2xl font-semibold tracking-tight sm:text-3xl">
                    Periode baru
                </h1>
                <p class="text-muted-foreground mt-1.5 text-base">Open Recruitment</p>
            </div>
            <Button
                type="submit"
                form="period-form"
                :disabled="form.processing"
                class="w-full shrink-0 sm:w-auto"
            >
                Simpan
            </Button>
        </div>

        <Card class="rounded-2xl border-border/70">
            <CardContent class="p-6">
                <form id="period-form" class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="name">Nama periode</Label>
                        <Input id="name" v-model="form.name" placeholder="Open Recruitment 2026" required />
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
                        <SplitDateTimeField
                            id-prefix="reg_open"
                            v-model="form.registration_opens_at"
                            label="Buka pendaftaran"
                            picker-class="bg-white"
                            class="sm:col-span-2"
                            :error="form.errors.registration_opens_at"
                            :invalid="!!form.errors.registration_opens_at"
                        />
                        <SplitDateTimeField
                            id-prefix="reg_close"
                            v-model="form.registration_closes_at"
                            label="Tutup pendaftaran"
                            picker-class="bg-white"
                            class="sm:col-span-2"
                            :error="form.errors.registration_closes_at"
                            :invalid="!!form.errors.registration_closes_at"
                        />

                        <div class="space-y-2">
                            <Label for="interview_starts_at">Mulai interview</Label>
                            <DatePicker
                                id="interview_starts_at"
                                v-model="form.interview_starts_at"
                                :aria-invalid="!!form.errors.interview_starts_at"
                                :class="cn('bg-white', !!form.errors.interview_starts_at && dateErrorClass)"
                            />
                            <p v-if="form.errors.interview_starts_at" class="text-destructive text-xs">
                                {{ form.errors.interview_starts_at }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="interview_ends_at">Akhir interview</Label>
                            <DatePicker
                                id="interview_ends_at"
                                v-model="form.interview_ends_at"
                                :aria-invalid="!!form.errors.interview_ends_at"
                                :class="cn('bg-white', !!form.errors.interview_ends_at && dateErrorClass)"
                            />
                            <p v-if="form.errors.interview_ends_at" class="text-destructive text-xs">
                                {{ form.errors.interview_ends_at }}
                            </p>
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="finalization_deadline_at">Target finalisasi</Label>
                            <DatePicker
                                id="finalization_deadline_at"
                                v-model="form.finalization_deadline_at"
                                :aria-invalid="!!form.errors.finalization_deadline_at"
                                :class="cn('bg-white', !!form.errors.finalization_deadline_at && dateErrorClass)"
                            />
                            <p v-if="form.errors.finalization_deadline_at" class="text-destructive text-xs">
                                {{ form.errors.finalization_deadline_at }}
                            </p>
                        </div>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
