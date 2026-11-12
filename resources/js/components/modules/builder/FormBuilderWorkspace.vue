<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import FormPreviewDialog from '@/components/modules/builder/FormPreviewDialog.vue';
import { Button } from '@/components/ui/button';
import type { IFormBannerState } from '@/components/modules/builder/formBanner';
import type { IBuilderField } from '@/types/form-builder';
import type { IFormRegistrationMetadata } from '@/types/form';
import { useFormBuilderWorkspace } from '@/hooks/useFormBuilderWorkspace';
import FormBuilderPalettePanel from './FormBuilderPalettePanel.vue';
import FormBuilderCanvasBuildView from './FormBuilderCanvasBuildView.vue';
import FormBuilderAddFieldSheet from './FormBuilderAddFieldSheet.vue';
import FormBuilderEditFieldSheet from './FormBuilderEditFieldSheet.vue';
import FormBuilderMobileAddFab from './FormBuilderMobileAddFab.vue';

const props = withDefaults(
    defineProps<{
        processing?: boolean;
        shell?: 'dashboard' | 'fullscreen';
        /** Error validasi header dari server; ditampilkan di input judul/deskripsi kanvas. */
        fieldErrors?: Partial<Record<'title' | 'description', string>>;
        /** Endpoint `POST {form}/images` untuk upload banner/gambar blok/opsi. */
        imageUploadUrl?: string;
    }>(),
    {
        processing: false,
        shell: 'dashboard',
        fieldErrors: () => ({}),
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
const banner = defineModel<IFormBannerState>('banner', { required: true });
const formFields = defineModel<IBuilderField[]>('formFields', { required: true });
const formMetadata = defineModel<IFormRegistrationMetadata>('formMetadata', { required: true });
const canvasScrollContainer = ref<HTMLElement | null>(null);

const wb = reactive(
    useFormBuilderWorkspace(
        {
            formTitle,
            formDescription,
            closedAt,
            visibleFor,
            banner,
            formFields,
            formMetadata,
            canvasScrollContainer,
            successContent,
        },
        { onSave: () => emit('save') }
    )
);

const shellHeightClass = computed(() => (props.shell === 'fullscreen' ? 'h-svh' : 'h-full'));

/**
 * Satu-satunya jalur simpan untuk blok aksi mobile (aksi desktop hidup di header tab
 * halaman). Klik berulang saat request berjalan diabaikan agar form tidak terkirim dua kali.
 * Header form yang belum valid (tanggal tutup / visibilitas) memindahkan user ke tab
 * pengaturan lewat `openSettings`; emit hanya sekali per klik.
 */
function submitForm(): void {
    if (props.processing) return;

    if (wb.validationIssues.some((issue) => issue.key === 'closedAt' || issue.key === 'visibleFor')) {
        emit('openSettings');
    }

    wb.requestSave();
}

/** Handle untuk aksi halaman induk (Pratinjau / simpan) yang hidup di header tab. */
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
        :class="[
            'relative flex min-h-0 flex-col overflow-hidden',
            shellHeightClass,
            '[--builder-fab-offset:9.25rem] sm:[--builder-fab-offset:5.25rem]',
        ]"
    >
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden lg:flex-row">
            <FormBuilderPalettePanel
                v-model:search-query="wb.searchQuery"
                :categories="wb.filteredCategories"
                :open-category-name="wb.openCategoryName"
                @toggle-category="wb.toggleCategory"
                @palette-drag-start="wb.onPaletteDragStart"
                @palette-drag-end="wb.onDragEnd"
            />

            <main
                ref="canvasScrollContainer"
                data-testid="form-builder-canvas-scroll"
                class="bg-background relative min-h-0 min-w-0 flex-1 overflow-y-auto"
            >
                <FormBuilderCanvasBuildView
                    v-model:form-title="formTitle"
                    v-model:form-description="formDescription"
                    v-model:success-content="successContent"
                    v-model:banner="banner"
                    :show-success-zone="wb.showSuccessZone"
                    :banner-preview-src="wb.bannerPreviewSrc"
                    :title-error="fieldErrors.title"
                    :description-error="fieldErrors.description"
                    :is-empty="wb.isCurrentSectionEmpty"
                    :is-dragging-over-canvas="wb.isDraggingOverCanvas"
                    :form-fields="wb.currentSectionFields"
                    :sections="wb.sections"
                    :current-section-index="wb.currentSectionIndex"
                    :selected-field-id="wb.selectedFieldId"
                    :drop-indicator-index="wb.dropIndicatorIndex"
                    :drag-source-id="wb.dragSourceId"
                    :banner-upload-url="imageUploadUrl"
                    @remove="wb.hideSuccessZone"
                    @canvas-drag-enter="wb.onCanvasDragEnter"
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
                    @add-section="wb.addSection"
                    @go-to-section="wb.goToSection"
                />
            </main>

            <div
                class="mx-auto flex w-full max-w-[480px] flex-col gap-2 px-4 pt-3 pb-[calc(1.5rem+env(safe-area-inset-bottom))] sm:hidden"
            >
                <slot name="mobile-actions" />
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
        <FormBuilderMobileAddFab v-if="!wb.isEmpty" @click="wb.showAddSheet = true" />
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
