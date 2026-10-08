<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent } from '@/components/ui/card'
import { DatePicker, SplitDateTimeField } from '@/components/ui/date-picker'
import PeriodBannerField from '@/components/modules/dashboard/recruitment/PeriodBannerField.vue'
import { routes } from '@/lib/routes'
import { cn } from '@/lib/utils'
import { handleInertiaFormErrors, showErrorToast } from '@/lib/error-message'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

/** Label field agar pesan validasi di toast mudah dicocokkan dengan form. */
const errorFieldLabels: Record<string, string> = {
    name: 'Nama periode',
    description: 'Deskripsi',
    registration_opens_at: 'Buka pendaftaran',
    registration_closes_at: 'Tutup pendaftaran',
    interview_starts_at: 'Mulai interview',
    interview_ends_at: 'Akhir interview',
    finalization_deadline_at: 'Target finalisasi',
    banner: 'Banner',
    whatsapp_group_url: 'Link grup WA',
    whatsapp_group_aa_url: 'Link grup WA AA',
    whatsapp_group_member_url: 'Link grup WA Member',
}

const form = useForm({
    name: '',
    description: '',
    registration_opens_at: '',
    registration_closes_at: '',
    interview_starts_at: '',
    interview_ends_at: '',
    finalization_deadline_at: '',
    banner: null as File | null,
    whatsapp_group_url: '',
    whatsapp_group_aa_url: '',
    whatsapp_group_member_url: '',
})

/** Validasi ringan: link WA opsional, bila diisi wajib https:// (cermin backend). */
const whatsappError = computed<string | null>(() => {
    const value = form.whatsapp_group_url.trim()
    if (!value) return null
    return value.startsWith('https://') ? null : 'Link grup WA harus diawali https://.'
})

/** Validasi ringan: link grup WA AA opsional, bila diisi wajib https://. */
const whatsappAaError = computed<string | null>(() => {
    const value = form.whatsapp_group_aa_url.trim()
    if (!value) return null
    return value.startsWith('https://') ? null : 'Link grup WA AA harus diawali https://.'
})

/** Validasi ringan: link grup WA Member opsional, bila diisi wajib https://. */
const whatsappMemberError = computed<string | null>(() => {
    const value = form.whatsapp_group_member_url.trim()
    if (!value) return null
    return value.startsWith('https://') ? null : 'Link grup WA Member harus diawali https://.'
})

const dateErrorClass =
    'border-destructive/70 bg-red-50 focus-visible:border-destructive focus-visible:ring-destructive/20 dark:bg-red-500/10'

onMounted(() => {
    setTopbar({ title: 'Periode baru', subtitle: 'Open Recruitment' })
})

function submit() {
    if (whatsappError.value) {
        showErrorToast(whatsappError.value, { title: 'Link grup WA tidak valid' })
        return
    }
    if (whatsappAaError.value) {
        showErrorToast(whatsappAaError.value, { title: 'Link grup WA AA tidak valid' })
        return
    }
    if (whatsappMemberError.value) {
        showErrorToast(whatsappMemberError.value, { title: 'Link grup WA Member tidak valid' })
        return
    }
    form.post(routes.admin.recruitment.periods.store, {
        forceFormData: true,
        onError: (errors) => {
            handleInertiaFormErrors(errors, {
                title: 'Gagal menyimpan periode',
                fieldLabels: errorFieldLabels,
            })
        },
        onSuccess: (page) => {
            // Server selalu mengalihkan ke halaman periode saat penyimpanan berhasil.
            // Kalau masih di halaman ini tanpa error, permintaan dibatalkan tanpa
            // pesan (mis. sesi/CSRF kedaluwarsa sehingga backend membalas 302 tanpa pesan).
            if (page.component === 'Dashboard/Recruitment/Periods/Create') {
                showErrorToast(
                    'Periode gagal disimpan. Sesi Anda mungkin sudah berakhir — muat ulang halaman lalu coba lagi.',
                )
            }
        },
    })
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

                    <div class="space-y-2">
                        <Label for="whatsapp_group_url">Link grup WA (opsional)</Label>
                        <Input
                            id="whatsapp_group_url"
                            v-model="form.whatsapp_group_url"
                            type="url"
                            inputmode="url"
                            placeholder="https://chat.whatsapp.com/…"
                            :aria-invalid="!!form.errors.whatsapp_group_url || !!whatsappError"
                        />
                        <p class="text-muted-foreground text-xs">
                            Format undangan https:// (mis. chat.whatsapp.com/…). Tampil di email
                            kelulusan bila diisi.
                        </p>
                        <p v-if="whatsappError" class="text-destructive text-xs">{{ whatsappError }}</p>
                        <p v-if="form.errors.whatsapp_group_url" class="text-destructive text-xs">
                            {{ form.errors.whatsapp_group_url }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="whatsapp_group_aa_url">Link grup WA AA (opsional)</Label>
                        <Input
                            id="whatsapp_group_aa_url"
                            v-model="form.whatsapp_group_aa_url"
                            type="url"
                            inputmode="url"
                            placeholder="https://chat.whatsapp.com/…"
                            :aria-invalid="!!form.errors.whatsapp_group_aa_url || !!whatsappAaError"
                        />
                        <p class="text-muted-foreground text-xs">
                            Format undangan https:// (mis. chat.whatsapp.com/…). Tampil di email
                            penerimaan AA bila diisi.
                        </p>
                        <p v-if="whatsappAaError" class="text-destructive text-xs">{{ whatsappAaError }}</p>
                        <p v-if="form.errors.whatsapp_group_aa_url" class="text-destructive text-xs">
                            {{ form.errors.whatsapp_group_aa_url }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="whatsapp_group_member_url">Link grup WA Member (opsional)</Label>
                        <Input
                            id="whatsapp_group_member_url"
                            v-model="form.whatsapp_group_member_url"
                            type="url"
                            inputmode="url"
                            placeholder="https://chat.whatsapp.com/…"
                            :aria-invalid="!!form.errors.whatsapp_group_member_url || !!whatsappMemberError"
                        />
                        <p class="text-muted-foreground text-xs">
                            Format undangan https:// (mis. chat.whatsapp.com/…). Tampil di email
                            penerimaan Member bila diisi.
                        </p>
                        <p v-if="whatsappMemberError" class="text-destructive text-xs">{{ whatsappMemberError }}</p>
                        <p v-if="form.errors.whatsapp_group_member_url" class="text-destructive text-xs">
                            {{ form.errors.whatsapp_group_member_url }}
                        </p>
                    </div>

                    <PeriodBannerField
                        v-model="form.banner"
                        :error="form.errors.banner"
                        :disabled="form.processing"
                    />

                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-4">
                            <div class="flex flex-col justify-start">
                                <SplitDateTimeField
                                    id-prefix="reg_open"
                                    v-model="form.registration_opens_at"
                                    label="Buka pendaftaran"
                                    picker-class="bg-white"
                                    :error="form.errors.registration_opens_at"
                                    :invalid="!!form.errors.registration_opens_at"
                                />
                            </div>
                            <div class="flex flex-col justify-start">
                                <SplitDateTimeField
                                    id-prefix="reg_close"
                                    v-model="form.registration_closes_at"
                                    label="Tutup pendaftaran"
                                    picker-class="bg-white"
                                    :error="form.errors.registration_closes_at"
                                    :invalid="!!form.errors.registration_closes_at"
                                />
                            </div>
                        </div>

                        <div class="flex flex-col gap-4">
                            <div class="flex flex-col justify-start gap-2">
                                <Label for="interview_starts_at" class="leading-5">Mulai interview</Label>
                                <DatePicker
                                    id="interview_starts_at"
                                    v-model="form.interview_starts_at"
                                    :aria-invalid="!!form.errors.interview_starts_at"
                                    :class="cn('h-9 bg-white', !!form.errors.interview_starts_at && dateErrorClass)"
                                />
                                <p v-if="form.errors.interview_starts_at" class="text-destructive text-xs">
                                    {{ form.errors.interview_starts_at }}
                                </p>
                            </div>

                            <div class="flex flex-col justify-start gap-2">
                                <Label for="interview_ends_at" class="leading-5">Akhir interview</Label>
                                <DatePicker
                                    id="interview_ends_at"
                                    v-model="form.interview_ends_at"
                                    :aria-invalid="!!form.errors.interview_ends_at"
                                    :class="cn('h-9 bg-white', !!form.errors.interview_ends_at && dateErrorClass)"
                                />
                                <p v-if="form.errors.interview_ends_at" class="text-destructive text-xs">
                                    {{ form.errors.interview_ends_at }}
                                </p>
                            </div>
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
