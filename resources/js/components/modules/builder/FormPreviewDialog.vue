<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { X, Star, Upload, ImagePlus, Send, Sparkles } from 'lucide-vue-next'
import { optionLabel, optionImageUrl, type FieldOptionEntry } from '@/components/modules/builder/fieldMapping'
import { normalizeBannerSrc } from '@/components/modules/builder/formBanner'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

/** Mirrors canvas builder field shape used by Show/Create — loose typing for metadata without `any`. */
export interface FormPreviewField {
    id: string
    type: string
    label: string
    description?: string
    required?: boolean
    placeholder?: string
    options?: FieldOptionEntry[]
    metadata?: Record<string, unknown>
}

const props = defineProps<{
    open: boolean
    title?: string
    description?: string
    formBannerUrl?: string
    formBannerCaption?: string
    fields?: FormPreviewField[]
}>()

const emit = defineEmits<{ close: [] }>()

const fieldsSafe = computed(() => props.fields ?? [])

const bannerResolved = computed(() => {
    const u = (props.formBannerUrl || '').trim()
    if (!u) return ''
    if (u.startsWith('data:')) return u
    if (/^https?:\/\//i.test(u)) return u
    if (u.startsWith('/')) return u
    return `/storage/${u.replace(/^\/+/, '')}`
})

const bannerCaptionTrim = computed(() => (props.formBannerCaption || '').trim())

const titleText = computed(() => props.title || 'Untitled form')

const hasFormDescription = computed(() => Boolean((props.description || '').trim()))

// Local non-persisted state so stars / radii feel interactive (preview only)
const ratingSelection = ref<Record<string, number>>({})

watch(
    () => props.open,
    (open) => {
        if (!open) return
        const next: Record<string, number> = { ...ratingSelection.value }
        for (const f of fieldsSafe.value) {
            if (f.type === 'rating' && next[f.id] === undefined) {
                next[f.id] = 0
            }
        }
        ratingSelection.value = next
    },
)

function metaString(field: FormPreviewField, key: string): string {
    const v = field.metadata?.[key]
    return typeof v === 'string' ? v : ''
}

function metaNumber(field: FormPreviewField, key: string, fallback: number): number {
    const v = field.metadata?.[key]
    return typeof v === 'number' && !Number.isNaN(v) ? v : fallback
}

function optionEntries(field: FormPreviewField): FieldOptionEntry[] {
    const raw = field.options
    return Array.isArray(raw) ? raw : []
}

function choiceThumb(url: string): string {
    return normalizeBannerSrc(url)
}

function optKey(opt: FieldOptionEntry, i: number): string {
    return `${optionLabel(opt)}_${i}`
}

function ratingStars(field: FormPreviewField): number[] {
    const n = Math.min(Math.max(metaNumber(field, 'maxStars', 5), 1), 10)
    return Array.from({ length: n }, (_, i) => i + 1)
}
</script>

<template>
    <Teleport to="body">
        <Transition name="preview-fade">
            <div
                v-if="open"
                class="fixed inset-0 z-[110] flex items-center justify-center p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-label="Form respondent preview"
            >
                <div
                    class="absolute inset-0 bg-[var(--brutal-ink)]/35 backdrop-blur-[2px]"
                    @click="emit('close')"
                />

                <div
                    class="relative z-10 flex max-h-[min(92vh,880px)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border-[1.5px] border-[var(--brutal-ink)] bg-[var(--card)] shadow-[var(--shadow-md)]"
                >
                    <!-- Chrome: matches dashboard “tool” panels -->
                    <div
                        class="flex shrink-0 items-start justify-between gap-3 border-b border-[var(--brutal-ink)]/12 bg-gradient-to-r from-[var(--brutal-cream)]/80 via-white to-primary/[0.04] px-4 py-3 sm:px-5"
                    >
                        <div class="flex w-full h-full items-start ">
                            <p class=" text-base font-extrabold uppercase tracking-wider text-[var(--brutal-ink)]/80">
                                Respondent preview
                            </p>
                           
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-9 shrink-0 rounded-xl border border-transparent hover:border-[var(--brutal-ink)]/12 hover:bg-muted/60"
                            type="button"
                            aria-label="Close preview"
                            @click="emit('close')"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>

                    <div class="flex-1 overflow-y-auto bg-muted/25 px-4 py-6 sm:px-6">
                        <div class="mx-auto max-w-3xl">
                            <!-- Banner — matches Fill.vue -->
                            <div
                                v-if="bannerResolved || bannerCaptionTrim"
                                class="overflow-hidden rounded-2xl border-[1.5px] border-[var(--brutal-ink)]/12 bg-white shadow-[var(--shadow-sm)]"
                                :class="hasFormDescription ? 'mb-6' : 'mb-4'"
                            >
                                <img
                                    v-if="bannerResolved"
                                    :src="bannerResolved"
                                    alt=""
                                    class="aspect-[3/1] w-full object-cover"
                                />
                                <p
                                    v-if="bannerCaptionTrim"
                                    class="border-t border-[var(--brutal-ink)]/10 px-5 py-4 text-sm leading-relaxed text-muted-foreground"
                                >
                                    {{ bannerCaptionTrim }}
                                </p>
                            </div>

                            <PageHeader :title="titleText" :subtitle="description || undefined" />

                            <div v-if="fieldsSafe.length === 0" class="neo-muted-panel py-12 text-center" :class="hasFormDescription ? 'mt-6' : 'mt-3'">
                                <p class="text-sm font-semibold text-[var(--brutal-ink)]">No questions yet</p>
                                <p class="mt-1.5 max-w-sm mx-auto text-xs leading-relaxed text-muted-foreground">
                                    Add fields from the canvas — they’ll show up here exactly as respondents will see them.
                                </p>
                            </div>

                            <div
                                v-else
                                class="flex flex-col"
                                :class="hasFormDescription ? 'mt-5 gap-3' : 'mt-2 gap-2.5'"
                            >
                                <template v-for="field in fieldsSafe" :key="field.id">
                                    <!-- Heading -->
                                    <div
                                        v-if="field.type === 'heading'"
                                        class="rounded-2xl border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-yellow)]/15 px-5 py-4 shadow-[var(--shadow-sm)]"
                                    >
                                        <h2 class="font-display text-2xl font-bold tracking-tight text-[var(--brutal-ink)]">
                                            {{ metaString(field, 'content') || field.label }}
                                        </h2>
                                        <p v-if="field.description" class="mt-1 text-sm text-muted-foreground">
                                            {{ field.description }}
                                        </p>
                                    </div>

                                    <!-- Paragraph -->
                                    <div
                                        v-else-if="field.type === 'paragraph'"
                                        class="rounded-2xl border border-[var(--brutal-ink)]/15 bg-white/80 px-5 py-4 text-sm leading-relaxed text-muted-foreground shadow-[var(--shadow-xs)]"
                                    >
                                        {{ metaString(field, 'content') || field.description || field.label }}
                                    </div>

                                    <!-- Divider -->
                                    <hr v-else-if="field.type === 'divider'" class="brutal-divider my-1" />

                                    <!-- Answer fields — Card shell like Fill.vue -->
                                    <Card v-else class="rounded-2xl border-[1.5px] border-[var(--brutal-ink)]/12 shadow-[var(--shadow-xs)]">
                                        <CardHeader class="pb-2 pt-4">
                                            <CardTitle class="flex items-start gap-1 text-sm font-semibold text-[var(--brutal-ink)]">
                                                {{ field.label }}
                                                <span v-if="field.required" class="text-destructive">*</span>
                                            </CardTitle>
                                            <p v-if="field.description" class="text-xs leading-relaxed text-muted-foreground">
                                                {{ field.description }}
                                            </p>
                                        </CardHeader>
                                        <CardContent class="pb-4 pt-0">
                                            <Input
                                                v-if="['short_text', 'email', 'phone', 'number', 'time'].includes(field.type)"
                                                :type="
                                                    field.type === 'short_text'
                                                        ? 'text'
                                                        : field.type === 'phone'
                                                          ? 'tel'
                                                          : field.type === 'time'
                                                            ? 'text'
                                                            : field.type
                                                "
                                                :placeholder="
                                                    field.placeholder || `Enter ${field.label.toLowerCase()}…`
                                                "
                                                disabled
                                                class="pointer-events-none text-sm opacity-90"
                                                tabindex="-1"
                                            />

                                            <Textarea
                                                v-else-if="field.type === 'long_text'"
                                                :placeholder="field.placeholder || `Enter ${field.label.toLowerCase()}…`"
                                                rows="4"
                                                disabled
                                                class="pointer-events-none resize-none text-sm opacity-90"
                                                tabindex="-1"
                                            />

                                            <Select
                                                v-else-if="field.type === 'dropdown'"
                                                :model-value="undefined"
                                                disabled
                                            >
                                                <SelectTrigger class="pointer-events-none text-sm opacity-90">
                                                    <SelectValue placeholder="Choose an option" />
                                                </SelectTrigger>
                                                <SelectContent class="z-[200]">
                                                    <SelectItem
                                                        v-for="(opt, oi) in optionEntries(field)"
                                                        :key="optKey(opt, oi)"
                                                        :value="optionLabel(opt)"
                                                    >
                                                        <span class="flex items-center gap-2.5 py-0.5">
                                                            <img
                                                                v-if="optionImageUrl(opt)"
                                                                :src="choiceThumb(optionImageUrl(opt)!)"
                                                                alt=""
                                                                class="size-7 shrink-0 rounded-md border border-[var(--brutal-ink)]/10 object-cover"
                                                            />
                                                            <span>{{ optionLabel(opt) }}</span>
                                                        </span>
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>

                                            <div v-else-if="field.type === 'radio'" class="flex flex-col gap-2">
                                                <label
                                                    v-for="(opt, oi) in optionEntries(field)"
                                                    :key="optKey(opt, oi)"
                                                    class="flex cursor-default items-center gap-2.5 rounded-xl border border-[var(--brutal-ink)]/10 px-3 py-2 text-sm text-muted-foreground"
                                                >
                                                    <input
                                                        type="radio"
                                                        class="accent-primary"
                                                        disabled
                                                        :name="`preview_${field.id}`"
                                                        tabindex="-1"
                                                    />
                                                    <img
                                                        v-if="optionImageUrl(opt)"
                                                        :src="choiceThumb(optionImageUrl(opt)!)"
                                                        alt=""
                                                        class="size-8 shrink-0 rounded-full border border-[var(--brutal-ink)]/10 object-cover"
                                                    />
                                                    <span>{{ optionLabel(opt) }}</span>
                                                </label>
                                            </div>

                                            <div v-else-if="field.type === 'checkbox'" class="flex flex-col gap-2">
                                                <label
                                                    v-for="(opt, oi) in optionEntries(field)"
                                                    :key="optKey(opt, oi)"
                                                    class="flex cursor-default items-center gap-2.5 rounded-xl border border-[var(--brutal-ink)]/10 px-3 py-2 text-sm"
                                                >
                                                    <Checkbox
                                                        :id="`pv_${field.id}_${oi}`"
                                                        disabled
                                                        class="pointer-events-none opacity-80"
                                                    />
                                                    <img
                                                        v-if="optionImageUrl(opt)"
                                                        :src="choiceThumb(optionImageUrl(opt)!)"
                                                        alt=""
                                                        class="size-8 shrink-0 rounded-md border border-[var(--brutal-ink)]/10 object-cover"
                                                    />
                                                    <span>{{ optionLabel(opt) }}</span>
                                                </label>
                                            </div>

                                            <Input
                                                v-else-if="field.type === 'date'"
                                                type="date"
                                                disabled
                                                class="pointer-events-none text-sm opacity-90"
                                                tabindex="-1"
                                            />

                                            <div v-else-if="field.type === 'rating'" class="flex flex-col gap-3">
                                                <div class="flex gap-1.5">
                                                    <button
                                                        v-for="star in ratingStars(field)"
                                                        :key="`${field.id}_${star}`"
                                                        type="button"
                                                        class="rounded-lg p-1 transition active:scale-95"
                                                        @click.stop="ratingSelection[field.id] = star"
                                                    >
                                                        <Star
                                                            class="size-7"
                                                            :class="
                                                                (ratingSelection[field.id] ?? 0) >= star
                                                                    ? 'fill-amber-400 text-amber-400'
                                                                    : 'text-muted-foreground/40'
                                                            "
                                                        />
                                                    </button>
                                                </div>
                                                <p class="text-xs text-muted-foreground">Tap a star to preview the highlight.</p>
                                            </div>

                                            <div
                                                v-else-if="field.type === 'image_upload'"
                                                class="rounded-xl border border-dashed border-[var(--brutal-ink)]/25 bg-muted/25 p-4 text-center"
                                            >
                                                <ImagePlus class="mx-auto size-8 text-muted-foreground/35" />
                                                <p class="mt-2 text-xs font-medium text-muted-foreground">
                                                    Image upload (preview)
                                                </p>
                                                <p class="mt-0.5 text-[10px] text-muted-foreground/70">
                                                    {{ metaString(field, 'accepts') || 'png, jpg, jpeg' }}
                                                </p>
                                            </div>

                                            <div
                                                v-else-if="field.type === 'file_upload'"
                                                class="rounded-xl border border-dashed border-[var(--brutal-ink)]/25 bg-muted/25 p-4 text-center"
                                            >
                                                <Upload class="mx-auto size-8 text-muted-foreground/35" />
                                                <p class="mt-2 text-xs font-medium text-muted-foreground">
                                                    File upload (preview)
                                                </p>
                                                <p class="mt-0.5 text-[10px] text-muted-foreground/70">
                                                    {{ metaString(field, 'accepts') || 'pdf, doc, xls' }}
                                                </p>
                                            </div>

                                            <p v-else class="text-xs text-muted-foreground">
                                                Unsupported field type in preview: {{ field.type }}
                                            </p>
                                        </CardContent>
                                    </Card>
                                </template>
                            </div>

                            <!-- Stub submit row — mirrors Fill footer rhythm -->
                            <div
                                v-if="fieldsSafe.length > 0"
                                class="flex flex-wrap items-center justify-end gap-3 border-t border-[var(--brutal-ink)]/10"
                                :class="hasFormDescription ? 'mt-8 pt-6' : 'mt-5 pt-5'"
                            >
                                <Button variant="outline" type="button" disabled class="opacity-70">
                                    Cancel
                                </Button>
                                <Button type="button" disabled class="gap-2 opacity-90">
                                    <Send class="size-4" />
                                    Submit
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div class="shrink-0 border-t border-[var(--brutal-ink)]/12 bg-[var(--brutal-cream)]/55 px-4 py-3 sm:px-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-[11px] leading-relaxed text-muted-foreground">
                                Preview mode — nothing is saved. Close when you’re happy with the layout.
                            </p>
                            <Button
                                type="button"
                                class="shrink-0 rounded-xl border-[1.5px] border-[var(--brutal-ink)]/15 shadow-[var(--shadow-xs)]"
                                @click="emit('close')"
                            >
                                Done
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.preview-fade-enter-active,
.preview-fade-leave-active {
    transition: opacity 0.2s ease;
}
.preview-fade-enter-from,
.preview-fade-leave-to {
    opacity: 0;
}
</style>
