<script setup lang="ts">
import { reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import FormBuilderDemoPaletteSidebar from '@/components/modules/builder/FormBuilderDemoPaletteSidebar.vue'
import FormBuilderDemoCanvas from '@/components/modules/builder/FormBuilderDemoCanvas.vue'
import FormBuilderDemoPropertiesSidebar from '@/components/modules/builder/FormBuilderDemoPropertiesSidebar.vue'
import { useFormBuilderDemoPage } from '@/utils/composables/useFormBuilderDemoPage'

defineOptions({ layout: DashboardFocusLayout })

const d = reactive(useFormBuilderDemoPage())
</script>

<template>
    <Head title="Form Builder" />
    <div class="flex h-[calc(100svh-3.5rem)] flex-col overflow-hidden lg:flex-row">
        <FormBuilderDemoPaletteSidebar
            v-model:search-query="d.searchQuery"
            :categories="d.filteredCategories"
            :banner="d.formBanner"
            @patch-banner="d.patchBanner"
            @toggle-category="d.toggleCategory"
            @sidebar-drag-start="d.onSidebarDragStart"
        />

        <FormBuilderDemoCanvas
            v-model:form-title="d.formTitle"
            v-model:form-description="d.formDescription"
            v-model:mobile-field-pick="d.mobileFieldType"
            :field-count="d.formFields.length"
            :form-banner-caption="d.formBanner.caption"
            :banner-preview-src="d.bannerPreviewSrc"
            :is-empty="d.isEmpty"
            :is-dragging-over-canvas="d.isDraggingOverCanvas"
            :form-fields="d.formFields"
            :selected-field-id="d.selectedFieldId"
            :drop-indicator-index="d.dropIndicatorIndex"
            :drag-source-id="d.dragSourceId"
            :all-field-types="d.allFieldTypes"
            :add-field-disabled="!d.mobileFieldType"
            @add-from-picker="d.addFieldFromPicker"
            @canvas-drag-over="d.onCanvasDragOver"
            @canvas-drag-leave="d.onCanvasDragLeave"
            @canvas-drop="d.onCanvasDrop"
            @gap-drag-enter="d.onGapDragEnter"
            @canvas-drag-start="d.onCanvasDragStart"
            @drag-end="d.onDragEnd"
            @select-field="d.selectField"
            @delete-field="d.deleteField"
            @duplicate-field="d.duplicateField"
        />

        <FormBuilderDemoPropertiesSidebar :selected-field="d.selectedField" @update-field="d.updateField" />
    </div>
</template>
