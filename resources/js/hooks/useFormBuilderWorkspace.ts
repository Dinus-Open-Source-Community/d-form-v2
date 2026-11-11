import { ref, computed, nextTick, watch, type Ref } from 'vue';
import { showErrorToast } from '@/lib/error-message';
import { DEFAULT_FORM_SECTION_ID, defaultFormBuilderSections } from '@/lib/formRegistrationMetadata';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import type { IFormBannerState } from '@/components/modules/builder/formBanner';
import {
    cloneFormBuilderPalette,
    type IFormBuilderPaletteCategory,
    type IFormBuilderPaletteField,
} from '@/components/modules/builder/formBuilderPalette';
import { createFormBuilderField } from '@/components/modules/builder/formBuilderFieldFactory';
import { createFormBuilderFieldList, parseFormBuilderDragPayload } from '@/lib/formBuilderFieldList';
import type { IBuilderField, IFormBuilderSection } from '@/types/form-builder';
import type { IFormRegistrationMetadata } from '@/types/form';

export type TFormBuilderInspectorMode = 'settings' | 'field';
export type TFormBuilderMobileTab = 'build' | 'settings';

export interface IFormBuilderValidationIssue {
    key: 'title' | 'description' | 'closedAt' | 'visibleFor' | 'fields';
    label: string;
}

export interface IFormBuilderWorkspaceModels {
    formTitle: Ref<string>;
    formDescription: Ref<string>;
    closedAt: Ref<string>;
    visibleFor: Ref<string[]>;
    banner: Ref<IFormBannerState>;
    formFields: Ref<IBuilderField[]>;
    formMetadata: Ref<IFormRegistrationMetadata>;
    canvasScrollContainer: Ref<HTMLElement | null>;
    successContent?: Ref<string>;
}

/** Kategori palette terbuka. `null` = semua tertutup (single-expand). */
export function useFormBuilderWorkspace(models: IFormBuilderWorkspaceModels, options: { onSave: () => void }) {
    const categories = ref<IFormBuilderPaletteCategory[]>(cloneFormBuilderPalette());

    /** Single-expand: simpan nama kategori yang terbuka (default semua tertutup). */
    const openCategoryName = ref<string | null>(null);

    const searchQuery = ref<string>('');
    const selectedFieldId = ref<string | null>(null);
    const dropIndicatorIndex = ref<number>(-1);
    const isDraggingOverCanvas = ref<boolean>(false);
    const dragSourceId = ref<string | null>(null);
    const inspectorMode = ref<TFormBuilderInspectorMode>('settings');
    const mobileTab = ref<TFormBuilderMobileTab>('build');
    const showAddSheet = ref<boolean>(false);
    const showMobileEditor = ref<boolean>(false);
    const showPreview = ref<boolean>(false);
    const currentSectionId = ref<string>(DEFAULT_FORM_SECTION_ID);

    const sections = computed<IFormBuilderSection[]>(() => {
        const configured = models.formMetadata.value.sections;
        return configured.length > 0 ? configured : defaultFormBuilderSections();
    });

    const currentSectionIndex = computed<number>(() => {
        const index = sections.value.findIndex((section) => section.id === currentSectionId.value);
        return index >= 0 ? index : 0;
    });

    const activeSectionId = computed<string>(
        () => sections.value[currentSectionIndex.value]?.id ?? DEFAULT_FORM_SECTION_ID
    );

    function sectionIdForField(field: IBuilderField): string {
        const sectionId = field.metadata.sectionId;
        if (
            typeof sectionId === 'string' &&
            sectionId.trim() !== '' &&
            sections.value.some((section) => section.id === sectionId)
        ) {
            return sectionId;
        }
        return sections.value[0]?.id ?? DEFAULT_FORM_SECTION_ID;
    }

    const currentSectionFields = computed<IBuilderField[]>(() =>
        models.formFields.value.filter((field) => sectionIdForField(field) === activeSectionId.value)
    );

    function ensureSections(): IFormBuilderSection[] {
        if (models.formMetadata.value.sections.length > 0) return models.formMetadata.value.sections;
        const fallback = defaultFormBuilderSections();
        models.formMetadata.value = { ...models.formMetadata.value, sections: fallback };
        return fallback;
    }

    function addSection(): void {
        const configured = ensureSections();
        const section: IFormBuilderSection = {
            id: crypto.randomUUID(),
            title: `Bagian ${configured.length + 1}`,
        };
        models.formMetadata.value = {
            ...models.formMetadata.value,
            sections: [...configured, section],
        };
        currentSectionId.value = section.id;
        selectedFieldId.value = null;
        inspectorMode.value = 'settings';
        resetCanvasScroll();
    }

    function goToSection(index: number): void {
        const target = sections.value[index];
        if (!target) return;
        currentSectionId.value = target.id;
        selectedFieldId.value = null;
        inspectorMode.value = 'settings';
        resetCanvasScroll();
    }

    /** Returns the canvas to the beginning after changing the active form section. */
    function resetCanvasScroll(): void {
        void nextTick(() => {
            models.canvasScrollContainer.value?.scrollTo({ top: 0, behavior: 'auto' });
        });
    }

    function assignFieldToSection(field: IBuilderField, sectionId: string): IBuilderField {
        return { ...field, metadata: { ...field.metadata, sectionId } };
    }

    function sectionFieldIndexes(sectionId: string): number[] {
        return models.formFields.value.flatMap((field, index) =>
            sectionIdForField(field) === sectionId ? [index] : []
        );
    }

    function globalInsertIndex(sectionId: string, localIndex: number): number {
        const indexes = sectionFieldIndexes(sectionId);
        const target = indexes[localIndex];
        if (target !== undefined) return target;
        const last = indexes[indexes.length - 1];
        return last === undefined ? models.formFields.value.length : last + 1;
    }

    watch(
        sections,
        (configured) => {
            if (!configured.some((section) => section.id === currentSectionId.value)) {
                currentSectionId.value = configured[0]?.id ?? DEFAULT_FORM_SECTION_ID;
            }
        },
        { immediate: true }
    );

    /** Zona "Pesan setelah submit" (ala Google Forms). `true` = canvas menampilkan zona (dipicu drag item palette). */
    const showSuccessZone = ref(false);

    // Edit form tersimpan: konten sudah ada saat mount → tampilkan zona.
    const initialSuccess = models.successContent?.value ?? '';
    if (
        initialSuccess
            .replace(/<[^>]*>/g, '')
            .replace(/&nbsp;/gi, ' ')
            .trim() !== ''
    ) {
        showSuccessZone.value = true;
    }

    const filteredCategories = computed(() => {
        const q = searchQuery.value.toLowerCase().trim();
        if (!q) return categories.value;
        // Mode pencarian: tampilkan semua kategori yang cocok (abaikan single-expand).
        return categories.value
            .map((cat) => ({
                ...cat,
                isOpen: true,
                fields: cat.fields.filter(
                    (f) =>
                        f.label.toLowerCase().includes(q) ||
                        f.description.toLowerCase().includes(q) ||
                        f.type.includes(q)
                ),
            }))
            .filter((cat) => cat.fields.length > 0);
    });

    const selectedField = computed<IBuilderField | null>(
        () => models.formFields.value.find((f) => f.id === selectedFieldId.value) ?? null
    );

    const isEmpty = computed<boolean>(() => models.formFields.value.length === 0);
    const isCurrentSectionEmpty = computed<boolean>(() => currentSectionFields.value.length === 0);
    const bannerPreviewSrc = computed<string>(() => normalizePublicStorageUrl(models.banner.value.bannerUrl));

    const validationIssues = computed<IFormBuilderValidationIssue[]>(() => {
        const issues: IFormBuilderValidationIssue[] = [];
        if (!models.formTitle.value.trim()) issues.push({ key: 'title', label: 'Form title' });
        if (!models.formDescription.value.trim()) issues.push({ key: 'description', label: 'Description' });
        if (!models.closedAt.value) issues.push({ key: 'closedAt', label: 'Close date' });
        if (models.visibleFor.value.length === 0) issues.push({ key: 'visibleFor', label: 'Visibility' });
        if (models.formFields.value.length === 0) issues.push({ key: 'fields', label: 'At least one field' });
        return issues;
    });

    const isReadyToSave = computed<boolean>(() => validationIssues.value.length === 0);

    function patchBanner(v: IFormBannerState): void {
        Object.assign(models.banner.value, v);
    }

    function hideSuccessZone(): void {
        showSuccessZone.value = false;
        if (models.successContent) models.successContent.value = '';
    }

    const fieldList = createFormBuilderFieldList<IBuilderField>({
        store: {
            read: (): IBuilderField[] => models.formFields.value,
            commit: (next: IBuilderField[]): void => {
                models.formFields.value = next;
            },
        },
        createField: createFormBuilderField,
        finalizeCreatedField: (field: IBuilderField): IBuilderField =>
            assignFieldToSection(field, activeSectionId.value),
    });

    function addField(template: IFormBuilderPaletteField, openEditorAfter = false): void {
        // Item palette "Pesan setelah submit" = trigger zona konfirmasi, bukan IBuilderField.
        if (template.type === 'confirmation') {
            showSuccessZone.value = true;
            showAddSheet.value = false;
            if (openEditorAfter) showMobileEditor.value = true;
            return;
        }
        const nf = fieldList.appendField(template.type, template.label);
        selectedFieldId.value = nf.id;
        inspectorMode.value = 'field';
        showAddSheet.value = false;
        if (openEditorAfter) showMobileEditor.value = true;
    }

    function selectField(id: string, isMobile = false): void {
        if (selectedFieldId.value === id && !isMobile) {
            selectedFieldId.value = null;
            inspectorMode.value = 'settings';
            return;
        }
        selectedFieldId.value = id;
        inspectorMode.value = 'field';
        if (isMobile) showMobileEditor.value = true;
    }

    function deleteField(id: string): void {
        const wasSelected = selectedFieldId.value === id;
        if (!fieldList.deleteField(id)) return;
        if (!wasSelected) return;
        selectedFieldId.value = null;
        inspectorMode.value = 'settings';
        showMobileEditor.value = false;
    }

    /** Buka sheet kelola opsi / pengaturan field dari aksi kartu — jangan toggle-off bila sudah terpilih. */
    function openFieldManage(id: string): void {
        selectedFieldId.value = id;
        inspectorMode.value = 'field';
        showMobileEditor.value = true;
    }

    function duplicateField(id: string): void {
        const copy = fieldList.duplicateField(id);
        if (copy === null) return;
        selectedFieldId.value = copy.id;
        inspectorMode.value = 'field';
    }

    function updateField(updated: IBuilderField): void {
        fieldList.updateField(updated);
    }

    function moveField(id: string, direction: -1 | 1): void {
        const indexes = sectionFieldIndexes(activeSectionId.value);
        const localIndex = currentSectionFields.value.findIndex((field) => field.id === id);
        if (localIndex === -1) return;
        const source = indexes[localIndex];
        const target = indexes[localIndex + direction];
        if (source === undefined || target === undefined) return;
        fieldList.moveFieldToIndex(source, target);
    }

    function toggleVisibility(value: string, checked: boolean): void {
        if (checked) models.visibleFor.value = [...models.visibleFor.value, value];
        else models.visibleFor.value = models.visibleFor.value.filter((v) => v !== value);
    }

    function toggleCategory(cat: IFormBuilderPaletteCategory): void {
        openCategoryName.value = openCategoryName.value === cat.name ? null : cat.name;
        // Sinkronkan isOpen agar cocok dengan state yang dipakai item kartu.
        for (const c of categories.value) {
            c.isOpen = c.name === openCategoryName.value;
        }
    }

    function onGapDragEnter(index: number): void {
        dropIndicatorIndex.value = index;
    }

    function onCanvasDragOver(e: DragEvent): void {
        e.preventDefault();
        const dt = e.dataTransfer;
        if (dt) {
            /** Drag dari palet: belum ada dragSourceId; tandai agar UI drop (ring kanvas) muncul */
            if (!dragSourceId.value && Array.from(dt.types).includes('application/json')) {
                isDraggingOverCanvas.value = true;
            }
            dt.dropEffect = dragSourceId.value ? 'move' : 'copy';
        }
        if (dropIndicatorIndex.value === -1 && isCurrentSectionEmpty.value) dropIndicatorIndex.value = 0;
    }

    function onCanvasDragLeave(e: DragEvent): void {
        if (!e.currentTarget || !(e.currentTarget as HTMLElement).contains(e.relatedTarget as Node)) {
            dropIndicatorIndex.value = -1;
            isDraggingOverCanvas.value = false;
        }
    }

    function onCanvasDrop(e: DragEvent): void {
        e.preventDefault();
        const raw = e.dataTransfer?.getData('application/json');
        if (!raw) return;
        const data = parseFormBuilderDragPayload(raw);
        if (data === null) return;
        // Drop item "Pesan setelah submit" = trigger zona konfirmasi di akhir canvas, bukan IBuilderField.
        if (data.isNew && data.type === 'confirmation') {
            showSuccessZone.value = true;
            dropIndicatorIndex.value = -1;
            isDraggingOverCanvas.value = false;
            dragSourceId.value = null;
            return;
        }
        const insertAt =
            dropIndicatorIndex.value < 0
                ? globalInsertIndex(activeSectionId.value, currentSectionFields.value.length)
                : globalInsertIndex(activeSectionId.value, dropIndicatorIndex.value);
        if (data.isNew) {
            if (data.type === undefined || data.label === undefined || data.label === '') return;
            const nf = fieldList.insertNewField(data.type, data.label, insertAt);
            selectedFieldId.value = nf.id;
            inspectorMode.value = 'field';
        } else if (data.id !== undefined) {
            const from = fieldList.indexOf(data.id);
            if (from === -1) return;
            fieldList.moveFieldToIndex(from, insertAt);
            selectedFieldId.value = data.id;
            inspectorMode.value = 'field';
        }
        dropIndicatorIndex.value = -1;
        isDraggingOverCanvas.value = false;
        dragSourceId.value = null;
    }

    function onCanvasDragStart(e: DragEvent, field: IBuilderField, index: number): void {
        dragSourceId.value = field.id;
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData(
                'application/json',
                JSON.stringify({ id: field.id, fromIndex: index, isNew: false })
            );
        }
        requestAnimationFrame(() => {
            isDraggingOverCanvas.value = true;
        });
    }

    function onDragEnd(): void {
        dropIndicatorIndex.value = -1;
        isDraggingOverCanvas.value = false;
        dragSourceId.value = null;
    }

    function requestSave(): void {
        if (validationIssues.value.length > 0) {
            showErrorToast(`Field belum lengkap: ${validationIssues.value.map((i) => i.label).join(', ')}`, {
                title: 'Form belum siap disimpan',
            });
            mobileTab.value = 'settings';
            inspectorMode.value = 'settings';
            return;
        }
        options.onSave();
    }

    return {
        categories,
        searchQuery,
        filteredCategories,
        openCategoryName,
        showSuccessZone,
        selectedFieldId,
        selectedField,
        dropIndicatorIndex,
        isDraggingOverCanvas,
        dragSourceId,
        inspectorMode,
        mobileTab,
        showAddSheet,
        showMobileEditor,
        showPreview,
        isEmpty,
        isCurrentSectionEmpty,
        sections,
        currentSectionFields,
        currentSectionIndex,
        addSection,
        goToSection,
        bannerPreviewSrc,
        validationIssues,
        isReadyToSave,
        patchBanner,
        addField,
        selectField,
        openFieldManage,
        deleteField,
        duplicateField,
        updateField,
        moveField,
        toggleVisibility,
        toggleCategory,
        hideSuccessZone,
        onGapDragEnter,
        onCanvasDragOver,
        onCanvasDragLeave,
        onCanvasDrop,
        onCanvasDragStart,
        onDragEnd,
        requestSave,
    };
}
