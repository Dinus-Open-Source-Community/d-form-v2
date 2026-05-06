<script setup lang="ts">
import FieldEditor from '@/components/modules/builder/FieldEditor.vue'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { SlidersHorizontal, Settings } from 'lucide-vue-next'
import type { FormBuilderInspectorMode, FormBuilderValidationIssue } from '@/utils/composables/useFormBuilderWorkspace'
import type { BuilderField } from '@/types/form-builder'
import FormBuilderValidationSummary from './FormBuilderValidationSummary.vue'
import FormBuilderFormDetailsCard from './FormBuilderFormDetailsCard.vue'
import FormBuilderBannerBlock from './FormBuilderBannerBlock.vue'
import type { FormBannerState } from './formBanner'

const inspectorMode = defineModel<FormBuilderInspectorMode>('inspectorMode', { required: true })
const formTitle = defineModel<string>('formTitle', { required: true })
const formDescription = defineModel<string>('formDescription', { required: true })
const closedAt = defineModel<string>('closedAt', { required: true })
const visibleFor = defineModel<string[]>('visibleFor', { required: true })
const banner = defineModel<FormBannerState>('banner', { required: true })

defineProps<{
    selectedField: BuilderField | null
    isReadyToSave: boolean
    validationIssues: FormBuilderValidationIssue[]
    fieldErrors: Partial<Record<'title' | 'description' | 'closed_at' | 'visible_for', string>>
    visibilityOptions: readonly { value: string; label: string }[]
}>()

const emit = defineEmits<{
    toggleVisibility: [value: string, checked: boolean]
    updateField: [field: BuilderField]
}>()
</script>

<template>
    <aside
        class="border-border bg-card hidden w-[340px] shrink-0 flex-col border-l lg:flex"
        aria-label="Inspector panel"
    >
        <Tabs v-model="inspectorMode" class="flex h-full flex-col">
            <TabsList class="m-3 grid grid-cols-2">
                <TabsTrigger value="settings" class="gap-1.5">
                    <SlidersHorizontal class="size-3.5" />
                    Settings
                    <span
                        v-if="!isReadyToSave"
                        class="bg-warning/15 text-warning ml-1 grid size-4 place-items-center rounded-full text-[9px] leading-none font-bold"
                    >
                        !
                    </span>
                </TabsTrigger>
                <TabsTrigger value="field" class="gap-1.5" :disabled="!selectedField">
                    <Settings class="size-3.5" />
                    Field
                </TabsTrigger>
            </TabsList>

            <TabsContent value="settings" class="m-0 flex-1 overflow-y-auto">
                <div class="space-y-5 px-4 pb-6">
                    <FormBuilderValidationSummary
                        v-if="!isReadyToSave"
                        :issues="validationIssues"
                        density="compact"
                    />

                    <FormBuilderFormDetailsCard
                        v-model:form-title="formTitle"
                        v-model:form-description="formDescription"
                        v-model:closed-at="closedAt"
                        v-model:visible-for="visibleFor"
                        id-prefix="d"
                        :field-errors="fieldErrors"
                        :visibility-options="visibilityOptions"
                        @toggle-visibility="(value, checked) => emit('toggleVisibility', value, checked)"
                    />

                    <FormBuilderBannerBlock v-model:banner="banner" variant="inline" />
                </div>
            </TabsContent>

            <TabsContent value="field" class="m-0 flex-1 overflow-y-auto">
                <div class="px-4 pb-6">
                    <FieldEditor
                        v-if="selectedField"
                        :field="selectedField"
                        @update:field="emit('updateField', $event)"
                    />
                    <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                        <div
                            class="border-border bg-muted/30 grid size-12 place-items-center rounded-2xl border border-dashed"
                        >
                            <Settings class="text-muted-foreground size-5" />
                        </div>
                        <p class="text-foreground mt-4 text-xs font-semibold">No field selected</p>
                        <p class="text-muted-foreground mt-1 max-w-[220px] text-[10px] leading-relaxed">
                            Click any field on the canvas to edit its properties.
                        </p>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    </aside>
</template>
