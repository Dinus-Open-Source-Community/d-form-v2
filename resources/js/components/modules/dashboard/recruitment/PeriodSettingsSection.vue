<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent } from '@/components/ui/card'
import { DatePicker, SplitDateTimeField } from '@/components/ui/date-picker'
import PeriodBannerField from '@/components/modules/dashboard/recruitment/PeriodBannerField.vue'
import { routes } from '@/lib/routes'
import { showErrorToast } from '@/lib/error-message'
import { cn } from '@/lib/utils'
import { toast } from 'vue-sonner'

interface PeriodSettings {
    id: string
    name: string
    description: string | null
    registration_opens_at: string | null
    registration_closes_at: string | null
    interview_starts_at: string | null
    interview_ends_at: string | null
    finalization_deadline_at: string | null
    banner_url: string | null
    whatsapp_group_url?: string | null
}

const props = defineProps<{ period: PeriodSettings }>()

const emit = defineEmits<{ saved: [] }>()

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

const existingBannerUrl = computed<string | null>(() => props.period.banner_url ?? null)

const form = useForm({
    name: props.period.name,
    description: props.period.description ?? '',
    registration_opens_at: toDatetimeLocal(props.period.registration_opens_at),
    registration_closes_at: toDatetimeLocal(props.period.registration_closes_at),
    interview_starts_at: toDateInput(props.period.interview_starts_at),
    interview_ends_at: toDateInput(props.period.interview_ends_at),
    finalization_deadline_at: toDateInput(props.period.finalization_deadline_at),
    banner: null as File | null,
    whatsapp_group_url: props.period.whatsapp_group_url ?? '',
})

/** Validasi ringan: link WA opsional, bila diisi wajib https:// (cermin backend). */
const whatsappError = computed<string | null>(() => {
    const value = form.whatsapp_group_url.trim()
    if (!value) return null
    return value.startsWith('https://') ? null : 'Link grup WA harus diawali https://.'
})

const dateErrorClass =
    'border-destructive/70 bg-red-50 focus-visible:border-destructive focus-visible:ring-destructive/20 dark:bg-red-500/10'

function submit(): void {
    if (whatsappError.value) {
        showErrorToast(whatsappError.value, { title: 'Link grup WA tidak valid' })
        return
    }
    form.put(routes.admin.recruitment.periods.update(props.period.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            emit('saved')
            toast.success('Perubahan periode disimpan.')
        },
    })
}
</script>

<template>
    <Card class="rounded-2xl border-border/70">
        <CardContent class="space-y-5 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold">Pengaturan periode</h2>
                    <p class="text-muted-foreground mt-0.5 text-sm">
                        Nama, jadwal, banner, dan link grup WA periode ini.
                    </p>
                </div>
                <Button
                    type="submit"
                    form="period-settings-form"
                    :disabled="form.processing"
                    class="shrink-0"
                >
                    {{ form.processing ? 'Menyimpan…' : 'Simpan perubahan' }}
                </Button>
            </div>

            <form id="period-settings-form" class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="period-name">Nama periode</Label>
                    <Input id="period-name" v-model="form.name" required />
                    <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="period-description">Deskripsi</Label>
                    <textarea
                        id="period-description"
                        v-model="form.description"
                        rows="3"
                        class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="period-whatsapp">Link grup WA (opsional)</Label>
                    <Input
                        id="period-whatsapp"
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

                <PeriodBannerField
                    v-model="form.banner"
                    :banner-url="existingBannerUrl"
                    :error="form.errors.banner"
                    :disabled="form.processing"
                />

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
                        <Label for="period-interview-starts">Mulai interview</Label>
                        <DatePicker
                            id="period-interview-starts"
                            v-model="form.interview_starts_at"
                            :aria-invalid="!!form.errors.interview_starts_at"
                            :class="cn('bg-white', !!form.errors.interview_starts_at && dateErrorClass)"
                        />
                        <p v-if="form.errors.interview_starts_at" class="text-destructive text-xs">
                            {{ form.errors.interview_starts_at }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="period-interview-ends">Akhir interview</Label>
                        <DatePicker
                            id="period-interview-ends"
                            v-model="form.interview_ends_at"
                            :aria-invalid="!!form.errors.interview_ends_at"
                            :class="cn('bg-white', !!form.errors.interview_ends_at && dateErrorClass)"
                        />
                        <p v-if="form.errors.interview_ends_at" class="text-destructive text-xs">
                            {{ form.errors.interview_ends_at }}
                        </p>
                    </div>

                    <div class="space-y-2 sm:col-span-2">
                        <Label for="period-finalization">Target finalisasi</Label>
                        <DatePicker
                            id="period-finalization"
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
</template>
