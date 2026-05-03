<script setup lang="ts">
import { ref, computed, reactive, nextTick } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import DraggableItem from '@/components/modules/builder/DraggableItem.vue'
import FieldRenderer from '@/components/modules/builder/FieldRenderer.vue'
import FieldEditor from '@/components/modules/builder/FieldEditor.vue'
import FormBannerSettings from '@/components/modules/builder/FormBannerSettings.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import LocalLottie from '@/components/core/LocalLottie.vue'
import { defaultFormBannerState, normalizeBannerSrc, type FormBannerState } from '@/components/modules/builder/formBanner'
import {
    Type, AlignLeft, Mail, Phone, Hash,
    ChevronDown, SquareCheck, CircleDot,
    ImagePlus, Upload,
    Calendar, Clock, Star,
    Heading as HeadingIcon, TextCursorInput, Minus,
    GripVertical, ChevronRight, Search, Eye, Save,
    Undo2, Redo2, Smartphone, Sparkles, Plus,
} from 'lucide-vue-next'

defineOptions({ layout: DashboardFocusLayout })

// ─── Field Categories for Sidebar Palette ───────────────────────
const fieldCategories = [
    {
        name: 'Text Inputs',
        icon: Type,
        isOpen: true,
        fields: [
            { type: 'short_text', label: 'Short Text', icon: Type, description: 'Single line text' },
            { type: 'long_text', label: 'Long Text', icon: AlignLeft, description: 'Multi-line text area' },
            { type: 'email', label: 'Email', icon: Mail, description: 'Email address input' },
            { type: 'phone', label: 'Phone', icon: Phone, description: 'Phone number input' },
            { type: 'number', label: 'Number', icon: Hash, description: 'Numeric value input' },
        ],
    },
    {
        name: 'Choice',
        icon: ChevronDown,
        isOpen: true,
        fields: [
            { type: 'dropdown', label: 'Dropdown', icon: ChevronDown, description: 'Select from a list' },
            { type: 'checkbox', label: 'Checkbox', icon: SquareCheck, description: 'Multiple selection' },
            { type: 'radio', label: 'Radio', icon: CircleDot, description: 'Single selection' },
        ],
    },
    {
        name: 'Media',
        icon: ImagePlus,
        isOpen: true,
        fields: [
            { type: 'image_upload', label: 'Image Upload', icon: ImagePlus, description: 'Upload an image file' },
            { type: 'file_upload', label: 'File Upload', icon: Upload, description: 'Upload any document' },
        ],
    },
    {
        name: 'Date & Time',
        icon: Calendar,
        isOpen: false,
        fields: [
            { type: 'date', label: 'Date', icon: Calendar, description: 'Date picker' },
            { type: 'time', label: 'Time', icon: Clock, description: 'Time picker' },
        ],
    },
    {
        name: 'Content',
        icon: TextCursorInput,
        isOpen: false,
        fields: [
            { type: 'heading', label: 'Heading', icon: HeadingIcon, description: 'Section title' },
            { type: 'paragraph', label: 'Paragraph', icon: TextCursorInput, description: 'Descriptive text block' },
            { type: 'divider', label: 'Divider', icon: Minus, description: 'Visual separator line' },
            { type: 'rating', label: 'Star Rating', icon: Star, description: 'Rate with stars' },
        ],
    },
]

// ─── State ──────────────────────────────────────────────────────
const categories = ref(fieldCategories.map((c) => ({ ...c, isOpen: c.isOpen })))
const searchQuery = ref('')

const formTitle = ref('Untitled Form')
const formDescription = ref('Describe what this form is for and what information you need.')
const formFields = ref([])
const formBanner = reactive(defaultFormBannerState())
const selectedFieldId = ref(null)

// DnD state
const dropIndicatorIndex = ref(-1)
const isDraggingOverCanvas = ref(false)
const dragSourceId = ref(null)

// ─── Computed ───────────────────────────────────────────────────
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
const mobileFieldType = ref('')
const allFieldTypes = computed(() => fieldCategories.flatMap((category) => category.fields))
const bannerPreviewSrc = computed(() => normalizeBannerSrc(formBanner.bannerUrl))

// ─── Field factory ──────────────────────────────────────────────
function addFieldFromPicker() {
    const picked = allFieldTypes.value.find(field => field.type === mobileFieldType.value)
    if (!picked) return
    const nf = createField(picked.type, picked.label)
    formFields.value.push(nf)
    selectedFieldId.value = nf.id
    mobileFieldType.value = ''
}

function createField(type, label) {
    const defaults = {
        short_text: { placeholder: '' },
        long_text: { placeholder: '' },
        email: { placeholder: '' },
        phone: { placeholder: '' },
        number: { placeholder: '' },
        dropdown: { options: ['Option 1', 'Option 2'] },
        checkbox: { options: ['Choice A', 'Choice B', 'Choice C'] },
        radio: { options: ['Option 1', 'Option 2', 'Option 3'] },
        image_upload: { metadata: { accepts: 'png, jpg, jpeg' } },
        file_upload: { metadata: { accepts: 'pdf, doc, xls' } },
        date: {},
        time: {},
        rating: { metadata: { maxStars: 5 } },
        heading: { metadata: { content: 'Section Heading' } },
        paragraph: { metadata: { content: '' } },
        divider: {},
    }
    return {
        id: crypto.randomUUID(),
        type,
        label: label || 'Untitled Field',
        description: '',
        placeholder: '',
        required: false,
        options: [],
        metadata: {},
        ...(defaults[type] || {}),
    }
}

// ─── Sidebar Drag Start ─────────────────────────────────────────
function onSidebarDragStart(e, fieldData) {
    e.dataTransfer.effectAllowed = 'copy'
    e.dataTransfer.setData(
        'application/json',
        JSON.stringify({ type: fieldData.type, label: fieldData.label, isNew: true }),
    )
}

// ─── Canvas Item Drag Start ─────────────────────────────────────
function onCanvasDragStart(e, field, index) {
    dragSourceId.value = field.id
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData(
        'application/json',
        JSON.stringify({ id: field.id, fromIndex: index, isNew: false }),
    )
    // slight delay to let browser capture the drag image
    requestAnimationFrame(() => {
        isDraggingOverCanvas.value = true
    })
}

// ─── Drop zone between fields ───────────────────────────────────
function onGapDragEnter(index) {
    dropIndicatorIndex.value = index
}

function onCanvasDragOver(e) {
    e.preventDefault()
    e.dataTransfer.dropEffect = isDraggingOverCanvas.value ? 'move' : 'copy'
    // If dragging over the main canvas container (not a gap), compute closest gap
    if (dropIndicatorIndex.value === -1 && formFields.value.length === 0) {
        dropIndicatorIndex.value = 0
    }
}

function onCanvasDragLeave(e) {
    // Only reset if leaving the entire canvas
    if (!e.currentTarget.contains(e.relatedTarget)) {
        dropIndicatorIndex.value = -1
        isDraggingOverCanvas.value = false
    }
}

function onCanvasDrop(e) {
    e.preventDefault()
    const raw = e.dataTransfer.getData('application/json')
    if (!raw) return

    const data = JSON.parse(raw)
    let insertAt = dropIndicatorIndex.value
    if (insertAt < 0) insertAt = formFields.value.length

    if (data.isNew) {
        // Add new field from sidebar
        const newField = createField(data.type, data.label)
        formFields.value.splice(insertAt, 0, newField)
        selectedFieldId.value = newField.id
    } else {
        // Reorder within canvas
        const fromIndex = formFields.value.findIndex((f) => f.id === data.id)
        if (fromIndex === -1) return
        const [moved] = formFields.value.splice(fromIndex, 1)
        // Adjust insertAt if moving downward
        const adjustedInsert = insertAt > fromIndex ? insertAt - 1 : insertAt
        formFields.value.splice(adjustedInsert, 0, moved)
        selectedFieldId.value = moved.id
    }

    // Reset DnD state
    dropIndicatorIndex.value = -1
    isDraggingOverCanvas.value = false
    dragSourceId.value = null
}

function onDragEnd() {
    dropIndicatorIndex.value = -1
    isDraggingOverCanvas.value = false
    dragSourceId.value = null
}

// ─── Field actions ──────────────────────────────────────────────
function selectField(id) {
    selectedFieldId.value = selectedFieldId.value === id ? null : id
}

function deleteField(id) {
    formFields.value = formFields.value.filter((f) => f.id !== id)
    if (selectedFieldId.value === id) selectedFieldId.value = null
}

function duplicateField(id) {
    const index = formFields.value.findIndex((f) => f.id === id)
    if (index === -1) return
    const original = formFields.value[index]
    const copy = {
        ...JSON.parse(JSON.stringify(original)),
        id: crypto.randomUUID(),
        label: `${original.label} (copy)`,
    }
    formFields.value.splice(index + 1, 0, copy)
    selectedFieldId.value = copy.id
}

function updateField(updatedField) {
    const index = formFields.value.findIndex((f) => f.id === updatedField.id)
    if (index !== -1) {
        formFields.value[index] = updatedField
    }
}

// ─── Category toggle ────────────────────────────────────────────
function toggleCategory(index) {
    categories.value[index].isOpen = !categories.value[index].isOpen
}
</script>

<template>
    <Head title="Form Builder" />

    <div class="flex h-[calc(100svh-3.5rem)] flex-col overflow-hidden lg:flex-row">
        <!-- ════════════════════════════════════════════════════════
             LEFT SIDEBAR — Component Palette
             ════════════════════════════════════════════════════════ -->
        <aside
            class="hidden w-[260px] shrink-0 flex-col border-r-[1.5px] border-[var(--brutal-ink)]/10 bg-white lg:flex"
        >
            <!-- Sidebar header -->
            <div class="border-b border-[var(--brutal-ink)]/8 px-4 pt-4 pb-3">
                <h2 class="font-display text-sm font-bold tracking-tight text-[var(--brutal-ink)]">
                    <Sparkles class="mr-1.5 inline size-4 text-[var(--brutal-yellow)]" />
                    Components
                </h2>
                <p class="mt-0.5 text-[10px] text-muted-foreground">
                    Drag a component to the form canvas
                </p>

                <!-- Search -->
                <div class="relative mt-3">
                    <Search class="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground/50" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search components..."
                        class="w-full rounded-lg border-[1.5px] border-[var(--brutal-ink)]/10 bg-muted/20 py-2 pr-3 pl-8 text-xs font-medium text-[var(--brutal-ink)] placeholder:text-muted-foreground/50 transition-all focus:border-[var(--brutal-blue)] focus:outline-none focus:ring-2 focus:ring-[var(--brutal-blue)]/20"
                    />
                </div>
            </div>

            <!-- Scrollable categories -->
            <div class="flex-1 overflow-y-auto px-3 py-3">
                <div class="mb-4">
                    <FormBannerSettings
                        :model-value="formBanner"
                        @update:model-value="(value: FormBannerState) => Object.assign(formBanner, value)"
                    />
                </div>
                <div v-for="(category, catIdx) in filteredCategories" :key="category.name" class="mb-3 last:mb-0">
                    <!-- Category header (collapsible) -->
                    <button
                        class="mb-1.5 flex w-full items-center gap-2 rounded-lg px-1 py-1 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-muted-foreground transition-colors hover:text-[var(--brutal-ink)]"
                        @click="toggleCategory(catIdx)"
                    >
                        <ChevronRight
                            class="size-3 shrink-0 transition-transform duration-200"
                            :class="category.isOpen ? 'rotate-90' : ''"
                        />
                        <component :is="category.icon" class="size-3.5 shrink-0" />
                        {{ category.name }}
                        <span class="ml-auto text-[10px] font-medium text-muted-foreground/50">
                            {{ category.fields.length }}
                        </span>
                    </button>

                    <!-- Draggable items -->
                    <div
                        v-show="category.isOpen"
                        class="flex flex-col gap-1.5"
                    >
                        <DraggableItem
                            v-for="f in category.fields"
                            :key="f.type"
                            :type="f.type"
                            :label="f.label"
                            :icon="f.icon"
                            :description="f.description"
                            @dragstart="(e) => onSidebarDragStart(e, f)"
                        />
                    </div>
                </div>

                <!-- No results -->
                <div
                    v-if="filteredCategories.length === 0"
                    class="flex flex-col items-center py-8 text-center"
                >
                    <Search class="mb-2 size-8 text-muted-foreground/30" />
                    <p class="text-xs font-medium text-muted-foreground">No components found</p>
                    <p class="mt-0.5 text-[10px] text-muted-foreground/60">Try a different search term</p>
                </div>
            </div>
        </aside>

        <!-- ════════════════════════════════════════════════════════
             CENTER — Mobile Canvas
             ════════════════════════════════════════════════════════ -->
        <main class="relative flex-1 overflow-y-auto bg-[var(--background)]">
            <!-- Top toolbar -->
            <div
                class="sticky top-0 z-10 flex items-center justify-between border-b border-[var(--brutal-ink)]/8 bg-white/90 px-6 py-2 backdrop-blur-md"
            >
                <div class="flex items-center gap-2">
                    <Smartphone class="size-4 text-muted-foreground" />
                    <span class="text-xs font-semibold text-muted-foreground">Mobile Preview</span>
                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">
                        {{ formFields.length }} field{{ formFields.length !== 1 ? 's' : '' }}
                    </span>
                </div>
                <div class="flex items-center gap-1.5">
                    <Button variant="outline" size="sm" class="h-8 text-xs" disabled>
                        <Eye class="mr-1 size-3.5" />Preview
                    </Button>
                    <Button size="sm" class="h-8 text-xs" disabled>
                        <Save class="mr-1 size-3.5" />Save
                    </Button>
                </div>
            </div>
            <div class="border-b border-[var(--brutal-ink)]/8 bg-[var(--brutal-cream)]/70 px-4 py-3 lg:hidden">
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-muted-foreground">Add field on mobile</label>
                <div class="flex gap-2">
                    <select v-model="mobileFieldType" class="min-w-0 flex-1 rounded-xl border-[1.5px] border-[var(--brutal-ink)] bg-white px-3 py-2 text-xs font-semibold">
                        <option value="">Choose field type</option>
                        <option v-for="fieldType in allFieldTypes" :key="fieldType.type" :value="fieldType.type">{{ fieldType.label }}</option>
                    </select>
                    <Button size="sm" class="h-9 shrink-0 text-xs" :disabled="!mobileFieldType" @click="addFieldFromPicker">
                        <Plus class="mr-1 size-3.5" />Add
                    </Button>
                </div>
            </div>

            <!-- Canvas area -->
            <div class="flex justify-center px-6 py-8">
                <div class="w-full max-w-[420px]">
                    <!-- Phone frame -->
                    <div
                        class="rounded-2xl border-[1.5px] border-[var(--brutal-ink)]/12 bg-white shadow-[var(--shadow-md)]"
                    >
                        <!-- Form header (always visible) -->
                        <div
                            v-if="bannerPreviewSrc || formBanner.caption.trim()"
                            class="border-b border-[var(--brutal-ink)]/10 bg-muted/20"
                        >
                            <img
                                v-if="bannerPreviewSrc"
                                :src="bannerPreviewSrc"
                                alt=""
                                class="aspect-[3/1] w-full object-cover"
                            />
                            <p
                                v-if="formBanner.caption.trim()"
                                class="px-5 py-3 text-[12px] leading-relaxed text-muted-foreground"
                            >
                                {{ formBanner.caption }}
                            </p>
                        </div>
                        <div
                            class="border-b border-[var(--brutal-ink)]/8 bg-gradient-to-br from-primary/5 via-transparent to-[var(--brutal-yellow)]/5 px-5 pt-6 pb-5"
                            :class="bannerPreviewSrc || formBanner.caption.trim() ? 'rounded-none' : 'rounded-t-2xl'"
                        >
                            <input
                                v-model="formTitle"
                                class="w-full bg-transparent font-display text-xl font-bold tracking-tight text-[var(--brutal-ink)] placeholder:text-[var(--brutal-ink)]/30 focus:outline-none"
                                placeholder="Form Title"
                            />
                            <textarea
                                v-model="formDescription"
                                rows="2"
                                class="mt-2 w-full resize-none bg-transparent text-[13px] leading-relaxed text-muted-foreground placeholder:text-muted-foreground/40 focus:outline-none"
                                placeholder="Add a description to help users understand the form..."
                            ></textarea>
                        </div>

                        <!-- Droppable fields area -->
                        <div
                            class="min-h-[400px] px-4 py-4"
                            :class="[
                                isEmpty && !isDraggingOverCanvas
                                    ? 'flex items-center justify-center'
                                    : '',
                                isDraggingOverCanvas && isEmpty ? 'bg-[var(--brutal-blue)]/[0.03]' : '',
                            ]"
                            @dragover.prevent="onCanvasDragOver"
                            @dragleave="onCanvasDragLeave"
                            @drop="onCanvasDrop"
                        >
                            <!-- ─── Empty State ─── -->
                            <div
                                v-if="isEmpty && !isDraggingOverCanvas"
                                class="flex flex-col items-center py-6 text-center"
                            >
                                <div
                                    class="mb-4 rounded-2xl border-2 border-dashed border-[var(--brutal-ink)]/15 px-8 py-8"
                                >
                                    <LocalLottie name="builderEmpty" :height="140" :width="140" />
                                </div>
                                <h3 class="font-display text-base font-bold text-[var(--brutal-ink)]">
                                    Start building your form
                                </h3>
                                <p class="mt-1.5 max-w-[260px] text-xs leading-relaxed text-muted-foreground">
                                    Drag components from the left panel and drop them here.
                                    You can rearrange them anytime.
                                </p>
                                <div class="mt-4 flex items-center gap-2 rounded-full bg-muted/40 px-3 py-1.5">
                                    <GripVertical class="size-3.5 text-muted-foreground/50" />
                                    <span class="text-[10px] font-medium text-muted-foreground">
                                        Tip: Drag the handle to reorder fields
                                    </span>
                                </div>
                            </div>

                            <!-- ─── Drop here placeholder (when empty + dragging) ─── -->
                            <div
                                v-if="isEmpty && isDraggingOverCanvas"
                                class="flex min-h-[200px] flex-col items-center justify-center rounded-xl border-2 border-dashed border-[var(--brutal-blue)]/40 bg-[var(--brutal-blue)]/[0.05] transition-all"
                            >
                                <div class="size-10 rounded-full bg-[var(--brutal-blue)]/10 p-2.5">
                                    <Plus class="size-full text-[var(--brutal-blue)]" />
                                </div>
                                <p class="mt-2 text-xs font-semibold text-[var(--brutal-blue)]">
                                    Drop here to add
                                </p>
                            </div>

                            <!-- ─── Field List ─── -->
                            <div v-if="!isEmpty" class="flex flex-col">
                                <template v-for="(field, index) in formFields" :key="field.id">
                                    <!-- Drop indicator BEFORE this field -->
                                    <div
                                        class="relative py-0.5"
                                        @dragenter.prevent="onGapDragEnter(index)"
                                        @dragover.prevent
                                    >
                                        <div
                                            class="pointer-events-none h-0.5 rounded-full transition-all duration-200"
                                            :class="
                                                dropIndicatorIndex === index
                                                    ? 'bg-[var(--brutal-blue)] shadow-[0_0_8px_rgba(37,99,235,0.3)]'
                                                    : 'bg-transparent'
                                            "
                                        ></div>
                                    </div>

                                    <!-- Field card with drag handle -->
                                    <div
                                        class="group relative"
                                        :class="[
                                            dragSourceId === field.id ? 'opacity-30' : '',
                                        ]"
                                    >
                                        <!-- Drag handle (left) -->
                                        <div
                                            class="absolute -left-1 top-1/2 z-10 -translate-x-full -translate-y-1/2 cursor-grab opacity-0 transition-opacity active:cursor-grabbing group-hover:opacity-100"
                                            draggable="true"
                                            @dragstart="(e) => onCanvasDragStart(e, field, index)"
                                            @dragend="onDragEnd"
                                        >
                                            <div
                                                class="flex size-7 items-center justify-center rounded-lg bg-white text-muted-foreground/60 shadow-[var(--shadow-sm)] ring-1 ring-[var(--brutal-ink)]/8 transition-colors hover:text-[var(--brutal-ink)]"
                                            >
                                                <GripVertical class="size-4" />
                                            </div>
                                        </div>

                                        <FieldRenderer
                                            :field="field"
                                            :is-selected="selectedFieldId === field.id"
                                            @select="selectField(field.id)"
                                            @delete="deleteField(field.id)"
                                            @duplicate="duplicateField(field.id)"
                                        />
                                    </div>
                                </template>

                                <!-- Drop indicator AFTER last field -->
                                <div
                                    class="relative py-0.5"
                                    @dragenter.prevent="onGapDragEnter(formFields.length)"
                                    @dragover.prevent
                                >
                                    <div
                                        class="pointer-events-none h-0.5 rounded-full transition-all duration-200"
                                        :class="
                                            dropIndicatorIndex === formFields.length
                                                ? 'bg-[var(--brutal-blue)] shadow-[0_0_8px_rgba(37,99,235,0.3)]'
                                                : 'bg-transparent'
                                        "
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom hint -->
                    <p class="mt-4 text-center text-[10px] text-muted-foreground/50">
                        Form preview · Mobile size (420px). On mobile, use the add-field dropdown above.
                    </p>
                </div>
            </div>
        </main>

        <!-- ════════════════════════════════════════════════════════
             RIGHT SIDEBAR — Properties Panel
             ════════════════════════════════════════════════════════ -->
        <aside
            class="hidden w-[300px] shrink-0 flex-col border-l-[1.5px] border-[var(--brutal-ink)]/10 bg-white lg:flex"
        >
            <div class="border-b border-[var(--brutal-ink)]/8 px-4 pt-4 pb-3">
                <h2 class="font-display text-sm font-bold tracking-tight text-[var(--brutal-ink)]">
                    Properties
                </h2>
                <p class="mt-0.5 text-[10px] text-muted-foreground">
                    {{ selectedField ? 'Edit the selected field' : 'Select a field to edit' }}
                </p>
            </div>

            <div class="flex-1 overflow-y-auto px-4 py-4">
                <!-- Field editor -->
                <FieldEditor
                    v-if="selectedField"
                    :field="selectedField"
                    @update:field="updateField"
                />

                <!-- Empty state -->
                <div
                    v-else
                    class="flex h-full flex-col items-center justify-center text-center"
                >
                    <div class="rounded-2xl border-2 border-dashed border-[var(--brutal-ink)]/10 p-6">
                        <LocalLottie name="fieldSelected" :height="100" :width="100" />
                    </div>
                    <p class="mt-4 text-xs font-semibold text-[var(--brutal-ink)]/60">
                        No field selected
                    </p>
                    <p class="mt-1 max-w-[200px] text-[10px] leading-relaxed text-muted-foreground">
                        Click on any field in the form canvas to edit its properties here.
                    </p>
                </div>
            </div>
        </aside>
    </div>
</template>
