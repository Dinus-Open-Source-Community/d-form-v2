<script setup lang="ts">
import FieldEditor from '@/components/modules/builder/FieldEditor.vue'
import type { FormBuilderInspectorMode, FormBuilderValidationIssue } from '@/utils/composables/useFormBuilderWorkspace'
import type { BuilderField } from '@/types/form-builder'
import type { FormRegistrationMetadata, FormSiblingOption } from '@/types/form'
import FormBuilderValidationSummary from './FormBuilderValidationSummary.vue'
import FormBuilderFormDetailsCard from './FormBuilderFormDetailsCard.vue'
import FormBuilderBannerBlock from './FormBuilderBannerBlock.vue'
import type { FormBannerState } from './formBanner'

const inspectorMode = defineModel<FormBuilderInspectorMode>('inspectorMode', { required: true })
const formTitle = defineModel<string>('formTitle', { required: true })
const formDescription = defineModel<string>('formDescription', { required: true })
const successContent = defineModel<string>('successContent', { required: true })
const closedAt = defineModel<string>('closedAt', { required: true })
const visibleFor = defineModel<string[]>('visibleFor', { required: true })
const banner = defineModel<FormBannerState>('banner', { required: true })
const formMetadata = defineModel<FormRegistrationMetadata>('formMetadata', { required: true })

withDefaults(
    defineProps<{
        selectedField: BuilderField | null
        isReadyToSave: boolean
        validationIssues: FormBuilderValidationIssue[]
        fieldErrors: Partial<Record<'title' | 'description' | 'closed_at' | 'visible_for', string>>
        visibilityOptions: readonly { value: string; label: string }[]
        siblingForms?: FormSiblingOption[]
    }>(),
    {
        siblingForms: () => [],
    },
)

const emit = defineEmits<{
    toggleVisibility: [value: string, checked: boolean]
    updateField: [field: BuilderField]
}>()
</script>

<template>
    <aside
        class="border-border bg-card hidden h-full max-h-full min-h-0 w-[340px] shrink-0 flex-col border-l lg:flex"
        aria-label="Inspector panel"
    >
        <div class="shrink-0 px-4 pt-4">
            <div class="grid w-full grid-cols-2 gap-2" role="tablist" aria-label="Mode inspector">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="inspectorMode === 'settings'"
                    class="flex min-h-10 w-full min-w-0 items-center justify-center gap-1.5 rounded-xl border border-transparent px-2 py-2.5 text-sm font-medium transition-[color,background-color,box-shadow,border-color] duration-200"
                    :class="
                        inspectorMode === 'settings'
                            ? 'border-border/60 bg-card text-foreground shadow-sm ring-1 ring-border/40'
                            : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                    @click="inspectorMode = 'settings'"
                >
                    <span class="truncate">Pengaturan</span>
                    <span
                        v-if="!isReadyToSave"
                        class="bg-warning/15 text-warning grid size-5 shrink-0 place-items-center rounded-full text-[10px] font-bold leading-none"
                    >
                        !
                    </span>
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="inspectorMode === 'field'"
                    class="flex min-h-10 w-full min-w-0 items-center justify-center gap-1.5 rounded-xl border border-transparent px-2 py-2.5 text-sm font-medium transition-[color,background-color,box-shadow,border-color] duration-200 disabled:cursor-not-allowed disabled:opacity-45"
                    :class="
                        inspectorMode === 'field'
                            ? 'border-border/60 bg-card text-foreground shadow-sm ring-1 ring-border/40'
                            : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                    :disabled="!selectedField"
                    @click="inspectorMode = 'field'"
                >
                    <span class="truncate">Field</span>
                </button>
            </div>
        </div>

        <div
            v-show="inspectorMode === 'settings'"
            class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden"
            role="tabpanel"
        >
            <div class="space-y-6 px-5 py-5 pb-8">
                <FormBuilderValidationSummary
                    v-if="!isReadyToSave"
                    :issues="validationIssues"
                    density="compact"
                />

                <FormBuilderFormDetailsCard
                    v-model:form-title="formTitle"
                    v-model:form-description="formDescription"
                    v-model:success-content="successContent"
                    v-model:closed-at="closedAt"
                    v-model:visible-for="visibleFor"
                    v-model:form-metadata="formMetadata"
                    id-prefix="d"
                    :field-errors="fieldErrors"
                    :visibility-options="visibilityOptions"
                    :sibling-forms="siblingForms"
                    @toggle-visibility="(value, checked) => emit('toggleVisibility', value, checked)"
                />

                <FormBuilderBannerBlock v-model:banner="banner" variant="inline" />
            </div>
        </div>

        <div
            v-show="inspectorMode === 'field'"
            class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden"
            role="tabpanel"
        >
            <div class="px-5 py-5 pb-8">
                <FieldEditor
                    v-if="selectedField"
                    :field="selectedField"
                    @update:field="emit('updateField', $event)"
                />
                <div v-else class="flex flex-col justify-center py-16 text-center">
                    <p class="text-foreground text-sm font-medium">Belum ada field dipilih</p>
                    <p class="text-muted-foreground mt-2 px-4 text-sm leading-relaxed">
                        Klik salah satu field di kanvas untuk mengubah properti.
                    </p>
                </div>
            </div>
        </div>
    </aside>
</template>
