<script setup lang="ts">
import { computed, ref, watch, type UnwrapNestedRefs } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Card } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent } from '@/components/ui/dialog'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import FormFillFieldSlotRows from '@/components/modules/dashboard/FormFillFieldSlotRows.vue'
import FormFillParticipantEmailsSection from '@/components/modules/dashboard/FormFillParticipantEmailsSection.vue'
import { Send } from 'lucide-vue-next'
import type { FormFillPageContext } from '@/utils/composables/useFormFillPage'

type FormSegment =
    | { type: 'linear'; fields: IFormField[] }
    | { type: 'bundleGroup'; fields: IFormField[] }

const props = defineProps<{
    fields: IFormField[]
    eventId: string
    ctx: UnwrapNestedRefs<FormFillPageContext>
}>()

const emit = defineEmits<{
    submit: []
}>()

const formSegments = computed((): FormSegment[] => {
    const list = props.fields
    const out: FormSegment[] = []
    let i = 0
    while (i < list.length) {
        if (props.ctx.isBundleDuplicatableField(list[i])) {
            const dup: IFormField[] = []
            while (i < list.length && props.ctx.isBundleDuplicatableField(list[i])) {
                dup.push(list[i])
                i++
            }
            out.push({ type: 'bundleGroup', fields: dup })
        } else {
            const linear: IFormField[] = []
            while (i < list.length && !props.ctx.isBundleDuplicatableField(list[i])) {
                linear.push(list[i])
                i++
            }
            out.push({ type: 'linear', fields: linear })
        }
    }
    return out
})

function slotStorageKey(
    ctx: UnwrapNestedRefs<FormFillPageContext>,
    field: IFormField,
    slot: { slotIndex: number | null },
): string {
    return ctx.answerKeyForSlot(field, slot.slotIndex)
}

function imageUploadFillReadyForField(
    ctx: UnwrapNestedRefs<FormFillPageContext>,
    field: IFormField,
    storageKey: string,
): boolean {
    return (
        ctx.builderType(field) === 'image_upload' &&
        !!ctx.answerForm[storageKey] &&
        !!ctx.filePreviewUrls[storageKey]
    )
}

const uploadLightboxOpen = ref(false)
const uploadLightboxSrc = ref<string | null>(null)
const uploadLightboxTitle = ref('')

function openUploadLightbox(src: string | undefined, title: string) {
    if (!src) return
    uploadLightboxSrc.value = src
    uploadLightboxTitle.value = title
    uploadLightboxOpen.value = true
}

watch(uploadLightboxOpen, (open) => {
    if (!open) {
        uploadLightboxSrc.value = null
        uploadLightboxTitle.value = ''
    }
})

const submitConfirmOpen = ref(false)

const submitConfirmDescription = computed(() => {
    const mode = props.ctx.registrationMode
    if (mode === 'team') {
        return 'Your answers will be sent. Invitations will be emailed to the team members you listed where required.'
    }
    if (mode === 'bundle') {
        return 'All bundled entries in this registration will be submitted together.'
    }
    return 'Please review your answers. After submitting, changes may not be possible.'
})

function requestSubmitConfirm() {
    if (props.ctx.isBlocked) return
    submitConfirmOpen.value = true
}

function confirmSubmit() {
    submitConfirmOpen.value = false
    emit('submit')
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="requestSubmitConfirm">
        <FormFillParticipantEmailsSection v-if="ctx.memberSlots > 0" :ctx="ctx" />

        <template v-for="(segment, segIdx) in formSegments" :key="'seg-' + segIdx">
            <template v-if="segment.type === 'linear'">
                <template v-for="field in segment.fields" :key="field.id">
                    <div
                        v-if="ctx.builderType(field) === 'heading'"
                        class="rounded-2xl border border-border bg-primary/8 px-5 py-4 shadow-xs"
                    >
                        <h2 class="font-display text-2xl font-bold tracking-[-0.025em] text-foreground">
                            {{ ctx.metadata(field).content || field.label }}
                        </h2>
                        <p v-if="field.description" class="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                            {{ field.description }}
                        </p>
                    </div>

                    <div
                        v-else-if="ctx.builderType(field) === 'paragraph'"
                        class="rounded-2xl border border-border bg-card px-5 py-4 text-sm leading-relaxed text-muted-foreground shadow-xs"
                    >
                        {{ ctx.metadata(field).content || field.description || field.label }}
                    </div>

                    <hr v-else-if="ctx.builderType(field) === 'divider'" class="app-divider my-2" />

                    <div v-else-if="ctx.builderType(field) === 'banner'" class="hidden" />

                    <Card v-else class="overflow-hidden">
                        <template v-for="slot in ctx.participationSlotsForField(field)" :key="`${field.id}-${slot.slotIndex ?? 'lead'}`">
                            <FormFillFieldSlotRows
                                :ctx="ctx"
                                :field="field"
                                :participation-slot="slot"
                                :storage-key="slotStorageKey(ctx, field, slot)"
                                variant="linear"
                                :stack-index="0"
                                :image-upload-fill-ready-fn="(k) => imageUploadFillReadyForField(ctx, field, k)"
                                @open-lightbox="
                                    (src, title) => {
                                        openUploadLightbox(src, title)
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
                        v-if="slot.title"
                        class="border-b border-border bg-muted/20 px-5 py-2 sm:px-6"
                    >
                        <p class="text-sm font-semibold text-foreground">{{ slot.title }}</p>
                    </div>
                    <FormFillFieldSlotRows
                        v-for="(field, fi) in segment.fields"
                        :key="field.id + '-' + (slot.slotIndex ?? 'lead')"
                        :ctx="ctx"
                        :field="field"
                        :participation-slot="slot"
                        :storage-key="slotStorageKey(ctx, field, slot)"
                        variant="bundleParticipant"
                        :stack-index="fi"
                        :image-upload-fill-ready-fn="(k) => imageUploadFillReadyForField(ctx, field, k)"
                        @open-lightbox="
                            (src, title) => {
                                openUploadLightbox(src, title)
                            }
                        "
                    />
                </Card>
            </template>
        </template>

        <div class="mb-20 flex items-center justify-end gap-3 border-t border-border pt-6">
            <Button variant="ghost" size="lg" as-child>
                <Link :href="`/user/dashboard/events/${eventId}`">Cancel</Link>
            </Button>
            <Button type="submit" size="lg" :disabled="ctx.answerForm.processing" class="gap-2">
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
                <div v-if="uploadLightboxSrc" class="flex flex-col items-center gap-3 px-1 pb-1 pt-1">
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
