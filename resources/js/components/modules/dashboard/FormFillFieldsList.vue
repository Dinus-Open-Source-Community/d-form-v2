<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import FormFillFieldSlotRows from '@/components/modules/dashboard/FormFillFieldSlotRows.vue';
import FormFillReferralField from '@/components/modules/dashboard/FormFillReferralField.vue';
import FormPriceBlock from '@/components/core/FormPriceBlock.vue';
import FormParagraphContent from '@/components/core/FormParagraphContent.vue';
import FormFillParticipantEmailsSection from '@/components/modules/dashboard/FormFillParticipantEmailsSection.vue';
import { formFieldImageSrc, formFieldMetadataText } from '@/lib/formFillFields';
import { formFieldPrice } from '@/lib/formPrice';
import { referralInsertIndexWithinSection } from '@/lib/referralPlacement';
import { useFormFillFieldsList } from '@/hooks/useFormFillFieldsList';
import type { TReferralCheckStatus } from '@/hooks/useReferralCodeCheck';
import { Send } from 'lucide-vue-next';
import { computed, type UnwrapNestedRefs } from 'vue';
import type { TFormFillPageContext } from '@/hooks/useFormFillPage';
import { routes } from '@/lib/routes';
import type { IFormBuilderSection } from '@/types/form-builder';
import type { IFormPriceQuote, TFormFillSegment } from '@/types/form';

const props = defineProps<{
    fields: IFormField[];
    sections: IFormBuilderSection[];
    eventId: string;
    ctx: UnwrapNestedRefs<TFormFillPageContext>;
    referralPosition: string;
    referralStatus: TReferralCheckStatus;
    referralMessage: string;
    referralError: string;
    referralCode: string;
    referralPrice: IFormPriceQuote | null;
}>();

const emit = defineEmits<{
    submit: [];
    'update:referralCode': [value: string];
}>();

const {
    currentSectionIndex,
    currentSectionFields,
    formSegments,
    previousSection,
    nextSection,
    storageKeyFor,
    imageUploadFillReady,
    uploadLightboxOpen,
    uploadLightboxSrc,
    uploadLightboxTitle,
    openUploadLightbox,
    submitConfirmOpen,
    submitConfirmDescription,
    requestSubmitConfirm,
    closeSubmitConfirm,
} = useFormFillFieldsList({
    fields: () => props.fields,
    sections: () => props.sections,
    registrationMode: props.ctx.registrationMode,
    answerForm: props.ctx.answerForm,
    filePreviewUrls: props.ctx.filePreviewUrls,
    multiFilePreviewUrls: props.ctx.multiFilePreviewUrls,
    validateSection: props.ctx.validateSection,
    isBundleDuplicatable: props.ctx.isBundleDuplicatableField,
    isBlocked: () => props.ctx.isBlocked,
});

function confirmSubmit(): void {
    closeSubmitConfirm();
    emit('submit');
}

/** Kunci stabil per kartu jawaban supaya pesan error kartu dihitung sekali per render. */
function linearErrorKey(field: IFormField): string {
    return `linear:${field.id}`;
}

/** Harga field dalam rupiah; 0 berarti field ini bukan blok harga. */
function fieldPrice(field: IFormField): number {
    return formFieldPrice(props.ctx.metadata(field));
}

function bundleErrorKey(field: IFormField, slotIndex: number | null): string {
    return `bundle:${field.id}:${slotIndex ?? 'lead'}`;
}

const cardErrorMessages = computed<Record<string, string[]>>(() => {
    const messages: Record<string, string[]> = {};
    for (const segment of formSegments.value) {
        if (segment.type === 'linear') {
            for (const field of segment.fields) {
                messages[linearErrorKey(field)] = props.ctx.cardErrorsForFields([field]);
            }
            continue;
        }

        const leadField = segment.fields[0];
        if (leadField === undefined) continue;
        for (const slot of props.ctx.participationSlotsForField(leadField)) {
            messages[bundleErrorKey(leadField, slot.slotIndex)] = props.ctx.cardErrorsForBundleSlot(
                segment.fields,
                slot.slotIndex
            );
        }
    }
    return messages;
});

/** Pesan error untuk satu kartu; array kosong saat kartu valid. */
function messagesForCard(key: string): string[] {
    return cardErrorMessages.value[key] ?? [];
}

/** Satu kartu hanya menampilkan satu pesan: pesan pertama yang ditemukan. */
function firstMessageForCard(key: string): string {
    return messagesForCard(key)[0] ?? '';
}

function cardHasErrors(key: string): boolean {
    return messagesForCard(key).length > 0;
}

/** Satu item render: segmen field seperti biasa, atau kartu referral di posisinya. */
type TFillListRenderItem = { type: 'segment'; segment: TFormFillSegment } | { type: 'referral' };

/**
 * Indeks sisip referral pada daftar field bagian ini. Indeks yang jatuh di tengah
 * bundle digeser ke akhir bundle agar kartu tidak memotong satu grup peserta.
 */
const referralInsertIndex = computed<number | null>(() => {
    if (!props.ctx.referralEnabled) return null;

    const rawIndex = referralInsertIndexWithinSection(props.fields, currentSectionFields.value, props.referralPosition);
    if (rawIndex === null) return null;

    let offset = 0;
    for (const segment of formSegments.value) {
        const start = offset;
        offset += segment.fields.length;
        if (segment.type === 'bundleGroup' && rawIndex > start && rawIndex < offset) return offset;
    }

    return rawIndex;
});

const renderItems = computed<TFillListRenderItem[]>(() => {
    const insertIndex = referralInsertIndex.value;
    const items: TFillListRenderItem[] = [];
    let offset = 0;

    for (const segment of formSegments.value) {
        const start = offset;
        const end = start + segment.fields.length;
        offset = end;

        if (insertIndex === start) items.push({ type: 'referral' });

        if (insertIndex !== null && segment.type === 'linear' && insertIndex > start && insertIndex < end) {
            const cutIndex = insertIndex - start;
            items.push({ type: 'segment', segment: { type: 'linear', fields: segment.fields.slice(0, cutIndex) } });
            items.push({ type: 'referral' });
            items.push({ type: 'segment', segment: { type: 'linear', fields: segment.fields.slice(cutIndex) } });
            continue;
        }

        items.push({ type: 'segment', segment });
    }

    if (insertIndex !== null && insertIndex === offset) items.push({ type: 'referral' });

    return items;
});
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="requestSubmitConfirm">
        <FormFillParticipantEmailsSection v-if="ctx.memberSlots > 0 && currentSectionIndex === 0" :ctx="ctx" />

        <div
            v-if="props.sections.length > 1"
            class="border-border bg-card flex items-center justify-between rounded-xl border px-4 py-3"
        >
            <div>
                <p class="text-foreground text-xs font-semibold">
                    {{ props.sections[currentSectionIndex]?.title }}
                </p>
                <p v-if="props.sections.length > 1" class="text-muted-foreground text-xs">
                    Bagian {{ currentSectionIndex + 1 }} dari {{ props.sections.length }}
                </p>
            </div>
        </div>

        <template v-for="(item, itemIdx) in renderItems" :key="'item-' + itemIdx">
            <div v-if="item.type === 'referral'" class="border-border bg-card rounded-2xl border p-5 shadow-xs">
                <FormFillReferralField
                    :model-value="props.referralCode"
                    :error="props.referralError"
                    :status="props.referralStatus"
                    :status-message="props.referralMessage"
                    @update:model-value="(value: string | number) => emit('update:referralCode', String(value))"
                />
            </div>

            <template v-else-if="item.segment.type === 'linear'">
                <template v-for="field in item.segment.fields" :key="field.id">
                    <FormPriceBlock
                        v-if="ctx.builderType(field) === 'price' && fieldPrice(field) > 0"
                        :label="field.label"
                        :base="fieldPrice(field)"
                        :quote="props.referralPrice"
                    />

                    <div v-else-if="ctx.builderType(field) === 'price'" class="hidden" />

                    <div
                        v-else-if="ctx.builderType(field) === 'heading'"
                        class="border-border bg-card rounded-2xl border px-5 py-4 shadow-xs"
                    >
                        <h2 class="font-display text-foreground text-2xl font-bold tracking-[-0.025em]">
                            {{ ctx.metadata(field).content || field.label }}
                        </h2>
                        <p v-if="field.description" class="text-muted-foreground mt-1.5 text-sm leading-relaxed">
                            {{ field.description }}
                        </p>
                    </div>

                    <div
                        v-else-if="ctx.builderType(field) === 'paragraph'"
                        class="border-border bg-card rounded-2xl border px-5 py-4 shadow-xs"
                    >
                        <FormParagraphContent
                            :content="String(ctx.metadata(field).content ?? '')"
                            :fallback="field.description || field.label"
                        />
                    </div>

                    <hr v-else-if="ctx.builderType(field) === 'divider'" class="app-divider my-2" />

                    <div v-else-if="ctx.builderType(field) === 'banner'" class="hidden" />

                    <div
                        v-else-if="ctx.builderType(field) === 'image'"
                        class="border-border bg-card overflow-hidden rounded-2xl border shadow-xs"
                    >
                        <div
                            v-if="field.label || field.description"
                            class="space-y-1 px-5 pt-4"
                            :class="fillImageSrc(field) || fillImageText(field, 'caption').trim() ? 'pb-3' : 'pb-4'"
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
                            v-if="formFieldImageSrc(field)"
                            :src="formFieldImageSrc(field)"
                            :alt="
                                formFieldMetadataText(field, 'alt') ||
                                formFieldMetadataText(field, 'caption') ||
                                field.label
                            "
                            class="w-full object-cover"
                            :class="(field.label || field.description) ? 'border-t border-border' : ''"
                        />
                        <p
                            v-if="formFieldMetadataText(field, 'caption').trim()"
                            class="border-border text-muted-foreground border-t px-5 py-3 text-sm leading-relaxed"
                        >
                            {{ formFieldMetadataText(field, 'caption') }}
                        </p>
                    </div>

                    <Card
                        v-else
                        class="gap-0 overflow-hidden py-0"
                        :class="
                            cardHasErrors(linearErrorKey(field))
                                ? 'border-destructive/60 bg-destructive/[0.03]'
                                : undefined
                        "
                        :data-field-error-card="cardHasErrors(linearErrorKey(field)) ? '' : undefined"
                        :tabindex="cardHasErrors(linearErrorKey(field)) ? -1 : undefined"
                    >
                        <template
                            v-for="slot in ctx.participationSlotsForField(field)"
                            :key="`${field.id}-${slot.slotIndex ?? 'lead'}`"
                        >
                            <FormFillFieldSlotRows
                                :ctx="ctx"
                                :field="field"
                                :participation-slot="slot"
                                :storage-key="storageKeyFor(field, slot)"
                                variant="linear"
                                :stack-index="0"
                                :image-upload-fill-ready-fn="(k) => imageUploadFillReady(field, k)"
                                @open-lightbox="
                                    (src, title) => {
                                        openUploadLightbox(src, title);
                                    }
                                "
                            />
                        </template>
                        <p
                            v-if="cardHasErrors(linearErrorKey(field))"
                            class="text-destructive mt-1.5 px-6 text-xs font-medium"
                        >
                            {{ firstMessageForCard(linearErrorKey(field)) }}
                        </p>
                    </Card>
                </template>
            </template>

            <template v-else>
                <Card
                    v-for="slot in ctx.participationSlotsForField(item.segment.fields[0])"
                    :key="'bundle-' + (slot.slotIndex ?? 'lead') + '-' + itemIdx"
                    class="gap-0 overflow-hidden py-0"
                    :class="
                        cardHasErrors(bundleErrorKey(item.segment.fields[0], slot.slotIndex))
                            ? 'border-destructive/60 bg-destructive/[0.03]'
                            : undefined
                    "
                    :data-field-error-card="
                        cardHasErrors(bundleErrorKey(item.segment.fields[0], slot.slotIndex)) ? '' : undefined
                    "
                    :tabindex="cardHasErrors(bundleErrorKey(item.segment.fields[0], slot.slotIndex)) ? -1 : undefined"
                >
                    <div v-if="slot.title" class="border-border bg-muted/20 border-b px-5 py-2 sm:px-6">
                        <p class="text-foreground text-sm font-semibold">{{ slot.title }}</p>
                    </div>
                    <FormFillFieldSlotRows
                        v-for="(field, fi) in item.segment.fields"
                        :key="field.id + '-' + (slot.slotIndex ?? 'lead')"
                        :ctx="ctx"
                        :field="field"
                        :participation-slot="slot"
                        :storage-key="storageKeyFor(field, slot)"
                        variant="bundleParticipant"
                        :stack-index="fi"
                        :image-upload-fill-ready-fn="(k) => imageUploadFillReady(field, k)"
                        @open-lightbox="
                            (src, title) => {
                                openUploadLightbox(src, title);
                            }
                        "
                    />
                    <p
                        v-if="cardHasErrors(bundleErrorKey(item.segment.fields[0], slot.slotIndex))"
                        class="text-destructive mt-1.5 px-6 text-xs font-medium"
                    >
                        {{ firstMessageForCard(bundleErrorKey(item.segment.fields[0], slot.slotIndex)) }}
                    </p>
                </Card>
            </template>
        </template>

        <div class="border-border mb-20 flex items-center justify-between gap-3 border-t pt-6">
            <Button v-if="currentSectionIndex > 0" type="button" variant="outline" size="lg" @click="previousSection">
                Sebelumnya
            </Button>
            <Button v-else variant="ghost" size="lg" as-child>
                <Link :href="routes.member.event.show(eventId)">Cancel</Link>
            </Button>
            <Button
                v-if="currentSectionIndex < props.sections.length - 1"
                type="button"
                size="lg"
                class="gap-2"
                @click="nextSection"
            >
                Berikutnya
            </Button>
            <Button v-else type="submit" size="lg" :disabled="ctx.answerForm.processing" class="gap-2">
                <Send class="size-4" />
                Submit registration
            </Button>
        </div>

        <Dialog v-model:open="uploadLightboxOpen">
            <DialogContent
                :show-close-button="false"
                overlay-class="bg-black/45 backdrop-blur-sm"
                class="w-auto max-w-[min(calc(100vw-2rem),96rem)] gap-0 rounded-none border-0 bg-transparent p-0 shadow-none outline-none sm:max-w-[min(calc(100vw-2rem),96rem)]"
            >
                <div v-if="uploadLightboxSrc" class="flex flex-col items-center gap-3 px-1 pt-1 pb-1">
                    <img
                        :src="uploadLightboxSrc"
                        :alt="uploadLightboxTitle"
                        class="max-h-[min(85vh,calc(100dvh-4rem))] w-auto max-w-[min(calc(100vw-3rem),96rem)] object-contain"
                    />
                    <p class="max-w-[min(calc(100vw-3rem),96rem)] truncate text-center text-sm font-medium text-white">
                        {{ uploadLightboxTitle }}
                    </p>
                </div>
            </DialogContent>
        </Dialog>

        <ConfirmationModal
            v-model:open="submitConfirmOpen"
            title="Submit this registration?"
            :description="submitConfirmDescription"
            confirm-text="Submit"
            :loading="ctx.answerForm.processing"
            @confirm="confirmSubmit"
        />
    </form>
</template>
