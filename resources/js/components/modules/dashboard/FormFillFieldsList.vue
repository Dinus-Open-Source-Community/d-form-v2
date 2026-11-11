<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import FormFillFieldSlotRows from '@/components/modules/dashboard/FormFillFieldSlotRows.vue';
import FormParagraphContent from '@/components/core/FormParagraphContent.vue';
import FormFillParticipantEmailsSection from '@/components/modules/dashboard/FormFillParticipantEmailsSection.vue';
import { formFieldImageSrc, formFieldMetadataText } from '@/lib/formFillFields';
import { useFormFillFieldsList } from '@/hooks/useFormFillFieldsList';
import { Send } from 'lucide-vue-next';
import type { UnwrapNestedRefs } from 'vue';
import type { TFormFillPageContext } from '@/hooks/useFormFillPage';
import { routes } from '@/lib/routes';
import type { IFormBuilderSection } from '@/types/form-builder';

const props = defineProps<{
    fields: IFormField[];
    sections: IFormBuilderSection[];
    eventId: string;
    ctx: UnwrapNestedRefs<TFormFillPageContext>;
}>();

const emit = defineEmits<{
    submit: [];
}>();

const {
    currentSectionIndex,
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
    validateSection: props.ctx.validateSection,
    isBundleDuplicatable: props.ctx.isBundleDuplicatableField,
    isBlocked: () => props.ctx.isBlocked,
});

function confirmSubmit(): void {
    closeSubmitConfirm();
    emit('submit');
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="requestSubmitConfirm">
        <FormFillParticipantEmailsSection v-if="ctx.memberSlots > 0 && currentSectionIndex === 0" :ctx="ctx" />

        <div class="border-border/70 bg-muted/20 flex items-center justify-between rounded-xl border px-4 py-3">
            <div>
                <p class="text-foreground text-xs font-semibold">
                    {{ props.sections[currentSectionIndex]?.title }}
                </p>
                <p v-if="props.sections.length > 1" class="text-muted-foreground text-xs">
                    Bagian {{ currentSectionIndex + 1 }} dari {{ props.sections.length }}
                </p>
            </div>
        </div>

        <template v-for="(segment, segIdx) in formSegments" :key="'seg-' + segIdx">
            <template v-if="segment.type === 'linear'">
                <template v-for="field in segment.fields" :key="field.id">
                    <div
                        v-if="ctx.builderType(field) === 'heading'"
                        class="border-border bg-primary/8 rounded-2xl border px-5 py-4 shadow-xs"
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
                        <img
                            v-if="formFieldImageSrc(field)"
                            :src="formFieldImageSrc(field)"
                            :alt="
                                formFieldMetadataText(field, 'alt') ||
                                formFieldMetadataText(field, 'caption') ||
                                field.label
                            "
                            class="w-full object-cover"
                        />
                        <p
                            v-if="formFieldMetadataText(field, 'caption').trim()"
                            class="border-border text-muted-foreground border-t px-5 py-3 text-sm leading-relaxed"
                        >
                            {{ formFieldMetadataText(field, 'caption') }}
                        </p>
                    </div>

                    <Card v-else class="overflow-hidden">
                        <div
                            v-if="ctx.cardErrorsForFields([field]).length"
                            class="border-destructive/20 bg-destructive/5 border-b px-5 py-3 sm:px-6"
                        >
                            <p
                                v-for="(message, errIdx) in ctx.cardErrorsForFields([field])"
                                :key="`linear-err-${field.id}-${errIdx}`"
                                class="text-destructive text-xs font-medium"
                            >
                                {{ message }}
                            </p>
                        </div>
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
                    </Card>
                </template>
            </template>

            <template v-else>
                <Card
                    v-for="slot in ctx.participationSlotsForField(segment.fields[0])"
                    :key="'bundle-' + (slot.slotIndex ?? 'lead') + '-' + segIdx"
                    class="overflow-hidden"
                >
                    <div
                        v-if="ctx.cardErrorsForBundleSlot(segment.fields, slot.slotIndex).length"
                        class="border-destructive/20 bg-destructive/5 border-b px-5 py-3 sm:px-6"
                    >
                        <p
                            v-for="(message, errIdx) in ctx.cardErrorsForBundleSlot(segment.fields, slot.slotIndex)"
                            :key="`bundle-err-${segIdx}-${slot.slotIndex ?? 'lead'}-${errIdx}`"
                            class="text-destructive text-xs font-medium"
                        >
                            {{ message }}
                        </p>
                    </div>
                    <div v-if="slot.title" class="border-border bg-muted/20 border-b px-5 py-2 sm:px-6">
                        <p class="text-foreground text-sm font-semibold">{{ slot.title }}</p>
                    </div>
                    <FormFillFieldSlotRows
                        v-for="(field, fi) in segment.fields"
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
