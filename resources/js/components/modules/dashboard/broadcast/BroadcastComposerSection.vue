<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import TipTapEditor from '@/components/modules/dashboard/events/TipTapEditor.vue'

const props = defineProps<{
    subject: string
    bodyHtml: string
    disabled: boolean
    subjectError?: string
    bodyError?: string
}>()

const emit = defineEmits<{
    'update:subject': [value: string]
    'update:bodyHtml': [value: string]
}>()

function onSubjectInput(value: string | number): void {
    emit('update:subject', String(value))
}
</script>

<template>
    <section aria-label="Komposer email" class="grid gap-4">
        <div class="space-y-2">
            <Label for="broadcast-subject">Subjek email <span aria-hidden="true" class="text-destructive">*</span></Label>
            <Input
                id="broadcast-subject"
                :model-value="props.subject"
                placeholder="Pengumuman hasil screening"
                required
                :disabled="props.disabled"
                :aria-invalid="!!props.subjectError"
                @update:model-value="onSubjectInput"
            />
            <p v-if="props.subjectError" class="text-destructive text-xs">{{ props.subjectError }}</p>
        </div>
        <div class="space-y-2">
            <span class="text-foreground text-sm font-medium">
                Isi email <span aria-hidden="true" class="text-destructive">*</span>
            </span>
            <TipTapEditor :model-value="props.bodyHtml" @update:model-value="emit('update:bodyHtml', $event)" />
            <p class="text-xs text-muted-foreground">Gunakan &#123;&#123;nama&#125;&#125; untuk personalisasi nama penerima.</p>
            <p v-if="props.bodyError" class="text-destructive text-xs">{{ props.bodyError }}</p>
        </div>
    </section>
</template>
