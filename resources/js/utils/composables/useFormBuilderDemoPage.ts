import { computed, ref, reactive } from 'vue'
import { defaultFormBannerState, normalizeBannerSrc, type FormBannerState } from '@/components/modules/builder/formBanner'
import {
    cloneFormBuilderPalette,
    ALL_FORM_BUILDER_FIELD_TEMPLATES,
    type FormBuilderPaletteCategory,
    type FormBuilderPaletteField,
} from '@/components/modules/builder/formBuilderPalette'

export function useFormBuilderDemoPage() {
    const categories = ref<FormBuilderPaletteCategory[]>(cloneFormBuilderPalette())
    const searchQuery = ref('')
    const formTitle = ref('Untitled Form')
    const formDescription = ref('Describe what this form is for and what information you need.')
    const formFields = ref<IFormField[]>([])
    const formBanner = reactive(defaultFormBannerState())
    const selectedFieldId = ref<string | null>(null)

    const dropIndicatorIndex = ref(-1)
    const isDraggingOverCanvas = ref(false)
    const dragSourceId = ref<string | null>(null)
    const mobileFieldType = ref('')
    const allFieldTypes = ALL_FORM_BUILDER_FIELD_TEMPLATES

    const filteredCategories = computed(() => {
        const q = searchQuery.value.toLowerCase().trim()
        if (!q) return categories.value
        return categories.value
            .map((cat) => ({
                ...cat,
                isOpen: true,
                fields: cat.fields.filter(
                    (f) =>
                        f.label.toLowerCase().includes(q) ||
                        f.description.toLowerCase().includes(q) ||
                        f.type.includes(q),
                ),
            }))
            .filter((cat) => cat.fields.length > 0)
    })

    const selectedField = computed(() => {
        if (!selectedFieldId.value) return null
        return formFields.value.find((f) => f.id === selectedFieldId.value) ?? null
    })

    const isEmpty = computed(() => formFields.value.length === 0)
    const bannerPreviewSrc = computed(() => normalizeBannerSrc(formBanner.bannerUrl))

    function createField(type: string, label: string): IFormField {
        const defaults: Record<string, Partial<IFormField>> = {
            short_text: { metadata: { placeholder: '' } },
            long_text: { metadata: { placeholder: '' } },
            email: { metadata: { placeholder: '' } },
            phone: { metadata: { placeholder: '' } },
            number: { metadata: { placeholder: '' } },
            dropdown: { metadata: { options: 'Option 1, Option 2' } },
            checkbox: { metadata: { options: 'Choice A, Choice B, Choice C' } },
            radio: { metadata: { options: 'Option 1, Option 2, Option 3' } },
            image_upload: { metadata: { accepts: 'png, jpg, jpeg' } },
            file_upload: { metadata: { accepts: 'pdf, doc, xls' } },
            rating: { metadata: { maxStars: 5 } },
            heading: { metadata: { content: 'Section Heading' } },
            paragraph: { metadata: { content: '' } },
        }
        return {
            id: crypto.randomUUID(),
            type: type as IFormField['type'],
            label: label || 'Untitled Field',
            name: `field_${crypto.randomUUID().replace(/-/g, '').slice(0, 12)}`,
            description: '',
            order: 0,
            metadata: {},
            ...(defaults[type] || {}),
        } as IFormField
    }

    function addFieldFromPicker(): void {
        const picked = allFieldTypes.find((field) => field.type === mobileFieldType.value)
        if (!picked) return
        const nf = createField(picked.type, picked.label)
        formFields.value.push(nf)
        selectedFieldId.value = nf.id
        mobileFieldType.value = ''
    }

    function onSidebarDragStart(e: DragEvent, fieldData: FormBuilderPaletteField): void {
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'copy'
            e.dataTransfer.setData(
                'application/json',
                JSON.stringify({ type: fieldData.type, label: fieldData.label, isNew: true }),
            )
        }
    }

    function onCanvasDragStart(e: DragEvent, field: IFormField, index: number): void {
        dragSourceId.value = field.id
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move'
            e.dataTransfer.setData(
                'application/json',
                JSON.stringify({ id: field.id, fromIndex: index, isNew: false }),
            )
        }
        requestAnimationFrame(() => {
            isDraggingOverCanvas.value = true
        })
    }

    function onGapDragEnter(index: number): void {
        dropIndicatorIndex.value = index
    }

    function onCanvasDragOver(e: DragEvent): void {
        e.preventDefault()
        if (e.dataTransfer) {
            e.dataTransfer.dropEffect = isDraggingOverCanvas.value ? 'move' : 'copy'
        }
        if (dropIndicatorIndex.value === -1 && formFields.value.length === 0) {
            dropIndicatorIndex.value = 0
        }
    }

    function onCanvasDragLeave(e: DragEvent): void {
        if (e.currentTarget && !(e.currentTarget as HTMLElement).contains(e.relatedTarget as Node)) {
            dropIndicatorIndex.value = -1
            isDraggingOverCanvas.value = false
        }
    }

    function onCanvasDrop(e: DragEvent): void {
        e.preventDefault()
        const raw = e.dataTransfer?.getData('application/json')
        if (!raw) return

        const data = JSON.parse(raw)
        let insertAt = dropIndicatorIndex.value
        if (insertAt < 0) insertAt = formFields.value.length

        if (data.isNew) {
            const newField = createField(data.type, data.label)
            formFields.value.splice(insertAt, 0, newField)
            selectedFieldId.value = newField.id
        } else {
            const fromIndex = formFields.value.findIndex((f) => f.id === data.id)
            if (fromIndex === -1) return
            const [moved] = formFields.value.splice(fromIndex, 1)
            const adjustedInsert = insertAt > fromIndex ? insertAt - 1 : insertAt
            formFields.value.splice(adjustedInsert, 0, moved)
            selectedFieldId.value = moved.id
        }

        dropIndicatorIndex.value = -1
        isDraggingOverCanvas.value = false
        dragSourceId.value = null
    }

    function onDragEnd(): void {
        dropIndicatorIndex.value = -1
        isDraggingOverCanvas.value = false
        dragSourceId.value = null
    }

    function selectField(id: string): void {
        selectedFieldId.value = selectedFieldId.value === id ? null : id
    }

    function deleteField(id: string): void {
        formFields.value = formFields.value.filter((f) => f.id !== id)
        if (selectedFieldId.value === id) selectedFieldId.value = null
    }

    function duplicateField(id: string): void {
        const index = formFields.value.findIndex((f) => f.id === id)
        if (index === -1) return
        const original = formFields.value[index]
        const copy: IFormField = {
            ...JSON.parse(JSON.stringify(original)),
            id: crypto.randomUUID(),
            label: `${original.label} (copy)`,
            name: `field_${crypto.randomUUID().replace(/-/g, '').slice(0, 12)}`,
        }
        formFields.value.splice(index + 1, 0, copy)
        selectedFieldId.value = copy.id
    }

    function updateField(updatedField: IFormField): void {
        const index = formFields.value.findIndex((f) => f.id === updatedField.id)
        if (index !== -1) {
            formFields.value[index] = updatedField
        }
    }

    function toggleCategory(index: number): void {
        categories.value[index].isOpen = !categories.value[index].isOpen
    }

    function patchBanner(v: FormBannerState): void {
        Object.assign(formBanner, v)
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
    }
}
