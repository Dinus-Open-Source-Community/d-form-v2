import { computed, ref, reactive } from 'vue';
import { defaultFormBannerState, type IFormBannerState } from '@/components/modules/builder/formBanner';
import { createDemoBuilderField } from '@/lib/formBuilderDemoFieldFactory';
import { createFormBuilderFieldList, parseFormBuilderDragPayload } from '@/lib/formBuilderFieldList';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import {
    cloneFormBuilderPalette,
    ALL_FORM_BUILDER_FIELD_TEMPLATES,
    type IFormBuilderPaletteCategory,
    type IFormBuilderPaletteField,
} from '@/components/modules/builder/formBuilderPalette';

/**
 * State form builder versi demo (tanpa backend): palette, drag-and-drop, dan edit field.
 * Mutasi daftar field memakai `createFormBuilderFieldList` yang sama dengan
 * entry point production; perbedaan demo hanya pada factory field dan suffix duplikat.
 */
export function useFormBuilderDemoPage() {
    const categories = ref<IFormBuilderPaletteCategory[]>(cloneFormBuilderPalette());
    const searchQuery = ref('');
    const formTitle = ref('Untitled Form');
    const formDescription = ref('Describe what this form is for and what information you need.');
    const formFields = ref<IFormField[]>([]);
    const formBanner = reactive(defaultFormBannerState());
    const selectedFieldId = ref<string | null>(null);

    const dropIndicatorIndex = ref(-1);
    const isDraggingOverCanvas = ref(false);
    const dragSourceId = ref<string | null>(null);
    const mobileFieldType = ref('');
    const allFieldTypes = ALL_FORM_BUILDER_FIELD_TEMPLATES;

    const fieldList = createFormBuilderFieldList<IFormField>({
        store: {
            read: (): IFormField[] => formFields.value,
            commit: (next: IFormField[]): void => {
                formFields.value = next;
            },
        },
        createField: createDemoBuilderField,
        decorateDuplicate: (copy: IFormField, original: IFormField): IFormField => ({
            ...copy,
            label: `${original.label} (copy)`,
        }),
    });

    const filteredCategories = computed(() => {
        const q = searchQuery.value.toLowerCase().trim();
        if (!q) return categories.value;
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

    const selectedField = computed(() => {
        if (!selectedFieldId.value) return null;
        return formFields.value.find((f) => f.id === selectedFieldId.value) ?? null;
    });

    const isEmpty = computed(() => formFields.value.length === 0);
    const bannerPreviewSrc = computed(() => normalizePublicStorageUrl(formBanner.bannerUrl));

    function addFieldFromPicker(): void {
        const picked = allFieldTypes.find((field) => field.type === mobileFieldType.value);
        if (!picked) return;
        const field = fieldList.appendField(picked.type, picked.label);
        selectedFieldId.value = field.id;
        mobileFieldType.value = '';
    }

    function onSidebarDragStart(e: DragEvent, fieldData: IFormBuilderPaletteField): void {
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'copy';
            e.dataTransfer.setData(
                'application/json',
                JSON.stringify({ type: fieldData.type, label: fieldData.label, isNew: true })
            );
        }
    }

    function onCanvasDragStart(e: DragEvent, field: IFormField, index: number): void {
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

    function onGapDragEnter(index: number): void {
        dropIndicatorIndex.value = index;
    }

    function onCanvasDragOver(e: DragEvent): void {
        e.preventDefault();
        if (e.dataTransfer) {
            e.dataTransfer.dropEffect = isDraggingOverCanvas.value ? 'move' : 'copy';
        }
        if (dropIndicatorIndex.value === -1 && formFields.value.length === 0) {
            dropIndicatorIndex.value = 0;
        }
    }

    function onCanvasDragLeave(e: DragEvent): void {
        if (e.currentTarget && !(e.currentTarget as HTMLElement).contains(e.relatedTarget as Node)) {
            dropIndicatorIndex.value = -1;
            isDraggingOverCanvas.value = false;
        }
    }

    function onCanvasDrop(e: DragEvent): void {
        e.preventDefault();
        const raw = e.dataTransfer?.getData('application/json');
        if (!raw) return;

        const payload = parseFormBuilderDragPayload(raw);
        if (payload === null) return;

        const insertAt = dropIndicatorIndex.value < 0 ? formFields.value.length : dropIndicatorIndex.value;

        if (payload.isNew) {
            if (payload.type === undefined || payload.label === undefined || payload.label === '') return;
            const field = fieldList.insertNewField(payload.type, payload.label, insertAt);
            selectedFieldId.value = field.id;
        } else if (payload.id !== undefined) {
            const fromIndex = fieldList.indexOf(payload.id);
            if (fromIndex === -1) return;
            fieldList.moveFieldToIndex(fromIndex, insertAt);
            selectedFieldId.value = payload.id;
        }

        dropIndicatorIndex.value = -1;
        isDraggingOverCanvas.value = false;
        dragSourceId.value = null;
    }

    function onDragEnd(): void {
        dropIndicatorIndex.value = -1;
        isDraggingOverCanvas.value = false;
        dragSourceId.value = null;
    }

    function selectField(id: string): void {
        selectedFieldId.value = selectedFieldId.value === id ? null : id;
    }

    function deleteField(id: string): void {
        const wasSelected = selectedFieldId.value === id;
        if (!fieldList.deleteField(id)) return;
        if (wasSelected) selectedFieldId.value = null;
    }

    function duplicateField(id: string): void {
        const copy = fieldList.duplicateField(id);
        if (copy !== null) selectedFieldId.value = copy.id;
    }

    function updateField(updatedField: IFormField): void {
        fieldList.updateField(updatedField);
    }

    function toggleCategory(index: number): void {
        categories.value[index].isOpen = !categories.value[index].isOpen;
    }

    function patchBanner(v: IFormBannerState): void {
        Object.assign(formBanner, v);
    }

    return {
        categories,
        searchQuery,
        formTitle,
        formDescription,
        formFields,
        formBanner,
        selectedFieldId,
        selectedField,
        dropIndicatorIndex,
        isDraggingOverCanvas,
        dragSourceId,
        mobileFieldType,
        allFieldTypes,
        filteredCategories,
        isEmpty,
        bannerPreviewSrc,
        addFieldFromPicker,
        onSidebarDragStart,
        onCanvasDragStart,
        onGapDragEnter,
        onCanvasDragOver,
        onCanvasDragLeave,
        onCanvasDrop,
        onDragEnd,
        selectField,
        deleteField,
        duplicateField,
        updateField,
        toggleCategory,
        patchBanner,
    };
}
