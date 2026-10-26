<script setup lang="ts">
import { computed, ref } from 'vue'
import { Star, Upload, ImagePlus, Send } from 'lucide-vue-next'
import { optionLabel, optionImageUrl, type FieldOptionEntry } from '@/components/modules/builder/fieldMapping'
import { normalizeBannerSrc } from '@/components/modules/builder/formBanner'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { DatePicker } from '@/components/ui/date-picker'
import { Textarea } from '@/components/ui/textarea'
import FormParagraphContent from '@/components/modules/dashboard/FormParagraphContent.vue'
import { Checkbox } from '@/components/ui/checkbox'
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select'
import type { FormPreviewField } from '@/types/form-builder'

type TFormPreviewVariant = 'dialog' | 'inline'

/** Seluruh teks yang dilihat responden; satu varian harus konsisten satu bahasa. */
interface IFormPreviewCopy {
    untitledTitle: string
    emptyTitle: string
    emptyHint: string
    chooseOption: string
    ratingHint: string
    imageUploadLabel: string
    fileUploadLabel: string
    unsupportedTypePrefix: string
    cancel: string
    submit: string
}

const PREVIEW_COPY: Record<TFormPreviewVariant, IFormPreviewCopy> = {
    dialog: {
        untitledTitle: 'Untitled form',
        emptyTitle: 'No questions yet',
        emptyHint: 'Add fields from the canvas — they’ll show up here exactly as respondents will see them.',
        chooseOption: 'Choose an option',
        ratingHint: 'Tap a star to preview the highlight.',
        imageUploadLabel: 'Image upload (preview)',
        fileUploadLabel: 'File upload (preview)',
        unsupportedTypePrefix: 'Unsupported field type in preview:',
        cancel: 'Cancel',
        submit: 'Submit',
    },
    inline: {
        untitledTitle: 'Formulir tanpa judul',
        emptyTitle: 'Belum ada pertanyaan',
        emptyHint: 'Tambahkan field dari panel kiri — akan muncul di sini persis seperti yang dilihat peserta.',
        chooseOption: 'Pilih opsi',
        ratingHint: 'Ketuk bintang untuk melihat sorotan.',
        imageUploadLabel: 'Unggah gambar (pratinjau)',
        fileUploadLabel: 'Unggah berkas (pratinjau)',
        unsupportedTypePrefix: 'Tipe field belum didukung di pratinjau:',
        cancel: 'Batal',
        submit: 'Kirim',
    },
}

const props = withDefaults(
    defineProps<{
        title?: string
        description?: string
        formBannerUrl?: string
        formBannerCaption?: string
        fields?: FormPreviewField[]
        /** `inline` merapatkan padding untuk kolom pratinjau live; `dialog` mengikuti modal. */
        variant?: TFormPreviewVariant
    }>(),
    { variant: 'dialog' }
)

const copy = computed<IFormPreviewCopy>(() => PREVIEW_COPY[props.variant])

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

const titleText = computed(() => props.title || copy.value.untitledTitle)

const hasFormDescription = computed(() => Boolean((props.description || '').trim()))

// Local non-persisted state so stars feel interactive (preview only)
const ratingSelection = ref<Record<string, number>>({})

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
    if (!Array.isArray(raw)) return []
    return raw.map(opt => {
        if (typeof opt === 'object' && opt !== null) return opt as FieldOptionEntry
        return { id: crypto.randomUUID(), type: 'text', label: String(opt) } as FieldOptionEntry
    })
}

function dropdownOptions(field: FormPreviewField): SimpleSelectOption[] {
    return optionEntries(field).map((opt, oi) => ({
        value: String(opt.id || `option-${oi}`),
        label: optionLabel(opt),
    }))
}

function choiceThumb(url: string): string {
    return normalizeBannerSrc(url)
}

/** URL thumbnail opsi gambar yang sudah dinormalisasi (string kosong bila tidak ada). */
function optionThumb(opt: FieldOptionEntry): string {
    return choiceThumb(optionImageUrl(opt) ?? '')
}

function optKey(opt: FieldOptionEntry, i: number): string {
    return `${opt.id || i}`
}

function ratingStars(field: FormPreviewField): number[] {
    const n = Math.min(Math.max(metaNumber(field, 'maxStars', 5), 1), 10)
    return Array.from({ length: n }, (_, i) => i + 1)
}
</script>

<template>
    <div class="mx-auto max-w-3xl" :class="variant === 'inline' ? 'px-4 py-5' : ''">
        <!-- Banner — matches Fill.vue -->
        <div
            v-if="bannerResolved || bannerCaptionTrim"
            class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
            :class="hasFormDescription ? 'mb-6' : 'mb-4'"
        >
            <img v-if="bannerResolved" :src="bannerResolved" alt="" class="aspect-[3/1] w-full object-cover" />
            <p
                v-if="bannerCaptionTrim"
                class="border-t border-border px-5 py-4 text-sm leading-relaxed text-muted-foreground"
            >
                {{ bannerCaptionTrim }}
            </p>
        </div>

        <PageHeader :title="titleText" :subtitle="description || undefined" />

        <div
            v-if="fieldsSafe.length === 0"
            class="app-surface-soft rounded-2xl py-12 text-center"
            :class="hasFormDescription ? 'mt-6' : 'mt-3'"
        >
            <p class="text-sm font-semibold text-foreground">{{ copy.emptyTitle }}</p>
            <p class="mt-1.5 max-w-sm mx-auto text-xs leading-relaxed text-muted-foreground">
                {{ copy.emptyHint }}
            </p>
        </div>

        <div v-else class="flex flex-col" :class="hasFormDescription ? 'mt-5 gap-3' : 'mt-2 gap-2.5'">
            <template v-for="field in fieldsSafe" :key="field.id">
                <!-- Heading -->
                <div v-if="field.type === 'heading'" class="rounded-2xl border border-primary/20 bg-primary/5 px-5 py-4 shadow-xs">
                    <h2 class="font-display text-2xl font-bold tracking-tight text-foreground">
                        {{ metaString(field, 'content') || field.label }}
                    </h2>
                    <p v-if="field.description" class="mt-1 text-sm text-muted-foreground">
                        {{ field.description }}
                    </p>
                </div>

                <!-- Paragraph -->
                <div v-else-if="field.type === 'paragraph'" class="rounded-2xl border border-border bg-muted/30 px-5 py-4 shadow-xs">
                    <FormParagraphContent
                        :content="metaString(field, 'content')"
                        :fallback="field.description || field.label"
                    />
                </div>

                <!-- Divider -->
                <hr v-else-if="field.type === 'divider'" class="app-divider my-1" />

                <!-- Image (display-only, non-input) -->
                <div v-else-if="field.type === 'image'" class="overflow-hidden rounded-2xl border border-border bg-card shadow-xs">
                    <img
                        v-if="choiceThumb(metaString(field, 'imageUrl'))"
                        :src="choiceThumb(metaString(field, 'imageUrl'))"
                        :alt="metaString(field, 'alt') || metaString(field, 'caption') || field.label"
                        class="w-full object-cover"
                    />
                    <p
                        v-if="metaString(field, 'caption').trim()"
                        class="border-t border-border px-5 py-3 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ metaString(field, 'caption') }}
                    </p>
                </div>

                <!-- Answer fields — Card shell like Fill.vue -->
                <Card v-else class="rounded-2xl border border-border shadow-xs">
                    <CardHeader class="pb-2 pt-4">
                        <CardTitle class="flex items-start gap-1 text-sm font-semibold text-foreground">
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
                            :placeholder="field.placeholder ?? ''"
                            disabled
                            class="pointer-events-none text-sm opacity-90"
                            tabindex="-1"
                        />

                        <Textarea
                            v-else-if="field.type === 'long_text'"
                            :placeholder="field.placeholder ?? ''"
                            rows="4"
                            disabled
                            class="pointer-events-none resize-none text-sm opacity-90"
                            tabindex="-1"
                        />

                        <div v-else-if="field.type === 'dropdown'" class="space-y-2">
                            <SimpleSelect
                                model-value=""
                                :options="dropdownOptions(field)"
                                :placeholder="copy.chooseOption"
                                disabled
                                class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm disabled:opacity-90"
                                :aria-label="copy.chooseOption"
                            />
                            <div class="space-y-1.5 rounded-xl border border-border/70 bg-muted/20 p-2">
                                <div
                                    v-for="(opt, oi) in optionEntries(field)"
                                    :key="`dropdown-preview-${optKey(opt, oi)}`"
                                    class="flex items-center gap-2 rounded-lg border border-border/60 bg-card px-2.5 py-1.5 text-xs"
                                >
                                    <span>{{ optionLabel(opt) }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="field.type === 'radio'" class="flex flex-col gap-2">
                            <label
                                v-for="(opt, oi) in optionEntries(field)"
                                :key="optKey(opt, oi)"
                                class="flex cursor-default items-center gap-2.5 rounded-xl border border-border px-3 py-2 text-sm text-muted-foreground"
                            >
                                <input
                                    type="radio"
                                    class="accent-primary"
                                    disabled
                                    :name="`preview_${field.id}`"
                                    tabindex="-1"
                                />
                                <div
                                    v-if="opt.type === 'image' && optionThumb(opt)"
                                    class="size-16 shrink-0 overflow-hidden rounded-md border border-border"
                                >
                                    <img :src="optionThumb(opt)" alt="" class="size-full object-cover" />
                                </div>
                                <span v-else>{{ optionLabel(opt) }}</span>
                            </label>
                        </div>

                        <div v-else-if="field.type === 'checkbox'" class="flex flex-col gap-2">
                            <label
                                v-for="(opt, oi) in optionEntries(field)"
                                :key="optKey(opt, oi)"
                                class="flex cursor-default items-center gap-2.5 rounded-xl border border-border px-3 py-2 text-sm"
                            >
                                <Checkbox :id="`pv_${field.id}_${oi}`" disabled class="pointer-events-none opacity-80" />
                                <div
                                    v-if="opt.type === 'image' && optionThumb(opt)"
                                    class="size-16 shrink-0 overflow-hidden rounded-md border border-border"
                                >
                                    <img :src="optionThumb(opt)" alt="" class="size-full object-cover" />
                                </div>
                                <span v-else>{{ optionLabel(opt) }}</span>
                            </label>
                        </div>

                        <DatePicker
                            v-else-if="field.type === 'date'"
                            model-value=""
                            disabled
                            placeholder="Tanggal"
                            class="text-sm opacity-90"
                        />

                        <div v-else-if="field.type === 'rating'" class="flex flex-col gap-3">
                            <div class="flex gap-1.5">
                                <button
                                    v-for="star in ratingStars(field)"
                                    :key="`${field.id}_${star}`"
                                    type="button"
                                    class=" p-1 transition"
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
                            <p class="text-xs text-muted-foreground">{{ copy.ratingHint }}</p>
                        </div>

                        <div
                            v-else-if="field.type === 'image_upload'"
                            class="rounded-xl border border-dashed border-border bg-muted/25 p-4 text-center"
                        >
                            <ImagePlus class="mx-auto size-8 text-muted-foreground/35" />
                            <p class="mt-2 text-xs font-medium text-muted-foreground">{{ copy.imageUploadLabel }}</p>
                            <p class="mt-0.5 text-[10px] text-muted-foreground/70">
                                {{ metaString(field, 'accepts') || 'png, jpg, jpeg' }}
                            </p>
                        </div>

                        <div
                            v-else-if="field.type === 'file_upload'"
                            class="rounded-xl border border-dashed border-border bg-muted/25 p-4 text-center"
                        >
                            <Upload class="mx-auto size-8 text-muted-foreground/35" />
                            <p class="mt-2 text-xs font-medium text-muted-foreground">{{ copy.fileUploadLabel }}</p>
                            <p class="mt-0.5 text-[10px] text-muted-foreground/70">
                                {{ metaString(field, 'accepts') || 'pdf, doc, xls' }}
                            </p>
                        </div>

                        <p v-else class="text-xs text-muted-foreground">
                            {{ copy.unsupportedTypePrefix }} {{ field.type }}
                        </p>
                    </CardContent>
                </Card>
            </template>
        </div>

        <!-- Stub submit row — mirrors Fill footer rhythm -->
        <div
            v-if="fieldsSafe.length > 0"
            class="flex flex-wrap items-center justify-end gap-3 border-t border-border"
            :class="hasFormDescription ? 'mt-8 pt-6' : 'mt-5 pt-5'"
        >
            <Button variant="outline" type="button" disabled class="opacity-70"> {{ copy.cancel }} </Button>
            <Button type="button" disabled class="gap-2 opacity-90">
                <Send class="size-4" />
                {{ copy.submit }}
            </Button>
        </div>
    </div>
</template>
