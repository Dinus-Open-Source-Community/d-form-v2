<script setup lang="ts">
import { computed } from 'vue';
import { CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { DatePicker } from '@/components/ui/date-picker';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { StyledSelect } from '@/components/ui/styled-select';
import type { UnwrapNestedRefs } from 'vue';
import { Star, ImagePlus, Upload, X } from 'lucide-vue-next';
import type { TFormFillPageContext } from '@/hooks/useFormFillPage';
import { formatParagraphContentToHtml } from '@/lib/formParagraphContent';

const props = withDefaults(
    defineProps<{
        ctx: UnwrapNestedRefs<TFormFillPageContext>;
        field: IFormField;
        participationSlot: { slotIndex: number | null; title: string };
        storageKey: string;
        /** 'linear' = legacy one field / card; 'bundleParticipant' = stacked in per-person card */
        variant: 'linear' | 'bundleParticipant';
        stackIndex?: number;
        imageUploadFillReadyFn: (key: string) => boolean;
    }>(),
    { stackIndex: 0 }
);

const emit = defineEmits<{
    openLightbox: [src: string | undefined, title: string];
}>();

function textAnswer(name: string): string {
    const v = props.ctx.answerForm[name];
    return typeof v === 'string' ? v : '';
}

function setTextAnswer(name: string, value: string | number): void {
    props.ctx.setAnswer(name, String(value));
}

const showSubtitle = computed(() => props.variant === 'linear' && Boolean(props.participationSlot.title));

const headerBorderClass = computed(() => {
    if (props.variant === 'bundleParticipant') {
        return props.stackIndex > 0 ? 'border-t border-border' : '';
    }
    return '';
});

/** Tighter vertical rhythm when multiple fields stack in one participant card */
const cardHeaderSpacingClass = computed(() => {
    if (props.variant === 'bundleParticipant') {
        return props.stackIndex > 0 ? 'gap-0.5 pt-2 pb-0.5' : 'gap-0.5 pt-2.5 pb-0.5';
    }
    return 'gap-1 pb-1.5 pt-3';
});

const cardContentSpacingClass = computed(() => {
    if (props.variant === 'bundleParticipant') {
        return 'pb-2 pt-0';
    }
    return 'pb-3 pt-0';
});

const fieldErrorSpacingClass = computed(() => (props.variant === 'bundleParticipant' ? 'mt-1' : 'mt-1.5'));

const showDescription = computed(() => {
    if (!props.field.description) return false;
    if (props.variant === 'bundleParticipant') return true;
    return props.participationSlot.slotIndex === null;
});

const descriptionHtml = computed(() =>
    props.field.description ? formatParagraphContentToHtml(props.field.description) : ''
);

function fillReady(): boolean {
    return props.imageUploadFillReadyFn(props.storageKey);
}
</script>

<template>
    <CardHeader :class="[headerBorderClass, cardHeaderSpacingClass]">
        <CardTitle class="text-foreground flex flex-col items-start gap-0.5 text-sm font-semibold tracking-[-0.005em]">
            <span class="flex items-start gap-1">
                {{ field.label }}
                <span v-if="ctx.isRequired(field)" class="text-destructive">*</span>
            </span>
            <span v-if="showSubtitle" class="text-muted-foreground text-xs font-normal">{{
                participationSlot.title
            }}</span>
        </CardTitle>
        <p
            v-if="showDescription"
            class="text-muted-foreground text-xs"
            :class="variant === 'bundleParticipant' ? 'mt-0 leading-snug' : 'leading-relaxed'"
            v-html="descriptionHtml"
        />
    </CardHeader>
    <CardContent :class="cardContentSpacingClass">
        <template
            v-if="
                ['short_text', 'email', 'phone', 'number', 'time', 'input'].includes(ctx.builderType(field)) &&
                !ctx.isMultipleSelect(field)
            "
        >
            <Input
                :id="storageKey"
                :type="ctx.getInputSubtype(field)"
                :placeholder="ctx.getPlaceholder(field)"
                :maxlength="ctx.maxLengthForField(field) ?? undefined"
                :model-value="textAnswer(storageKey)"
                @update:model-value="setTextAnswer(storageKey, $event)"
            />
            <p
                v-if="ctx.maxLengthForField(field)"
                class="text-muted-foreground mt-1 text-right text-[10px] tabular-nums"
            >
                {{ textAnswer(storageKey).length }} / {{ ctx.maxLengthForField(field) }}
            </p>
        </template>

        <div v-else-if="ctx.builderType(field) === 'rating'" class="flex flex-col gap-2">
            <div class="flex gap-1.5">
                <button
                    v-for="rating in Number(ctx.metadata(field).maxStars ?? 5)"
                    :key="rating"
                    type="button"
                    class="p-1"
                    @click="ctx.setAnswer(storageKey, String(rating))"
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
            <p class="text-muted-foreground text-xs">Choose one rating.</p>
        </div>

        <template
            v-else-if="
                ctx.builderType(field) === 'long_text' ||
                ctx.builderType(field) === 'address' ||
                field.type === 'textarea'
            "
        >
            <Textarea
                :id="storageKey"
                :placeholder="ctx.getPlaceholder(field)"
                :maxlength="ctx.maxLengthForField(field) ?? undefined"
                :model-value="textAnswer(storageKey)"
                @update:model-value="setTextAnswer(storageKey, $event)"
                rows="4"
            />
            <p
                v-if="ctx.maxLengthForField(field)"
                class="text-muted-foreground mt-1 text-right text-[10px] tabular-nums"
            >
                {{ textAnswer(storageKey).length }} / {{ ctx.maxLengthForField(field) }}
            </p>
        </template>

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
                class="border-border bg-card text-foreground hover:border-primary/30 hover:bg-muted/40 flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2 text-sm font-medium shadow-xs transition-[border-color,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]"
            >
                <Checkbox
                    :id="`${storageKey}-${row.label}`"
                    :model-value="ctx.isCheckboxSelected(storageKey, row.label)"
                    @update:model-value="
                        (value: boolean | 'indeterminate') =>
                            ctx.onCheckboxToggle(storageKey, row.label, value === true)
                    "
                />
                <div
                    v-if="row.type === 'image' && row.imageSrc"
                    class="border-border size-16 shrink-0 overflow-hidden rounded-md border"
                >
                    <img :src="row.imageSrc" alt="" class="size-full object-cover" />
                </div>
                <span v-else>{{ row.label }}</span>
            </label>
        </div>

        <div v-else-if="ctx.isRadioLike(field)" class="flex flex-col gap-1.5">
            <label
                v-for="row in ctx.getOptionRows(field)"
                :key="row.label"
                class="border-border bg-card text-foreground hover:border-primary/30 hover:bg-muted/40 flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2 text-sm font-medium shadow-xs transition-[border-color,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]"
            >
                <input
                    type="radio"
                    :name="`${field.name}-${participationSlot.slotIndex ?? 'lead'}`"
                    :value="row.label"
                    :checked="(ctx.answerForm[storageKey] as string) === row.label"
                    class="accent-primary size-4"
                    @change="() => ctx.setAnswer(storageKey, row.label)"
                />
                <div
                    v-if="row.type === 'image' && row.imageSrc"
                    class="border-border size-16 shrink-0 overflow-hidden rounded-md border"
                >
                    <img :src="row.imageSrc" alt="" class="size-full object-cover" />
                </div>
                <span v-else>{{ row.label }}</span>
            </label>
        </div>

        <StyledSelect
            v-else-if="
                (ctx.builderType(field) === 'dropdown' || field.type === 'select') && !ctx.isMultipleSelect(field)
            "
            :id="storageKey"
            :model-value="textAnswer(storageKey)"
            :options="ctx.getOptionRows(field)"
            :placeholder="ctx.getPlaceholder(field) || 'Select an option'"
            class="h-11 min-h-11"
            @update:model-value="setTextAnswer(storageKey, $event)"
        />

        <div
            v-else-if="['file_upload', 'image_upload', 'fileUpload'].includes(ctx.builderType(field))"
            class="border-border bg-muted/20 hover:border-primary/30 relative overflow-hidden rounded-xl border border-dashed transition-colors"
            :class="
                fillReady()
                    ? 'p-0'
                    : variant === 'bundleParticipant'
                      ? 'hover:bg-muted/30 p-4 text-center'
                      : 'hover:bg-muted/30 p-6 text-center'
            "
        >
            <div v-if="fillReady()" class="bg-muted/30 relative aspect-video w-full sm:aspect-[4/3]">
                <button
                    type="button"
                    class="focus-visible:ring-ring focus-visible:ring-offset-background absolute inset-0 z-10 flex w-full focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                    @click="
                        emit('openLightbox', ctx.filePreviewUrls[storageKey], (ctx.answerForm[storageKey] as File).name)
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
                    class="border-border/80 bg-background/95 text-destructive absolute top-2 right-2 z-20 grid size-9 place-items-center border shadow-md backdrop-blur-sm"
                    :aria-label="`Remove ${(ctx.answerForm[storageKey] as File).name}`"
                    @click.stop="ctx.clearFileUpload(storageKey)"
                >
                    <X class="size-4" />
                </button>
                <div
                    class="pointer-events-none absolute inset-x-0 bottom-0 z-20 flex flex-col items-center gap-1 bg-gradient-to-t from-black/60 via-black/25 to-transparent px-3 pt-14 pb-3 text-center"
                >
                    <label
                        class="bg-background/95 text-foreground hover:bg-background pointer-events-auto cursor-pointer border border-white/20 px-3 py-1.5 text-xs font-medium shadow-sm backdrop-blur-sm transition-colors"
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
                <div class="border-border bg-card grid size-12 place-items-center rounded-full border shadow-xs">
                    <component
                        :is="ctx.builderType(field) === 'image_upload' ? ImagePlus : Upload"
                        class="text-muted-foreground size-5"
                    />
                </div>
                <div>
                    <p class="text-foreground text-sm font-semibold">Click to upload</p>
                    <p class="text-muted-foreground mt-0.5 text-[10px]">{{ ctx.fileHint(field) }}</p>
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
                class="border-border bg-card relative z-10 mt-4 flex items-center justify-center gap-2 rounded-xl border p-3 shadow-xs"
            >
                <span class="text-foreground max-w-[200px] truncate text-xs font-medium">{{
                    (ctx.answerForm[storageKey] as File).name
                }}</span>
                <button type="button" class="text-destructive" @click="ctx.clearFileUpload(storageKey)">
                    <X class="size-4" />
                </button>
            </div>
        </div>

        <p v-if="ctx.fieldError(storageKey)" :class="[fieldErrorSpacingClass, 'text-destructive text-xs font-medium']">
            {{ ctx.fieldError(storageKey) }}
        </p>
    </CardContent>
</template>
