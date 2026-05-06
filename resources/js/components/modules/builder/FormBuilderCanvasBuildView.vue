<script setup lang="ts">
import FieldRenderer from '@/components/modules/builder/FieldRenderer.vue'
import LocalLottie from '@/components/core/LocalLottie.vue'
import { Button } from '@/components/ui/button'
import { GripVertical, Plus, Sparkles, ArrowUp, ArrowDown, Pencil, Trash2 } from 'lucide-vue-next'
import type { BuilderField } from '@/types/form-builder'

const formTitle = defineModel<string>('formTitle', { required: true })
const formDescription = defineModel<string>('formDescription', { required: true })

defineProps<{
    hideOnMobileSettings: boolean
    bannerPreviewSrc: string
    isEmpty: boolean
    isDraggingOverCanvas: boolean
    formFields: BuilderField[]
    selectedFieldId: string | null
    dropIndicatorIndex: number
    dragSourceId: string | null
}>()

defineEmits<{
    canvasDragOver: [e: DragEvent]
    canvasDragLeave: [e: DragEvent]
    canvasDrop: [e: DragEvent]
    gapDragEnter: [index: number]
    canvasDragStart: [e: DragEvent, field: BuilderField, index: number]
    dragEnd: []
    selectField: [id: string, isMobile?: boolean]
    deleteField: [id: string]
    duplicateField: [id: string]
    moveField: [id: string, dir: -1 | 1]
    openAddSheet: []
}>()
</script>

<template>
    <div
        :class="[
            'flex justify-center px-3 pt-6 pb-32 sm:px-6 lg:pb-10',
            hideOnMobileSettings && 'hidden lg:flex',
        ]"
    >
        <div class="w-full max-w-[440px]">
            <div
                v-if="isEmpty"
                class="border-border bg-muted/30 text-muted-foreground mb-4 hidden items-center gap-2.5 rounded-xl border border-dashed px-4 py-2.5 text-[11px] lg:flex"
            >
                <Sparkles class="text-primary size-3.5 shrink-0" />
                Drag any component from the left to start. You can reorder anytime.
            </div>

            <div class="border-border bg-card overflow-hidden rounded-2xl border shadow-sm">
                <div v-if="bannerPreviewSrc" class="border-border border-b">
                    <img :src="bannerPreviewSrc" alt="Form banner" class="aspect-[3/1] w-full object-cover" />
                </div>
                <div class="border-border from-primary/5 to-primary/0 border-b bg-gradient-to-br via-transparent px-5 pt-6 pb-5">
                    <input
                        v-model="formTitle"
                        placeholder="Untitled form"
                        class="font-display text-foreground placeholder:text-muted-foreground/60 w-full bg-transparent text-2xl font-bold tracking-[-0.025em] focus:outline-none"
                    />
                    <textarea
                        v-model="formDescription"
                        rows="2"
                        placeholder="Add a short description so registrants know what this form is for..."
                        class="text-muted-foreground placeholder:text-muted-foreground/60 mt-2 w-full resize-none bg-transparent text-sm leading-relaxed focus:outline-none"
                    ></textarea>
                </div>

                <div
                    class="min-h-[400px] space-y-1 p-4"
                    @dragover.prevent="$emit('canvasDragOver', $event)"
                    @dragleave="$emit('canvasDragLeave', $event)"
                    @drop="$emit('canvasDrop', $event)"
                >
                    <div
                        v-if="isEmpty && !isDraggingOverCanvas"
                        class="flex flex-col items-center justify-center py-16 text-center"
                    >
                        <LocalLottie name="builderEmpty" :height="120" :width="120" class="opacity-80" />
                        <p class="text-foreground mt-4 text-sm font-semibold">Your canvas is empty</p>
                        <p class="text-muted-foreground mt-1 max-w-[260px] text-xs leading-relaxed">
                            <span class="hidden lg:inline">Drag components from the left to add them.</span>
                            <span class="lg:hidden">Tap the button below to add your first field.</span>
                        </p>
                        <Button size="sm" class="mt-5 lg:hidden" @click="$emit('openAddSheet')">
                            <Plus class="mr-1.5 size-4" />
                            Add field
                        </Button>
                    </div>

                    <div
                        v-if="isEmpty && isDraggingOverCanvas"
                        class="border-primary/40 bg-primary/[0.05] hidden min-h-[200px] flex-col items-center justify-center rounded-xl border border-dashed transition-colors lg:flex"
                    >
                        <div class="bg-primary/10 grid size-10 place-items-center rounded-full">
                            <Plus class="text-primary size-5" />
                        </div>
                        <p class="text-primary mt-2 text-xs font-semibold">Drop here to add</p>
                    </div>

                    <template v-for="(field, index) in formFields" :key="field.id">
                        <div
                            class="relative hidden h-1 w-full lg:block"
                            @dragenter.prevent="$emit('gapDragEnter', index)"
                            @dragover.prevent
                        >
                            <div
                                class="pointer-events-none h-0.5 rounded-full transition-all duration-200"
                                :class="dropIndicatorIndex === index ? 'bg-primary' : 'bg-transparent'"
                            ></div>
                        </div>

                        <div class="group relative" :class="dragSourceId === field.id ? 'opacity-30' : ''">
                            <div class="absolute top-1/2 -left-10 hidden -translate-y-1/2 lg:block">
                                <div
                                    class="border-border bg-card text-muted-foreground hover:border-primary/30 hover:text-foreground grid size-7 cursor-grab place-items-center rounded-lg border shadow-xs transition-colors active:cursor-grabbing"
                                    draggable="true"
                                    @dragstart="$emit('canvasDragStart', $event, field, index)"
                                    @dragend="$emit('dragEnd')"
                                    aria-label="Drag to reorder"
                                >
                                    <GripVertical class="size-4" />
                                </div>
                            </div>

                            <FieldRenderer
                                :field="field"
                                :is-selected="selectedFieldId === field.id"
                                @select="$emit('selectField', field.id)"
                                @delete="$emit('deleteField', field.id)"
                                @duplicate="$emit('duplicateField', field.id)"
                            />

                            <div
                                class="border-border bg-muted/30 mt-1.5 flex items-center justify-between gap-1 rounded-lg border px-1.5 py-1 lg:hidden"
                            >
                                <div class="flex items-center gap-0.5">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        :disabled="index === 0"
                                        aria-label="Move field up"
                                        @click="$emit('moveField', field.id, -1)"
                                    >
                                        <ArrowUp class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        :disabled="index === formFields.length - 1"
                                        aria-label="Move field down"
                                        @click="$emit('moveField', field.id, 1)"
                                    >
                                        <ArrowDown class="size-4" />
                                    </Button>
                                </div>
                                <div class="flex items-center gap-0.5">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 gap-1 px-2 text-[11px] font-semibold"
                                        @click="$emit('selectField', field.id, true)"
                                    >
                                        <Pencil class="size-3.5" />
                                        Edit
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        aria-label="Delete field"
                                        @click="$emit('deleteField', field.id)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div
                        v-if="!isEmpty"
                        class="hidden h-4 w-full lg:block"
                        @dragenter.prevent="$emit('gapDragEnter', formFields.length)"
                    />
                </div>

                <div v-if="!isEmpty" class="border-border bg-muted/30 border-t p-3 lg:hidden">
                    <Button class="w-full gap-1.5" @click="$emit('openAddSheet')">
                        <Plus class="size-4" />
                        Add another field
                    </Button>
                </div>
            </div>

            <p class="text-muted-foreground/70 mt-4 hidden text-center text-[10px] lg:block">
                Live mobile preview · 420px wide
            </p>
        </div>
    </div>
</template>
