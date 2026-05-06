<script setup lang="ts">
import FieldRenderer from '@/components/modules/builder/FieldRenderer.vue'
import { Button } from '@/components/ui/button'
import LocalLottie from '@/components/core/LocalLottie.vue'
import { Eye, Save, Smartphone, Plus, GripVertical } from 'lucide-vue-next'
import type { FormBuilderPaletteField } from '@/components/modules/builder/formBuilderPalette'
import type { BuilderField } from '@/types/form-builder'

const formTitle = defineModel<string>('formTitle', { required: true })
const formDescription = defineModel<string>('formDescription', { required: true })
const mobileFieldType = defineModel<string>('mobileFieldPick', { required: true })

defineProps<{
    fieldCount: number
    formBannerCaption: string
    bannerPreviewSrc: string
    isEmpty: boolean
    isDraggingOverCanvas: boolean
    formFields: IFormField[]
    selectedFieldId: string | null
    dropIndicatorIndex: number
    dragSourceId: string | null
    allFieldTypes: readonly FormBuilderPaletteField[]
    addFieldDisabled: boolean
}>()

function previewField(f: IFormField): BuilderField {
    return f as unknown as BuilderField
}

defineEmits<{
    addFromPicker: []
    canvasDragOver: [e: DragEvent]
    canvasDragLeave: [e: DragEvent]
    canvasDrop: [e: DragEvent]
    gapDragEnter: [index: number]
    canvasDragStart: [e: DragEvent, field: IFormField, index: number]
    dragEnd: []
    selectField: [id: string]
    deleteField: [id: string]
    duplicateField: [id: string]
}>()
</script>

<template>
    <main class="relative flex-1 overflow-y-auto bg-background">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-border bg-card/85 px-6 py-2 backdrop-blur-xl">
            <div class="flex items-center gap-2">
                <Smartphone class="size-4 text-muted-foreground" />
                <span class="text-xs font-semibold text-muted-foreground">Mobile Preview</span>
                <span
                    class="rounded-full border border-primary/15 bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary"
                >
                    {{ fieldCount }} field{{ fieldCount !== 1 ? 's' : '' }}
                </span>
            </div>
            <div class="flex items-center gap-1.5">
                <Button variant="outline" size="sm" class="h-8 text-xs" disabled>
                    <Eye class="mr-1 size-3.5" />Preview
                </Button>
                <Button size="sm" class="h-8 text-xs" disabled>
                    <Save class="mr-1 size-3.5" />Save
                </Button>
            </div>
        </div>
        <div class="border-b border-border bg-muted/30 px-4 py-3 lg:hidden">
            <label class="mb-1.5 block text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                >Add field on mobile</label
            >
            <div class="flex gap-2">
                <select
                    v-model="mobileFieldType"
                    class="min-w-0 flex-1 rounded-lg border border-border bg-card px-3 py-2 text-xs font-medium text-foreground shadow-xs transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] focus:border-primary focus:outline-none focus:ring-3 focus:ring-primary/15"
                >
                    <option value="">Choose field type</option>
                    <option v-for="fieldType in allFieldTypes" :key="fieldType.type" :value="fieldType.type">
                        {{ fieldType.label }}
                    </option>
                </select>
                <Button size="sm" class="h-9 shrink-0 text-xs" :disabled="addFieldDisabled" @click="$emit('addFromPicker')">
                    <Plus class="mr-1 size-3.5" />Add
                </Button>
            </div>
        </div>

        <div class="flex justify-center px-6 py-8">
            <div class="w-full max-w-[420px]">
                <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                    <div v-if="bannerPreviewSrc || formBannerCaption.trim()" class="border-b border-border bg-muted/30">
                        <img v-if="bannerPreviewSrc" :src="bannerPreviewSrc" alt="" class="aspect-[3/1] w-full object-cover" />
                        <p
                            v-if="formBannerCaption.trim()"
                            class="px-5 py-3 text-[12px] leading-relaxed text-muted-foreground"
                        >
                            {{ formBannerCaption }}
                        </p>
                    </div>
                    <div
                        class="border-b border-border bg-gradient-to-br from-primary/5 via-transparent to-primary/0 px-5 pt-6 pb-5"
                    >
                        <input
                            v-model="formTitle"
                            class="w-full bg-transparent font-display text-xl font-bold tracking-[-0.02em] text-foreground placeholder:text-muted-foreground/50 focus:outline-none"
                            placeholder="Form Title"
                        />
                        <textarea
                            v-model="formDescription"
                            rows="2"
                            class="mt-2 w-full resize-none bg-transparent text-[13px] leading-relaxed text-muted-foreground placeholder:text-muted-foreground/50 focus:outline-none"
                            placeholder="Add a description to help users understand the form..."
                        ></textarea>
                    </div>

                    <div
                        class="min-h-[400px] px-4 py-4"
                        :class="[
                            isEmpty && !isDraggingOverCanvas ? 'flex items-center justify-center' : '',
                            isDraggingOverCanvas && isEmpty ? 'bg-primary/[0.03]' : '',
                        ]"
                        @dragover.prevent="$emit('canvasDragOver', $event)"
                        @dragleave="$emit('canvasDragLeave', $event)"
                        @drop="$emit('canvasDrop', $event)"
                    >
                        <div v-if="isEmpty && !isDraggingOverCanvas" class="flex flex-col items-center py-6 text-center">
                            <div class="mb-4 rounded-2xl border border-dashed border-border bg-muted/30 px-8 py-8">
                                <LocalLottie name="builderEmpty" :height="140" :width="140" />
                            </div>
                            <h3 class="font-display text-base font-bold tracking-[-0.01em] text-foreground">
                                Start building your form
                            </h3>
                            <p class="mt-1.5 max-w-[260px] text-xs leading-relaxed text-muted-foreground">
                                Drag components from the left panel and drop them here. You can rearrange them anytime.
                            </p>
                            <div
                                class="mt-4 flex items-center gap-2 rounded-full border border-border bg-muted/40 px-3 py-1.5"
                            >
                                <GripVertical class="size-3.5 text-muted-foreground/70" />
                                <span class="text-[10px] font-medium text-muted-foreground">
                                    Tip: Drag the handle to reorder fields
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="isEmpty && isDraggingOverCanvas"
                            class="flex min-h-[200px] flex-col items-center justify-center rounded-xl border border-dashed border-primary/40 bg-primary/[0.05] transition-colors"
                        >
                            <div class="grid size-10 place-items-center rounded-full bg-primary/10">
                                <Plus class="size-5 text-primary" />
                            </div>
                            <p class="mt-2 text-xs font-semibold text-primary">Drop here to add</p>
                        </div>

                        <div v-if="!isEmpty" class="flex flex-col">
                            <template v-for="(field, index) in formFields" :key="field.id">
                                <div
                                    class="relative py-0.5"
                                    @dragenter.prevent="$emit('gapDragEnter', index)"
                                    @dragover.prevent
                                >
                                    <div
                                        class="pointer-events-none h-0.5 rounded-full transition-all duration-200"
                                        :class="dropIndicatorIndex === index ? 'bg-primary shadow-sm' : 'bg-transparent'"
                                    ></div>
                                </div>
                                <div class="group relative" :class="[dragSourceId === field.id ? 'opacity-30' : '']">
                                    <div
                                        class="absolute -left-1 top-1/2 z-10 -translate-x-full -translate-y-1/2 cursor-grab opacity-0 transition-opacity active:cursor-grabbing group-hover:opacity-100"
                                        draggable="true"
                                        @dragstart="$emit('canvasDragStart', $event, field, index)"
                                        @dragend="$emit('dragEnd')"
                                    >
                                        <div
                                            class="grid size-7 place-items-center rounded-lg border border-border bg-card text-muted-foreground shadow-xs transition-colors hover:border-primary/30 hover:text-foreground"
                                        >
                                            <GripVertical class="size-4" />
                                        </div>
                                    </div>
                                    <FieldRenderer
                                        :field="previewField(field)"
                                        :is-selected="selectedFieldId === field.id"
                                        @select="$emit('selectField', field.id)"
                                        @delete="$emit('deleteField', field.id)"
                                        @duplicate="$emit('duplicateField', field.id)"
                                    />
                                </div>
                            </template>
                            <div
                                class="relative py-0.5"
                                @dragenter.prevent="$emit('gapDragEnter', formFields.length)"
                                @dragover.prevent
                            >
                                <div
                                    class="pointer-events-none h-0.5 rounded-full transition-all duration-200"
                                    :class="
                                        dropIndicatorIndex === formFields.length
                                            ? 'bg-primary shadow-sm'
                                            : 'bg-transparent'
                                    "
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="mt-4 text-center text-[10px] text-muted-foreground/70">
                    Form preview · Mobile size (420px). On mobile, use the add-field dropdown above.
                </p>
            </div>
        </div>
    </main>
</template>
