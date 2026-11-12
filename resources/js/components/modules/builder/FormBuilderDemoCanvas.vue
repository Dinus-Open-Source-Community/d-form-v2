<script setup lang="ts">
import { computed } from 'vue';
import FieldRenderer from '@/components/modules/builder/FieldRenderer.vue';
import { Button } from '@/components/ui/button';
import LocalLottie from '@/components/core/LocalLottie.vue';
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select';
import { Eye, Save, Smartphone, Plus, GripVertical } from 'lucide-vue-next';
import type { IFormBuilderPaletteField } from '@/components/modules/builder/formBuilderPalette';
import type { IBuilderField } from '@/types/form-builder';

const formTitle = defineModel<string>('formTitle', { required: true });
const formDescription = defineModel<string>('formDescription', { required: true });
const mobileFieldType = defineModel<string>('mobileFieldPick', { required: true });

const props = defineProps<{
    fieldCount: number;
    formBannerCaption: string;
    bannerPreviewSrc: string;
    isEmpty: boolean;
    isDraggingOverCanvas: boolean;
    formFields: IFormField[];
    selectedFieldId: string | null;
    dropIndicatorIndex: number;
    dragSourceId: string | null;
    allFieldTypes: readonly IFormBuilderPaletteField[];
    addFieldDisabled: boolean;
}>();

function previewField(f: IFormField): IBuilderField {
    return f as unknown as IBuilderField;
}

const mobileFieldTypeOptions = computed<SimpleSelectOption[]>(() => [
    { value: '', label: 'Choose field type' },
    ...props.allFieldTypes.map((fieldType) => ({ value: fieldType.type, label: fieldType.label })),
]);

defineEmits<{
    addFromPicker: [];
    canvasDragOver: [e: DragEvent];
    canvasDragLeave: [e: DragEvent];
    canvasDrop: [e: DragEvent];
    gapDragEnter: [index: number];
    canvasDragStart: [e: DragEvent, field: IFormField, index: number];
    dragEnd: [];
    selectField: [id: string];
    deleteField: [id: string];
    duplicateField: [id: string];
}>();
</script>

<template>
    <main class="bg-background relative flex-1 overflow-y-auto">
        <div
            class="border-border bg-card/85 sticky top-0 z-10 flex items-center justify-between border-b px-6 py-2 backdrop-blur-xl"
        >
            <div class="flex items-center gap-2">
                <Smartphone class="text-muted-foreground size-4" />
                <span class="text-muted-foreground text-xs font-semibold">Mobile Preview</span>
                <span
                    class="border-primary/15 bg-primary/10 text-primary rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                >
                    {{ fieldCount }} field{{ fieldCount !== 1 ? 's' : '' }}
                </span>
            </div>
            <div class="flex items-center gap-1.5">
                <Button variant="outline" size="sm" class="h-8 text-xs" disabled>
                    <Eye class="mr-1 size-3.5" />Preview
                </Button>
                <Button size="sm" class="h-8 text-xs" disabled> <Save class="mr-1 size-3.5" />Save </Button>
            </div>
        </div>
        <div class="border-border bg-muted/30 border-b px-4 py-3 lg:hidden">
            <label class="text-muted-foreground mb-1.5 block text-[10px] font-semibold tracking-[0.14em] uppercase"
                >Add field on mobile</label
            >
            <div class="flex gap-2">
                <SimpleSelect
                    v-model="mobileFieldType"
                    :options="mobileFieldTypeOptions"
                    id="mobile-field-type-picker"
                    class="border-border/80 bg-background/80 h-10 min-w-0 flex-1 text-xs sm:text-sm"
                    aria-label="Add field type"
                />
                <Button
                    size="sm"
                    class="h-10 shrink-0 text-xs"
                    :disabled="addFieldDisabled"
                    @click="$emit('addFromPicker')"
                >
                    <Plus class="mr-1 size-3.5" />Add
                </Button>
            </div>
        </div>

        <div class="flex justify-center px-6 py-8">
            <div class="w-full max-w-[420px]">
                <div class="border-border bg-card overflow-hidden rounded-2xl border shadow-sm">
                    <div v-if="bannerPreviewSrc || formBannerCaption.trim()" class="border-border bg-muted/30 border-b">
                        <img
                            v-if="bannerPreviewSrc"
                            :src="bannerPreviewSrc"
                            alt=""
                            class="aspect-[3/1] w-full object-cover"
                        />
                        <p
                            v-if="formBannerCaption.trim()"
                            class="text-muted-foreground px-5 py-3 text-[12px] leading-relaxed"
                        >
                            {{ formBannerCaption }}
                        </p>
                    </div>
                    <div
                        class="border-border from-primary/5 to-primary/0 border-b bg-gradient-to-br via-transparent px-5 pt-6 pb-5"
                    >
                        <input
                            v-model="formTitle"
                            class="font-display text-foreground placeholder:text-muted-foreground/50 w-full bg-transparent text-xl font-bold tracking-[-0.02em] focus:outline-none"
                            placeholder="Form Title"
                        />
                        <textarea
                            v-model="formDescription"
                            rows="2"
                            class="text-muted-foreground placeholder:text-muted-foreground/50 mt-2 w-full resize-none bg-transparent text-[13px] leading-relaxed focus:outline-none"
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
                        <div
                            v-if="isEmpty && !isDraggingOverCanvas"
                            class="flex flex-col items-center py-6 text-center"
                        >
                            <div class="border-border bg-muted/30 mb-4 rounded-2xl border border-dashed px-8 py-8">
                                <LocalLottie name="builderEmpty" :height="140" :width="140" />
                            </div>
                            <h3 class="font-display text-foreground text-base font-bold tracking-[-0.01em]">
                                Start building your form
                            </h3>
                            <p class="text-muted-foreground mt-1.5 max-w-[260px] text-xs leading-relaxed">
                                Drag components from the left panel and drop them here. You can rearrange them anytime.
                            </p>
                            <div
                                class="border-border bg-muted/40 mt-4 flex items-center gap-2 rounded-full border px-3 py-1.5"
                            >
                                <GripVertical class="text-muted-foreground/70 size-3.5" />
                                <span class="text-muted-foreground text-[10px] font-medium">
                                    Tip: Drag the handle to reorder fields
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="isEmpty && isDraggingOverCanvas"
                            class="border-primary/40 bg-primary/[0.05] flex min-h-[200px] flex-col items-center justify-center rounded-xl border border-dashed transition-colors"
                        >
                            <div class="bg-primary/10 grid size-10 place-items-center rounded-full">
                                <Plus class="text-primary size-5" />
                            </div>
                            <p class="text-primary mt-2 text-xs font-semibold">Drop here to add</p>
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
                                        :class="
                                            dropIndicatorIndex === index ? 'bg-primary shadow-sm' : 'bg-transparent'
                                        "
                                    ></div>
                                </div>
                                <div class="group relative" :class="[dragSourceId === field.id ? 'opacity-30' : '']">
                                    <div
                                        class="absolute top-1/2 -left-1 z-10 -translate-x-full -translate-y-1/2 cursor-grab opacity-0 transition-opacity group-hover:opacity-100 active:cursor-grabbing"
                                        draggable="true"
                                        @dragstart="$emit('canvasDragStart', $event, field, index)"
                                        @dragend="$emit('dragEnd')"
                                    >
                                        <div
                                            class="border-border bg-card text-muted-foreground hover:border-primary/30 hover:text-foreground grid size-7 place-items-center rounded-full border shadow-xs transition-colors"
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
                <p class="text-muted-foreground/70 mt-4 text-center text-[10px]">
                    Form preview · Mobile size (420px). On mobile, use the add-field dropdown above.
                </p>
            </div>
        </div>
    </main>
</template>
