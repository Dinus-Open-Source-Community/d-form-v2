<script setup lang="ts">
import { computed, type UnwrapNestedRefs } from 'vue';
import { CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { DatePicker } from '@/components/ui/date-picker';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { StyledSelect } from '@/components/ui/styled-select';
import FileUploadCard, { type TFileUploadState } from '@/components/core/FileUploadCard.vue';
import { Star } from 'lucide-vue-next';
import { formatParagraphContentToHtml } from '@/lib/formParagraphContent';
import { formFillSlotUi } from '@/lib/formFillSlotUi';
import type { TFormFillPageContext } from '@/hooks/useFormFillPage';
import type { IFormFieldParticipationSlot, TFormFillSlotVariant } from '@/types/form';

const props = withDefaults(
    defineProps<{
        ctx: UnwrapNestedRefs<TFormFillPageContext>;
        field: IFormField;
        participationSlot: IFormFieldParticipationSlot;
        storageKey: string;
        /** 'linear' = legacy one field / card; 'bundleParticipant' = stacked in per-person card */
        variant: TFormFillSlotVariant;
        stackIndex?: number;
        imageUploadFillReadyFn: (key: string) => boolean;
    }>(),
    { stackIndex: 0 }
);

const emit = defineEmits<{
    openLightbox: [src: string | undefined, title: string];
}>();

const ui = computed(() =>
    formFillSlotUi({
        variant: props.variant,
        stackIndex: props.stackIndex,
        fieldHasDescription: Boolean(props.field.description),
        slotHasTitle: Boolean(props.participationSlot.title),
        slotIndex: props.participationSlot.slotIndex,
    })
);

const descriptionHtml = computed(() =>
    props.field.description ? formatParagraphContentToHtml(props.field.description) : ''
);

/** Field yang jawabannya berkas: kartu unggah menggantikan dropzone lama. */
const isFileField = computed(() =>
    ['file_upload', 'image_upload', 'fileUpload'].includes(props.ctx.builderType(props.field))
);

/** Berkas yang menunggu submit; nilai awal field file selalu `null`. */
const selectedFile = computed<File | null>(() => {
    const value = props.ctx.answerForm[props.storageKey];

    return typeof File !== 'undefined' && value instanceof File ? value : null;
});

/** Progres milik visit submit yang sama, jadi angkanya nyata dan bisa dibatalkan. */
const fileCardState = computed<TFileUploadState>(() => {
    if (props.ctx.answerForm.processing && selectedFile.value) return 'uploading';
    if (props.ctx.fieldError(props.storageKey)) return 'error';

    return selectedFile.value ? 'completed' : 'idle';
});

const fileCardIdleLabel = computed(() =>
    props.ctx.builderType(props.field) === 'image_upload' ? 'Pilih foto' : 'Pilih berkas'
);

/** Pratinjau hanya dari object URL yang dikelola hook upload (tanpa base64). */
const filePreviewUrl = computed(() => (fillReady() ? props.ctx.filePreviewUrls[props.storageKey] : ''));

function textAnswer(name: string): string {
    const value = props.ctx.answerForm[name];
    return typeof value === 'string' ? value : '';
}

function setTextAnswer(name: string, value: string | number): void {
    props.ctx.setAnswer(name, String(value));
}

function fillReady(): boolean {
    return props.imageUploadFillReadyFn(props.storageKey);
}

function openLightbox(): void {
    emit('openLightbox', props.ctx.filePreviewUrls[props.storageKey], props.ctx.fileAnswerName(props.storageKey));
}

/**
 * Menyimpan berkas pilihan kartu lewat jalur yang sama dengan input file lama
 * (`applyFileSelection`): validasi klien, object URL pratinjau, dan nilai form identik.
 * Galat lama dibersihkan hanya setelah berkas lolos agar kartu keluar dari state error.
 */
async function pickFile(file: File): Promise<void> {
    const accepted = await props.ctx.applyFileSelection(props.storageKey, file);
    if (accepted) props.ctx.answerForm.clearErrors(props.storageKey);
}
</script>

<template>
    <CardHeader :class="[ui.headerBorderClass, ui.cardHeaderSpacingClass]">
        <CardTitle class="text-foreground flex flex-col items-start gap-0.5 text-sm font-semibold tracking-[-0.005em]">
            <span class="flex items-start gap-1">
                {{ field.label }}
                <span v-if="ctx.isRequired(field)" class="text-destructive">*</span>
            </span>
            <span v-if="ui.showSubtitle" class="text-muted-foreground text-xs font-normal">{{
                participationSlot.title
            }}</span>
        </CardTitle>
        <p
            v-if="ui.showDescription"
            class="text-muted-foreground text-xs"
            :class="variant === 'bundleParticipant' ? 'mt-0 leading-snug' : 'leading-relaxed'"
            v-html="descriptionHtml"
        />
    </CardHeader>
    <CardContent :class="ui.cardContentSpacingClass">
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
                    :checked="textAnswer(storageKey) === row.label"
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

        <div v-else-if="isFileField" class="space-y-1.5">
            <FileUploadCard
                :state="fileCardState"
                :idle-label="fileCardIdleLabel"
                :hint="ctx.fileHint(field)"
                :accept="ctx.acceptValue(field)"
                :file-name="selectedFile?.name ?? ''"
                :file-size="selectedFile?.size ?? 0"
                :mime="selectedFile?.type ?? ''"
                :progress="ctx.submitProgress"
                :preview-url="filePreviewUrl"
                :error-message="ctx.fieldError(storageKey) ?? ''"
                :disabled="ctx.answerForm.processing"
                @pick="pickFile"
                @cancel="ctx.cancelSubmit"
                @remove="ctx.clearFileUpload(storageKey)"
            />
            <button
                v-if="filePreviewUrl"
                type="button"
                class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/30 rounded-md text-xs underline-offset-2 outline-none hover:underline focus-visible:ring-[3px]"
                @click="openLightbox"
            >
                Lihat ukuran penuh
            </button>
        </div>

        <p
            v-if="!isFileField && ctx.fieldError(storageKey)"
            :class="[ui.errorSpacingClass, 'text-destructive text-xs font-medium']"
        >
            {{ ctx.fieldError(storageKey) }}
        </p>
    </CardContent>
</template>
