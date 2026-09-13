<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'

const props = defineProps<{
    storeUrl: string
    compact?: boolean
}>()

const emit = defineEmits<{
    success: []
    cancel: []
}>()

const ratingFields = [
    { key: 'rating_registration_ease', label: 'Kemudahan pendaftaran' },
    { key: 'rating_info_clarity', label: 'Kejelasan informasi OpRec' },
    { key: 'rating_tracking_ease', label: 'Kemudahan portal tracking' },
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
    form.post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            emit('success')
        },
    })
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div
            v-for="field in ratingFields"
            :key="field.key"
            class="space-y-1.5"
            :class="compact ? 'sm:grid sm:grid-cols-[1fr_auto] sm:items-center sm:gap-3 sm:space-y-0' : 'space-y-2'"
        >
            <Label :for="field.key" class="text-sm">{{ field.label }}</Label>
            <select
                :id="field.key"
                v-model="form[field.key]"
                class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm sm:max-w-[120px]"
                required
            >
                <option value="" disabled>1–5</option>
                <option v-for="n in 5" :key="n" :value="String(n)">{{ n }}</option>
            </select>
            <p v-if="form.errors[field.key]" class="text-destructive text-xs sm:col-span-2">
                {{ form.errors[field.key] }}
            </p>
        </div>

        <div class="space-y-2">
            <Label for="feedback_text">Saran (opsional)</Label>
            <textarea
                id="feedback_text"
                v-model="form.feedback_text"
                rows="3"
                class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                placeholder="Ceritakan pengalamanmu..."
            />
        </div>

        <p v-if="form.errors.feedback" class="text-destructive text-sm">{{ form.errors.feedback }}</p>

        <div class="flex flex-wrap gap-2 pt-1">
            <Button type="submit" size="sm" :disabled="form.processing">
                {{ form.processing ? 'Mengirim...' : 'Kirim feedback' }}
            </Button>
            <Button v-if="$attrs.onCancel !== undefined" type="button" variant="ghost" size="sm" @click="emit('cancel')">
                Batal
            </Button>
        </div>
    </form>
</template>
