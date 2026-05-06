import { ref, computed, type Ref } from 'vue'
import { toast } from 'vue-sonner'
import { normalizeBannerSrc, type FormBannerState } from '@/components/modules/builder/formBanner'
import {
    cloneFormBuilderPalette,
    type FormBuilderPaletteCategory,
    type FormBuilderPaletteField,
} from '@/components/modules/builder/formBuilderPalette'
import { createFormBuilderField } from '@/components/modules/builder/formBuilderFieldFactory'
import type { BuilderField } from '@/types/form-builder'

export type FormBuilderInspectorMode = 'settings' | 'field'
export type FormBuilderMobileTab = 'build' | 'settings'

export interface FormBuilderValidationIssue {
    key: 'title' | 'description' | 'closedAt' | 'visibleFor' | 'fields'
    label: string
}

export interface FormBuilderWorkspaceModels {
    formTitle: Ref<string>
    formDescription: Ref<string>
    closedAt: Ref<string>
    visibleFor: Ref<string[]>
    banner: Ref<FormBannerState>
    formFields: Ref<BuilderField[]>
}

export function useFormBuilderWorkspace(
    models: FormBuilderWorkspaceModels,
    options: { onSave: () => void },
) {
    const categories = ref<FormBuilderPaletteCategory[]>(cloneFormBuilderPalette())

    const searchQuery = ref<string>('')
    const selectedFieldId = ref<string | null>(null)
    const dropIndicatorIndex = ref<number>(-1)
    const isDraggingOverCanvas = ref<boolean>(false)
    const dragSourceId = ref<string | null>(null)
    const inspectorMode = ref<FormBuilderInspectorMode>('settings')
    const mobileTab = ref<FormBuilderMobileTab>('build')
    const showAddSheet = ref<boolean>(false)
    const showMobileEditor = ref<boolean>(false)
    const showPreview = ref<boolean>(false)

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

    const selectedField = computed<BuilderField | null>(
        () => models.formFields.value.find((f) => f.id === selectedFieldId.value) ?? null,
    )

    const isEmpty = computed<boolean>(() => models.formFields.value.length === 0)
    const bannerPreviewSrc = computed<string>(() => normalizeBannerSrc(models.banner.value.bannerUrl))

    const validationIssues = computed<FormBuilderValidationIssue[]>(() => {
        const issues: FormBuilderValidationIssue[] = []
        if (!models.formTitle.value.trim()) issues.push({ key: 'title', label: 'Form title' })
        if (!models.formDescription.value.trim()) issues.push({ key: 'description', label: 'Description' })
        if (!models.closedAt.value) issues.push({ key: 'closedAt', label: 'Close date' })
        if (models.visibleFor.value.length === 0) issues.push({ key: 'visibleFor', label: 'Visibility' })
        if (models.formFields.value.length === 0) issues.push({ key: 'fields', label: 'At least one field' })
        return issues
    })

    const isReadyToSave = computed<boolean>(() => validationIssues.value.length === 0)

    function patchBanner(v: FormBannerState): void {
        Object.assign(models.banner.value, v)
    }

    function addField(template: FormBuilderPaletteField, openEditorAfter = false): void {
        const nf = createFormBuilderField(template.type, template.label)
        models.formFields.value = [...models.formFields.value, nf]
        selectedFieldId.value = nf.id
        inspectorMode.value = 'field'
        showAddSheet.value = false
        if (openEditorAfter) showMobileEditor.value = true
    }

    function selectField(id: string, isMobile = false): void {
        if (selectedFieldId.value === id && !isMobile) {
            selectedFieldId.value = null
            inspectorMode.value = 'settings'
            return
        }
        selectedFieldId.value = id
        inspectorMode.value = 'field'
        if (isMobile) showMobileEditor.value = true
    }

    function deleteField(id: string): void {
        models.formFields.value = models.formFields.value.filter((f) => f.id !== id)
        if (selectedFieldId.value === id) {
            selectedFieldId.value = null
            inspectorMode.value = 'settings'
            showMobileEditor.value = false
        }
    }

    function duplicateField(id: string): void {
        const i = models.formFields.value.findIndex((f) => f.id === id)
        if (i === -1) return
        const copy = JSON.parse(JSON.stringify(models.formFields.value[i])) as BuilderField
        copy.id = crypto.randomUUID()
        copy.name = `field_${crypto.randomUUID().replace(/-/g, '').slice(0, 12)}`
        const next = [...models.formFields.value]
        next.splice(i + 1, 0, copy)
        models.formFields.value = next
        selectedFieldId.value = copy.id
        inspectorMode.value = 'field'
    }

    function updateField(updated: BuilderField): void {
        const i = models.formFields.value.findIndex((f) => f.id === updated.id)
        if (i === -1) return
        const next = [...models.formFields.value]
        next[i] = updated
        models.formFields.value = next
    }

    function moveField(id: string, direction: -1 | 1): void {
        const i = models.formFields.value.findIndex((f) => f.id === id)
        if (i === -1) return
        const target = i + direction
        if (target < 0 || target >= models.formFields.value.length) return
        const next = [...models.formFields.value]
        const [moved] = next.splice(i, 1)
        next.splice(target, 0, moved)
        models.formFields.value = next
    }

    function toggleVisibility(value: string, checked: boolean): void {
        if (checked) models.visibleFor.value = [...models.visibleFor.value, value]
        else models.visibleFor.value = models.visibleFor.value.filter((v) => v !== value)
    }

    function toggleCategory(cat: FormBuilderPaletteCategory): void {
        cat.isOpen = !cat.isOpen
    }

    function onGapDragEnter(index: number): void {
        dropIndicatorIndex.value = index
    }

    function onCanvasDragOver(e: DragEvent): void {
        e.preventDefault()
        if (e.dataTransfer) e.dataTransfer.dropEffect = isDraggingOverCanvas.value ? 'move' : 'copy'
        if (dropIndicatorIndex.value === -1 && isEmpty.value) dropIndicatorIndex.value = 0
    }

    function onCanvasDragLeave(e: DragEvent): void {
        if (!e.currentTarget || !(e.currentTarget as HTMLElement).contains(e.relatedTarget as Node)) {
            dropIndicatorIndex.value = -1
            isDraggingOverCanvas.value = false
        }
    }

    function onCanvasDrop(e: DragEvent): void {
        e.preventDefault()
        const raw = e.dataTransfer?.getData('application/json')
        if (!raw) return
        const data = JSON.parse(raw) as { isNew?: boolean; type?: string; label?: string; id?: string }
        const insertAt =
            dropIndicatorIndex.value < 0 ? models.formFields.value.length : dropIndicatorIndex.value
        const list = [...models.formFields.value]
        if (data.isNew && data.type && data.label) {
            const nf = createFormBuilderField(data.type as FormBuilderType, data.label)
            list.splice(insertAt, 0, nf)
            models.formFields.value = list
            selectedFieldId.value = nf.id
            inspectorMode.value = 'field'
        } else if (data.id) {
            const from = list.findIndex((f) => f.id === data.id)
            if (from === -1) return
            const [moved] = list.splice(from, 1)
            list.splice(insertAt > from ? insertAt - 1 : insertAt, 0, moved)
            models.formFields.value = list
            selectedFieldId.value = moved.id
            inspectorMode.value = 'field'
        }
        dropIndicatorIndex.value = -1
        isDraggingOverCanvas.value = false
        dragSourceId.value = null
    }

    function onCanvasDragStart(e: DragEvent, field: BuilderField, index: number): void {
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

    function onDragEnd(): void {
        dropIndicatorIndex.value = -1
        isDraggingOverCanvas.value = false
        dragSourceId.value = null
    }

    function requestSave(): void {
        if (validationIssues.value.length > 0) {
            toast.error(`Missing: ${validationIssues.value.map((i) => i.label).join(', ')}`)
            mobileTab.value = 'settings'
            inspectorMode.value = 'settings'
            return
        }
        options.onSave()
    }

    return {
        categories,
        searchQuery,
        filteredCategories,
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
        bannerPreviewSrc,
        validationIssues,
        isReadyToSave,
        patchBanner,
        addField,
        selectField,
        deleteField,
        duplicateField,
        updateField,
        moveField,
        toggleVisibility,
        toggleCategory,
        onGapDragEnter,
        onCanvasDragOver,
        onCanvasDragLeave,
        onCanvasDrop,
        onCanvasDragStart,
        onDragEnd,
        requestSave,
    }
}
