<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import FormPreviewDialog from '@/components/modules/builder/FormPreviewDialog.vue';
import { Button } from '@/components/ui/button';
import type { FormBannerState } from '@/components/modules/builder/formBanner';
import { FORM_VISIBILITY_OPTIONS } from '@/components/modules/builder/formBuilderPalette';
import type { BuilderField } from '@/types/form-builder';
import type { FormRegistrationMetadata, FormSiblingOption } from '@/types/form';
import { useFormBuilderWorkspace } from '@/utils/composables/useFormBuilderWorkspace';
import FormBuilderToolbar from './FormBuilderToolbar.vue';
import FormBuilderMobileTabBar from './FormBuilderMobileTabBar.vue';
import FormBuilderPalettePanel from './FormBuilderPalettePanel.vue';
import FormBuilderCanvasBuildView from './FormBuilderCanvasBuildView.vue';
import FormBuilderAddFieldSheet from './FormBuilderAddFieldSheet.vue';
import FormBuilderEditFieldSheet from './FormBuilderEditFieldSheet.vue';
import FormBuilderMobileAddFab from './FormBuilderMobileAddFab.vue';
import { routes } from '@/lib/routes';
import FormSettingsPanel from './FormSettingsPanel.vue';

const props = withDefaults(
    defineProps<{
        event: { id: string; title: string };
        toolbarSubtitle: string;
        saveLabel?: string;
        processing?: boolean;
        fieldErrors?: Partial<Record<'title' | 'description' | 'closed_at' | 'visible_for', string>>;
        shell?: 'dashboard' | 'fullscreen';
        siblingForms?: FormSiblingOption[];
        hideToolbarTitles?: boolean;
        hideToolbar?: boolean;
        formSettingsPlacement?: 'inline' | 'tab';
        /** Endpoint `POST {form}/images` untuk upload banner/gambar blok/opsi. */
        imageUploadUrl?: string;
    }>(),
    {
        saveLabel: 'Save Form',
        processing: false,
        fieldErrors: () => ({}),
        shell: 'dashboard',
        siblingForms: () => [],
        hideToolbarTitles: false,
        hideToolbar: false,
        formSettingsPlacement: 'inline',
        imageUploadUrl: '',
    }
);

const emit = defineEmits<{
    save: [];
    openSettings: [];
}>();

const formTitle = defineModel<string>('formTitle', { required: true });
const formDescription = defineModel<string>('formDescription', { required: true });
const successContent = defineModel<string>('successContent', { required: true });
const closedAt = defineModel<string>('closedAt', { required: true });
const visibleFor = defineModel<string[]>('visibleFor', { required: true });
const banner = defineModel<FormBannerState>('banner', { required: true });
const formFields = defineModel<BuilderField[]>('formFields', { required: true });
const formMetadata = defineModel<FormRegistrationMetadata>('formMetadata', { required: true });

const wb = reactive(
    useFormBuilderWorkspace(
        {
            formTitle,
            formDescription,
            closedAt,
            visibleFor,
            banner,
            formFields,
            successContent,
        },
        { onSave: () => emit('save') }
    )
);

const backHref = computed(() => routes.admin.events.show(props.event.id));
const shellHeightClass = computed(() => (props.shell === 'fullscreen' ? 'h-svh' : 'h-full'));
const visibilityOptions = FORM_VISIBILITY_OPTIONS;
/** Accordion "Pengaturan form" di panel kiri (bawaan tertutup). */
const formSettingsOpen = ref(false);
function toggleFormSettings(): void {
    formSettingsOpen.value = !formSettingsOpen.value;
}

/**
 * Satu-satunya jalur simpan untuk toolbar dan blok mobile.
 * Klik berulang saat request masih berjalan diabaikan agar form tidak terkirim dua kali.
 */
function submitForm(): void {
    if (props.processing) return;

    if (
        props.formSettingsPlacement === 'tab' &&
        wb.validationIssues.some((issue) => issue.key === 'closedAt' || issue.key === 'visibleFor')
    ) {
        emit('openSettings');
    }

    wb.requestSave();
}

/** Handle untuk aksi toolbar eksternal (Pratinjau / Save All) dari halaman induk. */
defineExpose({
    showPreview: () => {
        wb.showPreview = true;
    },
    requestSave: submitForm,
});
</script>

<template>
    <div
        data-testid="form-builder-workspace"
        :class="['flex min-h-0 flex-col overflow-hidden', shellHeightClass]"
    >
        <FormBuilderToolbar
            :back-href="backHref"
            :toolbar-subtitle="toolbarSubtitle"
            :heading-title="formTitle"
            :is-ready-to-save="wb.isReadyToSave"
            :validation-issue-count="wb.validationIssues.length"
            :is-empty="wb.isEmpty"
            :processing="processing"
            :save-label="saveLabel"
            :hide-titles="hideToolbarTitles"
            :hide-toolbar="hideToolbar"
            @preview="wb.showPreview = true"
            @save="submitForm"
        >
            <template #toolbar-extra>
                <slot name="toolbar-extra" />
            </template>
        </FormBuilderToolbar>

        <FormBuilderMobileTabBar
            v-model="wb.mobileTab"
            :field-count="formFields.length"
            :is-ready-to-save="wb.isReadyToSave"
            :show-settings-tab="formSettingsPlacement === 'inline'"
        />

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden lg:flex-row">
            <FormBuilderPalettePanel
                v-model:search-query="wb.searchQuery"
                v-model:closed-at="closedAt"
                v-model:visible-for="visibleFor"
                v-model:form-metadata="formMetadata"
                :categories="wb.filteredCategories"
                :open-category-name="wb.openCategoryName"
                :form-settings-open="formSettingsOpen"
                :show-form-settings="formSettingsPlacement === 'inline'"
                :field-errors="fieldErrors"
                :visibility-options="visibilityOptions"
                :sibling-forms="siblingForms"
                @toggle-category="wb.toggleCategory"
                @toggle-form-settings="toggleFormSettings"
                @toggle-visibility="wb.toggleVisibility"
            />

            <main
                data-testid="form-builder-canvas-scroll"
                class="bg-background relative min-h-0 min-w-0 flex-1 overflow-y-auto"
            >
                <FormBuilderCanvasBuildView
                    v-model:form-title="formTitle"
                    v-model:form-description="formDescription"
                    v-model:success-content="successContent"
                    v-model:banner="banner"
                    :show-success-zone="wb.showSuccessZone"
                    :hide-on-mobile-settings="formSettingsPlacement === 'inline' && wb.mobileTab === 'settings'"
                    :banner-preview-src="wb.bannerPreviewSrc"
                    :is-empty="wb.isEmpty"
                    :is-dragging-over-canvas="wb.isDraggingOverCanvas"
                    :form-fields="formFields"
                    :selected-field-id="wb.selectedFieldId"
                    :drop-indicator-index="wb.dropIndicatorIndex"
                    :drag-source-id="wb.dragSourceId"
                    :banner-upload-url="imageUploadUrl"
                    @remove="wb.hideSuccessZone"
                    @canvas-drag-over="wb.onCanvasDragOver"
                    @canvas-drag-leave="wb.onCanvasDragLeave"
                    @canvas-drop="wb.onCanvasDrop"
                    @gap-drag-enter="wb.onGapDragEnter"
                    @canvas-drag-start="wb.onCanvasDragStart"
                    @drag-end="wb.onDragEnd"
                    @select-field="wb.selectField"
                    @update-field="wb.updateField"
                    @manage-field="wb.openFieldManage"
                    @delete-field="wb.deleteField"
                    @duplicate-field="wb.duplicateField"
                    @move-field="wb.moveField"
                    @open-add-sheet="wb.showAddSheet = true"
                />

                <div
                    v-if="formSettingsPlacement === 'inline'"
                    v-show="wb.mobileTab === 'settings'"
                    class="px-4 pt-5 pb-24 lg:hidden"
                >
                    <div class="mx-auto max-w-[480px]">
                        <FormSettingsPanel
                            v-model:closed-at="closedAt"
                            v-model:visible-for="visibleFor"
                            v-model:form-metadata="formMetadata"
                            id-prefix="m"
                            :field-errors="fieldErrors"
                            :visibility-options="visibilityOptions"
                            :sibling-forms="siblingForms"
                            @toggle-visibility="wb.toggleVisibility"
                        />
                    </div>
                </div>
            </main>

            <div class="mx-auto w-full max-w-[480px] px-4 pb-6 sm:hidden">
                <div class="grid grid-cols-2 gap-2">
                    <Button
                        variant="outline"
                        class="border-border/80 bg-background h-11 text-sm font-medium shadow-sm"
                        :disabled="wb.isEmpty"
                        aria-label="Pratinjau formulir"
                        @click="wb.showPreview = true"
                    >
                        Pratinjau
                    </Button>
                    <Button class="h-11 text-sm font-medium shadow-sm" :disabled="processing" @click="submitForm">
                        Simpan
                    </Button>
                </div>
            </div>
        </div>
        <FormBuilderMobileAddFab v-if="wb.mobileTab === 'build' && !wb.isEmpty" @click="wb.showAddSheet = true" />
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
        :upload-url="imageUploadUrl"
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
