<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { X, Star, Upload, ImagePlus, Send } from 'lucide-vue-next';
import { optionLabel, optionImageUrl, type IFieldOptionEntry } from '@/components/modules/builder/fieldMapping';
import { formatDefaultUploadFormats } from '@/lib/formUploadFormats';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import { createUuid } from '@/lib/uuid';
import PageHeader from '@/components/modules/builder/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { DatePicker } from '@/components/ui/date-picker';
import { Textarea } from '@/components/ui/textarea';
import FormParagraphContent from '@/components/core/FormParagraphContent.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select';

/** Mirrors canvas builder field shape used by Show/Create with unknown metadata values. */
export interface IFormPreviewField {
    id: string;
    type: string;
    label: string;
    description?: string;
    required?: boolean;
    placeholder?: string;
    options?: IFieldOptionEntry[];
    metadata?: Record<string, unknown>;
}

const props = defineProps<{
    open: boolean;
    title?: string;
    description?: string;
    formBannerUrl?: string;
    formBannerCaption?: string;
    fields?: IFormPreviewField[];
}>();

const emit = defineEmits<{ close: [] }>();

const fieldsSafe = computed(() => props.fields ?? []);

const bannerResolved = computed(() => {
    const u = (props.formBannerUrl || '').trim();
    if (!u) return '';
    if (u.startsWith('data:')) return u;
    if (/^https?:\/\//i.test(u)) return u;
    if (u.startsWith('/')) return u;
    return `/storage/${u.replace(/^\/+/, '')}`;
});

const bannerCaptionTrim = computed(() => (props.formBannerCaption || '').trim());

const titleText = computed(() => props.title || 'Untitled form');

const hasFormDescription = computed(() => Boolean((props.description || '').trim()));

// Local non-persisted state so stars / radii feel interactive (preview only)
const ratingSelection = ref<Record<string, number>>({});

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        const next: Record<string, number> = { ...ratingSelection.value };
        for (const f of fieldsSafe.value) {
            if (f.type === 'rating' && next[f.id] === undefined) {
                next[f.id] = 0;
            }
        }
        ratingSelection.value = next;
    }
);

function metaString(field: IFormPreviewField, key: string): string {
    const v = field.metadata?.[key];
    return typeof v === 'string' ? v : '';
}

function metaNumber(field: IFormPreviewField, key: string, fallback: number): number {
    const v = field.metadata?.[key];
    return typeof v === 'number' && !Number.isNaN(v) ? v : fallback;
}

function optionEntries(field: IFormPreviewField): IFieldOptionEntry[] {
    const raw = field.options;
    if (!Array.isArray(raw)) return [];
    return raw.map((opt) => {
        if (typeof opt === 'object' && opt !== null) return opt as IFieldOptionEntry;
        return { id: createUuid(), type: 'text', label: String(opt) } as IFieldOptionEntry;
    });
}

function dropdownOptions(field: IFormPreviewField): SimpleSelectOption[] {
    return optionEntries(field).map((opt, oi) => ({
        value: String(opt.id || `option-${oi}`),
        label: optionLabel(opt),
    }));
}

function choiceThumb(url: string | undefined): string {
    return url ? normalizePublicStorageUrl(url) : '';
}

function optKey(opt: IFieldOptionEntry, i: number): string {
    return `${opt.id || i}`;
}

function ratingStars(field: IFormPreviewField): number[] {
    const n = Math.min(Math.max(metaNumber(field, 'maxStars', 5), 1), 10);
    return Array.from({ length: n }, (_, i) => i + 1);
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
                <div class="bg-foreground/40 absolute inset-0 backdrop-blur-[2px]" @click="emit('close')" />

                <div
                    class="border-border bg-card relative z-10 flex max-h-[min(92vh,880px)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border shadow-sm"
                >
                    <!-- Chrome: matches dashboard “tool” panels -->
                    <div
                        class="border-border from-muted/60 via-card to-primary/5 flex shrink-0 items-start justify-between gap-3 border-b bg-gradient-to-r px-4 py-3 sm:px-5"
                    >
                        <div class="flex h-full w-full items-start">
                            <p class="text-muted-foreground text-base font-extrabold tracking-wider uppercase">
                                Respondent preview
                            </p>
                        </div>
                        <Button
                            radius="icon"
                            variant="ghost"
                            size="icon"
                            class="hover:border-border hover:bg-muted/60 size-9 shrink-0 border border-transparent"
                            type="button"
                            aria-label="Close preview"
                            @click="emit('close')"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>

                    <div class="bg-muted/25 flex-1 overflow-y-auto px-4 py-6 sm:px-6">
                        <div class="mx-auto max-w-3xl">
                            <!-- Banner — matches Fill.vue -->
                            <div
                                v-if="bannerResolved || bannerCaptionTrim"
                                class="border-border bg-card overflow-hidden rounded-2xl border shadow-sm"
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
                                    class="border-border text-muted-foreground border-t px-5 py-4 text-sm leading-relaxed"
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
                                <p class="text-foreground text-sm font-semibold">No questions yet</p>
                                <p class="text-muted-foreground mx-auto mt-1.5 max-w-sm text-xs leading-relaxed">
                                    Add fields from the canvas — they’ll show up here exactly as respondents will see
                                    them.
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
                                        class="border-primary/20 bg-primary/5 rounded-2xl border px-5 py-4 shadow-xs"
                                    >
                                        <h2 class="font-display text-foreground text-2xl font-bold tracking-tight">
                                            {{ metaString(field, 'content') || field.label }}
                                        </h2>
                                        <p v-if="field.description" class="text-muted-foreground mt-1 text-sm">
                                            {{ field.description }}
                                        </p>
                                    </div>

                                    <!-- Paragraph -->
                                    <div
                                        v-else-if="field.type === 'paragraph'"
                                        class="border-border bg-muted/30 rounded-2xl border px-5 py-4 shadow-xs"
                                    >
                                        <FormParagraphContent
                                            :content="metaString(field, 'content')"
                                            :fallback="field.description || field.label"
                                        />
                                    </div>

                                    <!-- Divider -->
                                    <hr v-else-if="field.type === 'divider'" class="app-divider my-1" />

                                    <!-- Image (display-only, non-input) -->
                                    <div
                                        v-else-if="field.type === 'image'"
                                        class="border-border bg-card overflow-hidden rounded-2xl border shadow-xs"
                                    >
                                        <div
                                            v-if="field.label || field.description"
                                            class="space-y-1 px-5 pt-4"
                                            :class="choiceThumb(metaString(field, 'imageUrl')) || metaString(field, 'caption').trim() ? 'pb-3' : 'pb-4'"
                                        >
                                            <h3
                                                v-if="field.label"
                                                class="text-sm font-semibold text-foreground"
                                            >
                                                {{ field.label }}
                                            </h3>
                                            <p
                                                v-if="field.description"
                                                class="text-xs leading-relaxed text-muted-foreground"
                                            >
                                                {{ field.description }}
                                            </p>
                                        </div>
                                        <img
                                            v-if="choiceThumb(metaString(field, 'imageUrl'))"
                                            :src="choiceThumb(metaString(field, 'imageUrl'))"
                                            :alt="
                                                metaString(field, 'alt') || metaString(field, 'caption') || field.label
                                            "
                                            class="w-full object-cover"
                                            :class="(field.label || field.description) ? 'border-t border-border' : ''"
                                        />
                                        <p
                                            v-if="metaString(field, 'caption').trim()"
                                            class="border-border text-muted-foreground border-t px-5 py-3 text-sm leading-relaxed"
                                        >
                                            {{ metaString(field, 'caption') }}
                                        </p>
                                    </div>

                                    <!-- Answer fields — Card shell like Fill.vue -->
                                    <Card v-else class="border-border rounded-2xl border shadow-xs">
                                        <CardHeader class="pt-4 pb-2">
                                            <CardTitle
                                                class="text-foreground flex items-start gap-1 text-sm font-semibold"
                                            >
                                                {{ field.label }}
                                                <span v-if="field.required" class="text-destructive">*</span>
                                            </CardTitle>
                                            <p
                                                v-if="field.description"
                                                class="text-muted-foreground text-xs leading-relaxed"
                                            >
                                                {{ field.description }}
                                            </p>
                                        </CardHeader>
                                        <CardContent class="pt-0 pb-4">
                                            <Input
                                                v-if="
                                                    ['short_text', 'email', 'phone', 'number', 'time'].includes(
                                                        field.type
                                                    )
                                                "
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
                                                    placeholder="Choose an option"
                                                    disabled
                                                    class="border-border/80 bg-background/80 h-10 w-full text-xs disabled:opacity-90 sm:text-sm"
                                                    aria-label="Choose an option"
                                                />
                                                <div
                                                    class="border-border/70 bg-muted/20 space-y-1.5 rounded-xl border p-2"
                                                >
                                                    <div
                                                        v-for="(opt, oi) in optionEntries(field)"
                                                        :key="`dropdown-preview-${optKey(opt, oi)}`"
                                                        class="border-border/60 bg-card flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs"
                                                    >
                                                        <span>{{ optionLabel(opt) }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-else-if="field.type === 'radio'" class="flex flex-col gap-2">
                                                <label
                                                    v-for="(opt, oi) in optionEntries(field)"
                                                    :key="optKey(opt, oi)"
                                                    class="border-border text-muted-foreground flex cursor-default items-center gap-2.5 rounded-xl border px-3 py-2 text-sm"
                                                >
                                                    <input
                                                        type="radio"
                                                        class="accent-primary"
                                                        disabled
                                                        :name="`preview_${field.id}`"
                                                        tabindex="-1"
                                                    />
                                                    <div
                                                        v-if="opt.type === 'image' && optionImageUrl(opt)"
                                                        class="border-border size-16 shrink-0 overflow-hidden rounded-md border"
                                                    >
                                                        <img
                                                            :src="choiceThumb(optionImageUrl(opt))"
                                                            alt=""
                                                            class="size-full object-cover"
                                                        />
                                                    </div>
                                                    <span v-else>{{ optionLabel(opt) }}</span>
                                                </label>
                                            </div>

                                            <div v-else-if="field.type === 'checkbox'" class="flex flex-col gap-2">
                                                <label
                                                    v-for="(opt, oi) in optionEntries(field)"
                                                    :key="optKey(opt, oi)"
                                                    class="border-border flex cursor-default items-center gap-2.5 rounded-xl border px-3 py-2 text-sm"
                                                >
                                                    <Checkbox
                                                        :id="`pv_${field.id}_${oi}`"
                                                        disabled
                                                        class="pointer-events-none opacity-80"
                                                    />
                                                    <div
                                                        v-if="opt.type === 'image' && optionImageUrl(opt)"
                                                        class="border-border size-16 shrink-0 overflow-hidden rounded-md border"
                                                    >
                                                        <img
                                                            :src="choiceThumb(optionImageUrl(opt))"
                                                            alt=""
                                                            class="size-full object-cover"
                                                        />
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
                                                        class="p-1 transition"
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
                                                <p class="text-muted-foreground text-xs">
                                                    Tap a star to preview the highlight.
                                                </p>
                                            </div>

                                            <div
                                                v-else-if="field.type === 'image_upload'"
                                                class="border-border bg-muted/25 rounded-xl border border-dashed p-4 text-center"
                                            >
                                                <ImagePlus class="text-muted-foreground/35 mx-auto size-8" />
                                                <p class="text-muted-foreground mt-2 text-xs font-medium">
                                                    Image upload (preview)
                                                </p>
                                                <p class="text-muted-foreground/70 mt-0.5 text-[10px]">
                                                    {{
                                                        metaString(field, 'accepts') ||
                                                        formatDefaultUploadFormats('image_upload')
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                v-else-if="field.type === 'file_upload'"
                                                class="border-border bg-muted/25 rounded-xl border border-dashed p-4 text-center"
                                            >
                                                <Upload class="text-muted-foreground/35 mx-auto size-8" />
                                                <p class="text-muted-foreground mt-2 text-xs font-medium">
                                                    File upload (preview)
                                                </p>
                                                <p class="text-muted-foreground/70 mt-0.5 text-[10px]">
                                                    {{
                                                        metaString(field, 'accepts') ||
                                                        formatDefaultUploadFormats('file_upload')
                                                    }}
                                                </p>
                                            </div>

                                            <p v-else class="text-muted-foreground text-xs">
                                                Unsupported field type in preview: {{ field.type }}
                                            </p>
                                        </CardContent>
                                    </Card>
                                </template>
                            </div>

                            <!-- Stub submit row — mirrors Fill footer rhythm -->
                            <div
                                v-if="fieldsSafe.length > 0"
                                class="border-border flex flex-wrap items-center justify-end gap-3 border-t"
                                :class="hasFormDescription ? 'mt-8 pt-6' : 'mt-5 pt-5'"
                            >
                                <Button variant="outline" type="button" disabled class="opacity-70"> Cancel </Button>
                                <Button type="button" disabled class="gap-2 opacity-90">
                                    <Send class="size-4" />
                                    Submit
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div class="border-border bg-muted/30 shrink-0 border-t px-4 py-3 sm:px-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-muted-foreground text-[11px] leading-relaxed">
                                Preview mode — nothing is saved. Close when you’re happy with the layout.
                            </p>
                            <Button
                                type="button"
                                class="border-border shrink-0 border shadow-xs"
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
