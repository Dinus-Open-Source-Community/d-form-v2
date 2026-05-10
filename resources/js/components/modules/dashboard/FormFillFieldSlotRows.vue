<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- ctx.answerForm is parent Inertia form state */
import { computed } from 'vue'
import { CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { DatePicker } from '@/components/ui/date-picker'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { UnwrapNestedRefs } from 'vue'
import { Star, ImagePlus, Upload, X } from 'lucide-vue-next'
import type { FormFillPageContext } from '@/utils/composables/useFormFillPage'

const props = withDefaults(
    defineProps<{
        ctx: UnwrapNestedRefs<FormFillPageContext>
        field: IFormField
        participationSlot: { slotIndex: number | null; title: string }
        storageKey: string
        /** 'linear' = legacy one field / card; 'bundleParticipant' = stacked in per-person card */
        variant: 'linear' | 'bundleParticipant'
        stackIndex?: number
        imageUploadFillReadyFn: (key: string) => boolean
    }>(),
    { stackIndex: 0 },
)

const emit = defineEmits<{
    openLightbox: [src: string | undefined, title: string]
}>()

function textAnswer(name: string): string {
    const v = props.ctx.answerForm[name]
    return typeof v === 'string' ? v : ''
}

function setTextAnswer(name: string, value: string | number): void {
    props.ctx.answerForm[name] = String(value)
}

const showSubtitle = computed(() => props.variant === 'linear' && Boolean(props.participationSlot.title))

const headerBorderClass = computed(() => {
    if (props.variant === 'bundleParticipant') {
        return props.stackIndex > 0 ? 'border-t border-border' : ''
    }
    return ''
})

/** Tighter vertical rhythm when multiple fields stack in one participant card */
const cardHeaderSpacingClass = computed(() => {
    if (props.variant === 'bundleParticipant') {
        return props.stackIndex > 0 ? 'gap-0.5 pt-2 pb-0.5' : 'gap-0.5 pt-2.5 pb-0.5'
    }
    return 'gap-1 pb-1.5 pt-3'
})

const cardContentSpacingClass = computed(() => {
    if (props.variant === 'bundleParticipant') {
        return 'pb-2 pt-0'
    }
    return 'pb-3 pt-0'
})

const fieldErrorSpacingClass = computed(() =>
    props.variant === 'bundleParticipant' ? 'mt-1' : 'mt-1.5',
)

const showDescription = computed(() => {
    if (!props.field.description) return false
    if (props.variant === 'bundleParticipant') return true
    return props.participationSlot.slotIndex === null
})

function fillReady(): boolean {
    return props.imageUploadFillReadyFn(props.storageKey)
}
</script>

<template>
    <CardHeader :class="[headerBorderClass, cardHeaderSpacingClass]">
        <CardTitle class="flex flex-col items-start gap-0.5 text-sm font-semibold tracking-[-0.005em] text-foreground">
            <span class="flex items-start gap-1">
                {{ field.label }}
                <span v-if="ctx.isRequired(field)" class="text-destructive">*</span>
            </span>
            <span v-if="showSubtitle" class="text-xs font-normal text-muted-foreground">{{ participationSlot.title }}</span>
        </CardTitle>
        <p
            v-if="showDescription"
            class="text-xs text-muted-foreground"
            :class="variant === 'bundleParticipant' ? 'mt-0 leading-snug' : 'leading-relaxed'"
        >
            {{ field.description }}
        </p>
    </CardHeader>
    <CardContent :class="cardContentSpacingClass">
        <Input
            v-if="
                ['short_text', 'email', 'phone', 'number', 'time', 'input'].includes(ctx.builderType(field)) &&
                !ctx.isMultipleSelect(field)
            "
            :id="storageKey"
            :type="ctx.getInputSubtype(field)"
            :placeholder="ctx.getPlaceholder(field)"
            :model-value="textAnswer(storageKey)"
            @update:model-value="setTextAnswer(storageKey, $event)"
        />

        <div v-else-if="ctx.builderType(field) === 'rating'" class="flex flex-col gap-2">
            <div class="flex gap-1.5">
                <button
                    v-for="rating in Number(ctx.metadata(field).maxStars ?? 5)"
                    :key="rating"
                    type="button"
                    class="rounded-lg p-1 transition-transform duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] active:scale-90"
                    @click="ctx.answerForm[storageKey] = String(rating)"
                >
                    <Star
                        class="size-7 transition-colors"
                        :class="
                            Number(ctx.answerForm[storageKey] || 0) >= rating
                                ? 'fill-amber-400 text-amber-400'
                                : 'text-muted-foreground/50'
                        "
                    />
                </button>
            </div>
            <p class="text-xs text-muted-foreground">Choose one rating.</p>
        </div>

        <Textarea
            v-else-if="ctx.builderType(field) === 'long_text' || ctx.builderType(field) === 'address' || field.type === 'textarea'"
            :id="storageKey"
            :placeholder="ctx.getPlaceholder(field)"
            :model-value="textAnswer(storageKey)"
            @update:model-value="setTextAnswer(storageKey, $event)"
            rows="4"
        />

        <DatePicker
            v-else-if="ctx.builderType(field) === 'date' || field.type === 'datePicker'"
            :id="storageKey"
            :model-value="textAnswer(storageKey)"
            class="min-h-11"
            @update:model-value="setTextAnswer(storageKey, $event)"
        />

        <div v-else-if="ctx.isCheckboxLike(field)" class="flex flex-col gap-1.5">
            <label
                v-for="row in ctx.getOptionRows(field)"
                :key="row.label"
                class="flex cursor-pointer items-center gap-3 rounded-xl border border-border bg-card px-3 py-2 text-sm font-medium text-foreground shadow-xs transition-[border-color,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:border-primary/30 hover:bg-muted/40"
            >
                <Checkbox
                    :id="`${storageKey}-${row.label}`"
                    :checked="((ctx.answerForm[storageKey] as string[]) ?? []).includes(row.label)"
                    @update:checked="
                        (value: boolean | 'indeterminate') =>
                            ctx.onCheckboxToggle(storageKey, row.label, value === true)
                    "
                />
                <div v-if="row.type === 'image' && row.imageSrc" class="size-16 shrink-0 overflow-hidden rounded-md border border-border">
                    <img :src="row.imageSrc" alt="" class="size-full object-cover" />
                </div>
                <span v-else>{{ row.label }}</span>
            </label>
        </div>

        <div v-else-if="ctx.isRadioLike(field)" class="flex flex-col gap-1.5">
            <label
                v-for="row in ctx.getOptionRows(field)"
                :key="row.label"
                class="flex cursor-pointer items-center gap-3 rounded-xl border border-border bg-card px-3 py-2 text-sm font-medium text-foreground shadow-xs transition-[border-color,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:border-primary/30 hover:bg-muted/40"
            >
                <input
                    type="radio"
                    :name="`${field.name}-${participationSlot.slotIndex ?? 'lead'}`"
                    :value="row.label"
                    :checked="(ctx.answerForm[storageKey] as string) === row.label"
                    class="size-4 accent-primary"
                    @change="() => (ctx.answerForm[storageKey] = row.label)"
                />
                <div v-if="row.type === 'image' && row.imageSrc" class="size-16 shrink-0 overflow-hidden rounded-md border border-border">
                    <img :src="row.imageSrc" alt="" class="size-full object-cover" />
                </div>
                <span v-else>{{ row.label }}</span>
            </label>
        </div>

        <Select v-else-if="ctx.builderType(field) === 'dropdown' || field.type === 'select'" v-model="ctx.answerForm[storageKey]">
            <SelectTrigger>
                <SelectValue placeholder="Select an option">
                    <template v-if="ctx.answerForm[storageKey]">
                        <span class="flex items-center gap-2">
                            <img
                                v-if="ctx.getSelectedOptionRow(field, storageKey)?.imageSrc"
                                :src="ctx.getSelectedOptionRow(field, storageKey)?.imageSrc"
                                alt=""
                                class="size-6 rounded border border-border object-cover"
                            />
                            {{ ctx.answerForm[storageKey] }}
                        </span>
                    </template>
                </SelectValue>
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="row in ctx.getOptionRows(field)" :key="row.label" :value="row.label">
                    <span class="flex items-center gap-2">
                        <img
                            v-if="row.imageSrc"
                            :src="row.imageSrc"
                            alt=""
                            class="size-7 rounded border border-border object-cover"
                        />
                        {{ row.label }}
                    </span>
                </SelectItem>
            </SelectContent>
        </Select>

        <div
            v-else-if="['file_upload', 'image_upload', 'fileUpload'].includes(ctx.builderType(field))"
            class="relative overflow-hidden rounded-xl border border-dashed border-border bg-muted/20 transition-colors hover:border-primary/30"
            :class="fillReady() ? 'p-0' : variant === 'bundleParticipant' ? 'p-4 text-center hover:bg-muted/30' : 'p-6 text-center hover:bg-muted/30'"
        >
            <div v-if="fillReady()" class="relative aspect-[4/3] w-full bg-muted/30">
                <button
                    type="button"
                    class="absolute inset-0 z-10 flex w-full focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                    @click="
                        emit(
                            'openLightbox',
                            ctx.filePreviewUrls[storageKey],
                            (ctx.answerForm[storageKey] as File).name,
                        )
                    "
                >
                    <img
                        :src="ctx.filePreviewUrls[storageKey]"
                        :alt="(ctx.answerForm[storageKey] as File).name"
                        class="h-full w-full object-cover"
                    />
                    <span class="sr-only">View full size</span>
                </button>
                <button
                    type="button"
                    class="absolute right-2 top-2 z-20 grid size-9 place-items-center rounded-full border border-border/80 bg-background/95 text-destructive shadow-md backdrop-blur-sm transition-transform hover:scale-105"
                    :aria-label="`Remove ${(ctx.answerForm[storageKey] as File).name}`"
                    @click.stop="ctx.clearFileUpload(storageKey)"
                >
                    <X class="size-4" />
                </button>
                <div
                    class="pointer-events-none absolute inset-x-0 bottom-0 z-20 flex flex-col items-center gap-1 bg-gradient-to-t from-black/60 via-black/25 to-transparent px-3 pb-3 pt-14 text-center"
                >
                    <label
                        class="pointer-events-auto cursor-pointer rounded-full border border-white/20 bg-background/95 px-3 py-1.5 text-xs font-medium text-foreground shadow-sm backdrop-blur-sm transition-colors hover:bg-background"
                    >
                        Choose another file
                        <input
                            type="file"
                            :accept="ctx.acceptValue(field)"
                            class="sr-only"
                            @change="ctx.onFileChange(storageKey, $event)"
                            @click.stop
                        />
                    </label>
                    <p class="text-[10px] font-medium text-white drop-shadow">Click image to view full size</p>
                </div>
            </div>

            <div v-else class="flex flex-col items-center gap-2">
                <div class="grid size-12 place-items-center rounded-full border border-border bg-card shadow-xs">
                    <component
                        :is="ctx.builderType(field) === 'image_upload' ? ImagePlus : Upload"
                        class="size-5 text-muted-foreground"
                    />
                </div>
                <div>
                    <p class="text-sm font-semibold text-foreground">Click to upload</p>
                    <p class="mt-0.5 text-[10px] text-muted-foreground">{{ ctx.fileHint(field) }}</p>
                </div>
                <input
                    type="file"
                    :accept="ctx.acceptValue(field)"
                    class="absolute inset-0 cursor-pointer opacity-0"
                    @change="ctx.onFileChange(storageKey, $event)"
                />
            </div>

            <div
                v-if="ctx.answerForm[storageKey] && !fillReady()"
                class="relative z-10 mt-4 flex items-center justify-center gap-2 rounded-xl border border-border bg-card p-3 shadow-xs"
            >
                <span class="max-w-[200px] truncate text-xs font-medium text-foreground">{{
                    (ctx.answerForm[storageKey] as File).name
                }}</span>
                <button
                    type="button"
                    class="text-destructive transition-transform hover:scale-110"
                    @click="ctx.clearFileUpload(storageKey)"
                >
                    <X class="size-4" />
                </button>
            </div>
        </div>

        <p v-if="ctx.fieldError(storageKey)" :class="[fieldErrorSpacingClass, 'text-xs font-medium text-destructive']">
            {{ ctx.fieldError(storageKey) }}
        </p>
    </CardContent>
</template>
