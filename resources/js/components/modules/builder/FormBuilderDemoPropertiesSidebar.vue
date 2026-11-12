<script setup lang="ts">
import FieldEditor from '@/components/modules/builder/FieldEditor.vue';
import LocalLottie from '@/components/core/LocalLottie.vue';
import type { IBuilderField } from '@/types/form-builder';

defineProps<{
    selectedField: IFormField | null;
}>();

const emit = defineEmits<{
    updateField: [field: IFormField];
}>();

function editorField(field: IFormField): IBuilderField {
    return field as unknown as IBuilderField;
}

function onEditorUpdate(f: IBuilderField): void {
    emit('updateField', f as unknown as IFormField);
}
</script>

<template>
    <aside class="border-border bg-card hidden w-[300px] shrink-0 flex-col border-l lg:flex">
        <div class="border-border border-b px-4 pt-4 pb-3">
            <h2 class="font-display text-foreground text-sm font-semibold tracking-[-0.01em]">Properties</h2>
            <p class="text-muted-foreground mt-0.5 text-[10px]">
                {{ selectedField ? 'Edit the selected field' : 'Select a field to edit' }}
            </p>
        </div>
        <div class="flex-1 overflow-y-auto px-4 py-4">
            <FieldEditor v-if="selectedField" :field="editorField(selectedField)" @update:field="onEditorUpdate" />
            <div v-else class="flex h-full flex-col items-center justify-center text-center">
                <div class="border-border bg-muted/30 rounded-2xl border border-dashed p-6">
                    <LocalLottie name="fieldSelected" :height="100" :width="100" />
                </div>
                <p class="text-foreground mt-4 text-xs font-semibold">No field selected</p>
                <p class="text-muted-foreground mt-1 max-w-[200px] text-[10px] leading-relaxed">
                    Select a field in the form canvas to edit its properties here.
                </p>
            </div>
        </div>
    </aside>
</template>
