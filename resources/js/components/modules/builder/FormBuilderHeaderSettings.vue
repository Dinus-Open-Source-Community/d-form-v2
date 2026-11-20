<script setup lang="ts">
import { computed } from 'vue';
import { Label } from '@/components/ui/label';
import { AutosaveStatus } from '@/components/ui/autosave-status';
import FormBuilderBannerBlock from './FormBuilderBannerBlock.vue';
import type { IFormBannerState } from './formBanner';
import type { TAutosaveStatus } from '@/hooks/useAutosaveSync';
import { FORM_DESCRIPTION_MAX_LENGTH, FORM_TITLE_MAX_LENGTH } from '@/constants/formBuilderLimits';
import { joinAriaIds } from '@/lib/ariaIds';

const formTitle = defineModel<string>('formTitle', { required: true });
const formDescription = defineModel<string>('formDescription', { required: true });
const banner = defineModel<IFormBannerState>('banner', { required: true });

const props = withDefaults(
    defineProps<{
        bannerPreviewSrc?: string;
        /** Endpoint `POST {form}/images` untuk upload banner. */
        uploadUrl?: string;
        titleError?: string;
        descriptionError?: string;
        /** Status autosave halaman; `undefined` berarti indikator tidak ditampilkan. */
        autosaveStatus?: TAutosaveStatus;
    }>(),
    {
        bannerPreviewSrc: '',
        uploadUrl: '',
    }
);

const emit = defineEmits<{
    autosaveRetry: [];
}>();

const titleLength = computed<number>(() => formTitle.value.length);
const descriptionLength = computed<number>(() => formDescription.value.length);

const titleDescribedBy = computed<string | undefined>(() =>
    joinAriaIds('f-title-hint', props.titleError === undefined ? undefined : 'f-title-error')
);
const descriptionDescribedBy = computed<string | undefined>(() =>
    joinAriaIds('f-description-hint', props.descriptionError === undefined ? undefined : 'f-description-error')
);

/** `maxlength` tidak menahan nilai yang ditempel, jadi panjang dipotong sebelum masuk model. */
function capToMaxLength(value: string, max: number): string {
    return value.length > max ? value.slice(0, max) : value;
}

function onTitleInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    input.value = capToMaxLength(input.value, FORM_TITLE_MAX_LENGTH);
    formTitle.value = input.value;
}

function onDescriptionInput(event: Event): void {
    const textarea = event.target as HTMLTextAreaElement;
    textarea.value = capToMaxLength(textarea.value, FORM_DESCRIPTION_MAX_LENGTH);
    formDescription.value = textarea.value;
}
</script>

<template>
    <section
        data-testid="builder-header-settings"
        class="border-border bg-card rounded-2xl border shadow-sm"
        aria-label="Sampul dan judul form"
    >
        <div
            v-if="props.autosaveStatus !== undefined"
            data-testid="builder-header-autosave"
            class="flex min-h-6 items-center justify-end px-4 pt-3 sm:px-5"
        >
            <AutosaveStatus
                v-if="props.autosaveStatus !== 'idle'"
                :status="props.autosaveStatus"
                @retry="emit('autosaveRetry')"
            />
        </div>

        <div class="space-y-4 px-4 py-4 sm:px-5 sm:py-5">
            <FormBuilderBannerBlock
                v-model:banner="banner"
                :banner-preview-src="props.bannerPreviewSrc"
                :upload-url="props.uploadUrl"
            />

            <div class="flex flex-col gap-1.5">
                <div class="flex items-baseline justify-between gap-2">
                    <Label for="f-title" class="text-xs font-medium">
                        Judul form <span class="text-destructive" aria-hidden="true">*</span>
                    </Label>
                    <span
                        data-testid="form-title-counter"
                        class="text-muted-foreground text-[11px] font-medium tabular-nums"
                        :class="titleLength >= FORM_TITLE_MAX_LENGTH ? 'text-destructive' : ''"
                    >
                        {{ titleLength }}/{{ FORM_TITLE_MAX_LENGTH }}
                    </span>
                </div>
                <div class="border-border bg-background rounded-lg border px-3 py-2 shadow-sm sm:px-3.5">
                    <input
                        id="f-title"
                        :value="formTitle"
                        :maxlength="FORM_TITLE_MAX_LENGTH"
                        placeholder="Contoh: Pendaftaran Startup Pitch Night"
                        class="font-display text-foreground placeholder:text-muted-foreground/65 w-full border-0 bg-transparent p-0 text-sm leading-snug font-semibold tracking-tight focus:ring-0 focus:outline-none sm:text-base"
                        :aria-invalid="props.titleError !== undefined"
                        :aria-describedby="titleDescribedBy"
                        @input="onTitleInput"
                    />
                </div>
                <p id="f-title-hint" class="text-muted-foreground text-xs">
                    Maksimal {{ FORM_TITLE_MAX_LENGTH }} karakter.
                </p>
                <p v-if="props.titleError" id="f-title-error" class="text-destructive text-xs">
                    {{ props.titleError }}
                </p>
            </div>

            <div class="flex flex-col gap-1.5">
                <div class="flex items-baseline justify-between gap-2">
                    <Label for="f-description" class="text-xs font-medium">Deskripsi singkat</Label>
                    <span
                        data-testid="form-description-counter"
                        class="text-muted-foreground text-[11px] font-medium tabular-nums"
                        :class="descriptionLength >= FORM_DESCRIPTION_MAX_LENGTH ? 'text-destructive' : ''"
                    >
                        {{ descriptionLength }}/{{ FORM_DESCRIPTION_MAX_LENGTH }}
                    </span>
                </div>
                <div class="border-border bg-background rounded-lg border px-3 py-2 shadow-sm sm:px-3.5">
                    <textarea
                        id="f-description"
                        :value="formDescription"
                        :maxlength="FORM_DESCRIPTION_MAX_LENGTH"
                        rows="3"
                        placeholder="Satu atau dua kalimat tentang siapa yang cocok mengisi form ini."
                        class="text-foreground placeholder:text-muted-foreground/65 min-h-[3.25rem] w-full resize-none border-0 bg-transparent p-0 text-sm leading-relaxed focus:ring-0 focus:outline-none"
                        :aria-invalid="props.descriptionError !== undefined"
                        :aria-describedby="descriptionDescribedBy"
                        @input="onDescriptionInput"
                    ></textarea>
                </div>
                <p id="f-description-hint" class="text-muted-foreground text-xs">
                    Maksimal {{ FORM_DESCRIPTION_MAX_LENGTH }} karakter.
                </p>
                <p v-if="props.descriptionError" id="f-description-error" class="text-destructive text-xs">
                    {{ props.descriptionError }}
                </p>
            </div>
        </div>
    </section>
</template>
