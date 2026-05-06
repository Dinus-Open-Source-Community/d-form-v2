<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Check } from 'lucide-vue-next'

const formTitle = defineModel<string>('formTitle', { required: true })
const formDescription = defineModel<string>('formDescription', { required: true })
const closedAt = defineModel<string>('closedAt', { required: true })
const visibleFor = defineModel<string[]>('visibleFor', { required: true })

defineProps<{
    idPrefix: string
    fieldErrors: Partial<Record<'title' | 'description' | 'closed_at' | 'visible_for', string>>
    visibilityOptions: readonly { value: string; label: string }[]
}>()

defineEmits<{
    toggleVisibility: [value: string, checked: boolean]
}>()
</script>

<template>
    <section class="border-border bg-card space-y-4 rounded-2xl border p-5 shadow-xs">
        <h3 class="font-display text-foreground text-sm font-semibold tracking-[-0.01em]">Form details</h3>
        <div class="space-y-1.5">
            <Label :for="`${idPrefix}-title`" class="text-xs font-semibold"
                >Title <span class="text-destructive">*</span></Label
            >
            <Input
                :id="`${idPrefix}-title`"
                v-model="formTitle"
                placeholder="e.g. Speaker Registration"
            />
            <p v-if="fieldErrors.title" class="text-destructive text-[10px]">{{ fieldErrors.title }}</p>
        </div>
        <div class="space-y-1.5">
            <Label :for="`${idPrefix}-desc`" class="text-xs font-semibold"
                >Description <span class="text-destructive">*</span></Label
            >
            <Textarea
                :id="`${idPrefix}-desc`"
                v-model="formDescription"
                rows="3"
                placeholder="Tell registrants what this form is for..."
            />
            <p v-if="fieldErrors.description" class="text-destructive text-[10px]">{{ fieldErrors.description }}</p>
        </div>
        <div class="space-y-1.5">
            <Label :for="`${idPrefix}-closed`" class="text-xs font-semibold"
                >Closes at <span class="text-destructive">*</span></Label
            >
            <Input :id="`${idPrefix}-closed`" v-model="closedAt" type="datetime-local" />
            <p v-if="fieldErrors.closed_at" class="text-destructive text-[10px]">{{ fieldErrors.closed_at }}</p>
        </div>
        <div class="space-y-2">
            <Label class="text-xs font-semibold">Visibility <span class="text-destructive">*</span></Label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="opt in visibilityOptions"
                    :key="opt.value"
                    type="button"
                    @click="$emit('toggleVisibility', opt.value, !visibleFor.includes(opt.value))"
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition-[border-color,background-color,color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        visibleFor.includes(opt.value)
                            ? 'border-primary/30 bg-primary/10 text-primary'
                            : 'border-border bg-card text-muted-foreground hover:border-primary/30 hover:text-foreground',
                    ]"
                >
                    <Check v-if="visibleFor.includes(opt.value)" class="size-3" />
                    {{ opt.label }}
                </button>
            </div>
            <p v-if="fieldErrors.visible_for" class="text-destructive text-[10px]">{{ fieldErrors.visible_for }}</p>
        </div>
    </section>
</template>
