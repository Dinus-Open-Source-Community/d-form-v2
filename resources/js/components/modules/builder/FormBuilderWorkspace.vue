<script setup lang="ts">
import { computed, reactive } from 'vue'
import FormPreviewDialog from '@/components/modules/builder/FormPreviewDialog.vue'
import type { FormBannerState } from '@/components/modules/builder/formBanner'
import { FORM_VISIBILITY_OPTIONS } from '@/components/modules/builder/formBuilderPalette'
import type { BuilderField } from '@/types/form-builder'
import { useFormBuilderWorkspace } from '@/utils/composables/useFormBuilderWorkspace'
import FormBuilderToolbar from './FormBuilderToolbar.vue'
import FormBuilderMobileTabBar from './FormBuilderMobileTabBar.vue'
import FormBuilderPalettePanel from './FormBuilderPalettePanel.vue'
import FormBuilderCanvasBuildView from './FormBuilderCanvasBuildView.vue'
import FormBuilderValidationSummary from './FormBuilderValidationSummary.vue'
import FormBuilderFormDetailsCard from './FormBuilderFormDetailsCard.vue'
import FormBuilderBannerBlock from './FormBuilderBannerBlock.vue'
import FormBuilderInspectorPanel from './FormBuilderInspectorPanel.vue'
import FormBuilderAddFieldSheet from './FormBuilderAddFieldSheet.vue'
import FormBuilderEditFieldSheet from './FormBuilderEditFieldSheet.vue'
import FormBuilderMobileAddFab from './FormBuilderMobileAddFab.vue'

const props = withDefaults(
    defineProps<{
        event: { id: string; title: string }
        toolbarSubtitle: string
        saveLabel?: string
        processing?: boolean
        fieldErrors?: Partial<Record<'title' | 'description' | 'closed_at' | 'visible_for', string>>
        shell?: 'dashboard' | 'fullscreen'
    }>(),
    {
        saveLabel: 'Save Form',
        processing: false,
        fieldErrors: () => ({}),
        shell: 'dashboard',
    },
)

const emit = defineEmits<{
    save: []
}>()

const formTitle = defineModel<string>('formTitle', { required: true })
const formDescription = defineModel<string>('formDescription', { required: true })
const closedAt = defineModel<string>('closedAt', { required: true })
const visibleFor = defineModel<string[]>('visibleFor', { required: true })
const banner = defineModel<FormBannerState>('banner', { required: true })
const formFields = defineModel<BuilderField[]>('formFields', { required: true })

const wb = reactive(
    useFormBuilderWorkspace(
        {
            formTitle,
            formDescription,
            closedAt,
            visibleFor,
            banner,
            formFields,
        },
        { onSave: () => emit('save') },
    ),
)

const backHref = computed(() => `/dashboard/events/${props.event.id}/forms`)
const shellHeightClass = computed(() =>
    props.shell === 'fullscreen' ? 'h-svh' : 'h-[calc(100svh-3.5rem)]',
)
const visibilityOptions = FORM_VISIBILITY_OPTIONS
</script>

<template>
    <div :class="['flex flex-col overflow-hidden', shellHeightClass]">
        <FormBuilderToolbar
            :back-href="backHref"
            :toolbar-subtitle="toolbarSubtitle"
            :heading-title="formTitle"
            :is-ready-to-save="wb.isReadyToSave"
            :validation-issue-count="wb.validationIssues.length"
            :is-empty="wb.isEmpty"
            :processing="processing"
            :save-label="saveLabel"
            @preview="wb.showPreview = true"
            @save="wb.requestSave"
        >
            <template #toolbar-extra>
                <slot name="toolbar-extra" />
            </template>
        </FormBuilderToolbar>

        <FormBuilderMobileTabBar
            v-model="wb.mobileTab"
            :field-count="formFields.length"
            :is-ready-to-save="wb.isReadyToSave"
        />

        <div class="flex flex-1 overflow-hidden">
            <FormBuilderPalettePanel
                v-model:search-query="wb.searchQuery"
                :categories="wb.filteredCategories"
                @toggle-category="wb.toggleCategory"
            />

            <main class="bg-background relative min-h-0 flex-1 overflow-y-auto">
                <FormBuilderCanvasBuildView
                    v-model:form-title="formTitle"
                    v-model:form-description="formDescription"
                    :hide-on-mobile-settings="wb.mobileTab === 'settings'"
                    :banner-preview-src="wb.bannerPreviewSrc"
                    :is-empty="wb.isEmpty"
                    :is-dragging-over-canvas="wb.isDraggingOverCanvas"
                    :form-fields="formFields"
                    :selected-field-id="wb.selectedFieldId"
                    :drop-indicator-index="wb.dropIndicatorIndex"
                    :drag-source-id="wb.dragSourceId"
                    @canvas-drag-over="wb.onCanvasDragOver"
                    @canvas-drag-leave="wb.onCanvasDragLeave"
                    @canvas-drop="wb.onCanvasDrop"
                    @gap-drag-enter="wb.onGapDragEnter"
                    @canvas-drag-start="wb.onCanvasDragStart"
                    @drag-end="wb.onDragEnd"
                    @select-field="wb.selectField"
                    @delete-field="wb.deleteField"
                    @duplicate-field="wb.duplicateField"
                    @move-field="wb.moveField"
                    @open-add-sheet="wb.showAddSheet = true"
                />

                <div v-show="wb.mobileTab === 'settings'" class="px-4 pt-5 pb-24 lg:hidden">
                    <div class="mx-auto max-w-[480px] space-y-5">
                        <FormBuilderValidationSummary
                            v-if="!wb.isReadyToSave"
                            :issues="wb.validationIssues"
                            density="comfortable"
                        />

                        <FormBuilderFormDetailsCard
                            v-model:form-title="formTitle"
                            v-model:form-description="formDescription"
                            v-model:closed-at="closedAt"
                            v-model:visible-for="visibleFor"
                            id-prefix="m"
                            :field-errors="fieldErrors"
                            :visibility-options="visibilityOptions"
                            @toggle-visibility="wb.toggleVisibility"
                        />

                        <FormBuilderBannerBlock v-model:banner="banner" variant="card" />
                    </div>
                </div>
            </main>

            <FormBuilderInspectorPanel
                v-model:inspector-mode="wb.inspectorMode"
                v-model:form-title="formTitle"
                v-model:form-description="formDescription"
                v-model:closed-at="closedAt"
                v-model:visible-for="visibleFor"
                v-model:banner="banner"
                :selected-field="wb.selectedField"
                :is-ready-to-save="wb.isReadyToSave"
                :validation-issues="wb.validationIssues"
                :field-errors="fieldErrors"
                :visibility-options="visibilityOptions"
                @toggle-visibility="wb.toggleVisibility"
                @update-field="wb.updateField"
            />
        </div>

        <FormBuilderMobileAddFab
            v-if="wb.mobileTab === 'build' && !wb.isEmpty"
            @click="wb.showAddSheet = true"
        />
    </div>

    <FormBuilderAddFieldSheet
        v-model:open="wb.showAddSheet"
        v-model:search-query="wb.searchQuery"
        :categories="wb.filteredCategories"
        @pick-field="wb.addField($event, false)"
    />

    <FormBuilderEditFieldSheet
        v-model:open="wb.showMobileEditor"
        :field="wb.selectedField"
        @update-field="wb.updateField"
        @done="wb.showMobileEditor = false"
    />

    <FormPreviewDialog
        :open="wb.showPreview"
        :title="formTitle || 'Untitled Form'"
        :description="formDescription"
        :form-banner-url="banner.bannerUrl"
        :form-banner-caption="banner.caption"
        :fields="formFields"
        @close="wb.showPreview = false"
    />
</template>
