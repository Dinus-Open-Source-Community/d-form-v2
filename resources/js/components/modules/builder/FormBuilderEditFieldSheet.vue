<script setup lang="ts">
import FieldEditor from '@/components/modules/builder/FieldEditor.vue'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/components/ui/sheet'
import type { BuilderField } from '@/types/form-builder'
import { computed } from 'vue'

const open = defineModel<boolean>('open', { required: true })

const props = withDefaults(
    defineProps<{
        field: BuilderField | null
        /** Endpoint `POST {form}/images` untuk upload gambar blok/opsi. */
        uploadUrl?: string
    }>(),
    { uploadUrl: '' },
)

defineEmits<{
    updateField: [field: BuilderField]
    done: []
}>()

const isChoiceType = computed(() => props.field != null && ['dropdown', 'checkbox', 'radio'].includes(props.field.type))
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="flex w-full flex-col p-0 sm:max-w-md">
            <SheetHeader class="border-border bg-muted/30 shrink-0 border-b p-5 text-left">
                <SheetTitle class="font-display tracking-[-0.01em]">
                    {{ isChoiceType ? 'Kelola opsi' : 'Pengaturan field' }}
                </SheetTitle>
                <SheetDescription>Customize the selected field.</SheetDescription>
            </SheetHeader>
            <div class="bg-background flex-1 overflow-y-auto px-5 py-5">
                <FieldEditor v-if="field" :field="field" :upload-url="uploadUrl" @update:field="$emit('updateField', $event)" />
            </div>
            <div class="border-border bg-muted/30 shrink-0 border-t p-4">
                <Button class="h-11 w-full" @click="$emit('done')">Done</Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
