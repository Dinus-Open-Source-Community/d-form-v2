import { ref, computed, watch, type Ref } from 'vue'
import { showErrorToast } from '@/lib/error-message'
import { DEFAULT_FORM_SECTION_ID, defaultFormBuilderSections } from '@/lib/formRegistrationMetadata'
import { normalizeBannerSrc, type FormBannerState } from '@/components/modules/builder/formBanner'
import {
    cloneFormBuilderPalette,
    type FormBuilderPaletteCategory,
    type FormBuilderPaletteField,
} from '@/components/modules/builder/formBuilderPalette'
import { createFormBuilderField } from '@/components/modules/builder/formBuilderFieldFactory'
import type { BuilderField, IFormBuilderSection } from '@/types/form-builder'
import type { FormRegistrationMetadata } from '@/types/form'

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
    formMetadata: Ref<FormRegistrationMetadata>
    successContent?: Ref<string>
}

/** Kategori palette terbuka. `null` = semua tertutup (single-expand). */
export function useFormBuilderWorkspace(
    models: FormBuilderWorkspaceModels,
    options: { onSave: () => void },
) {
    const categories = ref<FormBuilderPaletteCategory[]>(cloneFormBuilderPalette())

    /** Single-expand: simpan nama kategori yang terbuka (default semua tertutup). */
    const openCategoryName = ref<string | null>(null)

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
    const currentSectionId = ref<string>(DEFAULT_FORM_SECTION_ID)

    const sections = computed<IFormBuilderSection[]>(() => {
        const configured = models.formMetadata.value.sections
        return configured.length > 0 ? configured : defaultFormBuilderSections()
    })

    const currentSectionIndex = computed<number>(() => {
        const index = sections.value.findIndex((section) => section.id === currentSectionId.value)
        return index >= 0 ? index : 0
    })

    const activeSectionId = computed<string>(() => sections.value[currentSectionIndex.value]?.id ?? DEFAULT_FORM_SECTION_ID)

    function sectionIdForField(field: BuilderField): string {
        const sectionId = field.metadata.sectionId
        if (
            typeof sectionId === 'string' &&
            sectionId.trim() !== '' &&
            sections.value.some((section) => section.id === sectionId)
        ) {
            return sectionId
        }
        return sections.value[0]?.id ?? DEFAULT_FORM_SECTION_ID
    }

    const currentSectionFields = computed<BuilderField[]>(() =>
        models.formFields.value.filter((field) => sectionIdForField(field) === activeSectionId.value),
    )

    function ensureSections(): IFormBuilderSection[] {
        if (models.formMetadata.value.sections.length > 0) return models.formMetadata.value.sections
        const fallback = defaultFormBuilderSections()
        models.formMetadata.value = { ...models.formMetadata.value, sections: fallback }
        return fallback
    }

    function addSection(): void {
        const configured = ensureSections()
        const section: IFormBuilderSection = {
            id: crypto.randomUUID(),
            title: `Bagian ${configured.length + 1}`,
        }
        models.formMetadata.value = {
            ...models.formMetadata.value,
            sections: [...configured, section],
        }
        currentSectionId.value = section.id
        selectedFieldId.value = null
        inspectorMode.value = 'settings'
    }

    function goToSection(index: number): void {
        const target = sections.value[index]
        if (!target) return
        currentSectionId.value = target.id
        selectedFieldId.value = null
        inspectorMode.value = 'settings'
    }

    function assignFieldToSection(field: BuilderField, sectionId: string): BuilderField {
        return { ...field, metadata: { ...field.metadata, sectionId } }
    }

    function sectionFieldIndexes(sectionId: string): number[] {
        return models.formFields.value.flatMap((field, index) => (sectionIdForField(field) === sectionId ? [index] : []))
    }

    function globalInsertIndex(sectionId: string, localIndex: number): number {
        const indexes = sectionFieldIndexes(sectionId)
        const target = indexes[localIndex]
        if (target !== undefined) return target
        const last = indexes[indexes.length - 1]
        return last === undefined ? models.formFields.value.length : last + 1
    }

    watch(
        sections,
        (configured) => {
            if (!configured.some((section) => section.id === currentSectionId.value)) {
                currentSectionId.value = configured[0]?.id ?? DEFAULT_FORM_SECTION_ID
            }
        },
        { immediate: true },
    )

    /** Zona "Pesan setelah submit" (ala Google Forms). `true` = canvas menampilkan zona (dipicu drag item palette). */
    const showSuccessZone = ref(false)

    // Edit form tersimpan: konten sudah ada saat mount → tampilkan zona.
    const initialSuccess = models.successContent?.value ?? ''
    if (initialSuccess.replace(/<[^>]*>/g, '').replace(/&nbsp;/gi, ' ').trim() !== '') {
        showSuccessZone.value = true
    }

    const filteredCategories = computed(() => {
        const q = searchQuery.value.toLowerCase().trim()
        if (!q) return categories.value
        // Mode pencarian: tampilkan semua kategori yang cocok (abaikan single-expand).
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
    const isCurrentSectionEmpty = computed<boolean>(() => currentSectionFields.value.length === 0)
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

    function hideSuccessZone(): void {
        showSuccessZone.value = false
        if (models.successContent) models.successContent.value = ''
    }

    function addField(template: FormBuilderPaletteField, openEditorAfter = false): void {
        // Item palette "Pesan setelah submit" = trigger zona konfirmasi, bukan BuilderField.
        if (template.type === 'confirmation') {
            showSuccessZone.value = true
            showAddSheet.value = false
            if (openEditorAfter) showMobileEditor.value = true
            return
        }
        const nf = createFormBuilderField(template.type, template.label)
        models.formFields.value = [...models.formFields.value, assignFieldToSection(nf, activeSectionId.value)]
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

    /** Buka sheet kelola opsi / pengaturan field dari aksi kartu — jangan toggle-off bila sudah terpilih. */
    function openFieldManage(id: string): void {
        selectedFieldId.value = id
        inspectorMode.value = 'field'
        showMobileEditor.value = true
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
        const indexes = sectionFieldIndexes(activeSectionId.value)
        const localIndex = currentSectionFields.value.findIndex((field) => field.id === id)
        if (localIndex === -1) return
        const targetLocalIndex = localIndex + direction
        const target = indexes[targetLocalIndex]
        const source = indexes[localIndex]
        if (source === undefined || target === undefined) return
        const next = [...models.formFields.value]
        const [moved] = next.splice(source, 1)
        next.splice(target > source ? target - 1 : target, 0, moved)
        models.formFields.value = next
    }

    function toggleVisibility(value: string, checked: boolean): void {
        if (checked) models.visibleFor.value = [...models.visibleFor.value, value]
        else models.visibleFor.value = models.visibleFor.value.filter((v) => v !== value)
    }

    function toggleCategory(cat: FormBuilderPaletteCategory): void {
        openCategoryName.value = openCategoryName.value === cat.name ? null : cat.name
        // Sinkronkan isOpen agar cocok dengan state yang dipakai item kartu.
        for (const c of categories.value) {
            c.isOpen = c.name === openCategoryName.value
        }
    }

    function onGapDragEnter(index: number): void {
        dropIndicatorIndex.value = index
    }

    function onCanvasDragOver(e: DragEvent): void {
        e.preventDefault()
        const dt = e.dataTransfer
        if (dt) {
            /** Drag dari palet: belum ada dragSourceId; tandai agar UI drop (ring kanvas) muncul */
            if (!dragSourceId.value && Array.from(dt.types).includes('application/json')) {
                isDraggingOverCanvas.value = true
            }
            dt.dropEffect = dragSourceId.value ? 'move' : 'copy'
        }
        if (dropIndicatorIndex.value === -1 && isCurrentSectionEmpty.value) dropIndicatorIndex.value = 0
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
        // Drop item "Pesan setelah submit" = trigger zona konfirmasi di akhir canvas, bukan BuilderField.
        if (data.isNew && data.type === 'confirmation') {
            showSuccessZone.value = true
            dropIndicatorIndex.value = -1
            isDraggingOverCanvas.value = false
            dragSourceId.value = null
            return
        }
        const insertAt =
            dropIndicatorIndex.value < 0
                ? globalInsertIndex(activeSectionId.value, currentSectionFields.value.length)
                : globalInsertIndex(activeSectionId.value, dropIndicatorIndex.value)
        const list = [...models.formFields.value]
        if (data.isNew && data.type && data.label) {
            const nf = assignFieldToSection(createFormBuilderField(data.type as FormBuilderType, data.label), activeSectionId.value)
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
            showErrorToast(`Field belum lengkap: ${validationIssues.value.map((i) => i.label).join(', ')}`, {
                title: 'Form belum siap disimpan',
            })
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
    }
}
