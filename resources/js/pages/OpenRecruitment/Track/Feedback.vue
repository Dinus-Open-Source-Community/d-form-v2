<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import FormFillLayout from '@/layouts/FormFillLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { routes } from '@/lib/routes'

defineOptions({ layout: FormFillLayout })

const props = defineProps<{
    application: { full_name: string; registration_number: string }
    storeUrl: string
    dashboardUrl: string
}>()

const ratingFields = [
    { key: 'rating_registration_ease', label: 'Kemudahan pendaftaran' },
    { key: 'rating_info_clarity', label: 'Kejelasan informasi OpRec' },
    { key: 'rating_tracking_ease', label: 'Kemudahan tracking portal' },
    { key: 'rating_interview_experience', label: 'Pengalaman interview' },
    { key: 'rating_staff_service', label: 'Pelayanan panitia' },
] as const

const form = useForm({
    rating_registration_ease: '',
    rating_info_clarity: '',
    rating_tracking_ease: '',
    rating_interview_experience: '',
    rating_staff_service: '',
    feedback_text: '',
})

function submit() {
    form.post(props.storeUrl)
}
</script>

<template>
    <Head title="Feedback OpRec" />

    <div class="mx-auto max-w-2xl space-y-6 px-4 py-8">
        <div class="text-center">
            <h1 class="text-2xl font-semibold">Feedback OpenRecruitment</h1>
            <p class="text-muted-foreground mt-1 text-sm">
                {{ application.full_name }} · {{ application.registration_number }}
            </p>
        </div>

        <Card class="rounded-2xl border-border/70">
            <CardHeader>
                <CardTitle class="text-base">Bagaimana pengalamanmu?</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-5" @submit.prevent="submit">
                    <div v-for="field in ratingFields" :key="field.key" class="space-y-2">
                        <Label :for="field.key">{{ field.label }}</Label>
                        <select
                            :id="field.key"
                            v-model="form[field.key]"
                            class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            required
                        >
                            <option value="" disabled>Pilih 1–5</option>
                            <option v-for="n in 5" :key="n" :value="String(n)">{{ n }}</option>
                        </select>
                        <p v-if="form.errors[field.key]" class="text-destructive text-xs">
                            {{ form.errors[field.key] }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="feedback_text">Saran tambahan (opsional)</Label>
                        <textarea
                            id="feedback_text"
                            v-model="form.feedback_text"
                            rows="4"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Ceritakan pengalamanmu..."
                        />
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2">
                        <Button type="submit" :disabled="form.processing">Kirim feedback</Button>
                        <Button as-child variant="outline">
                            <Link :href="dashboardUrl">Batal</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
