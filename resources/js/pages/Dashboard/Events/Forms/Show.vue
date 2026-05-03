<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import DraggableItem from '@/components/modules/builder/DraggableItem.vue';
import FieldRenderer from '@/components/modules/builder/FieldRenderer.vue';
import FieldEditor from '@/components/modules/builder/FieldEditor.vue';
import FormBannerSettings from '@/components/modules/builder/FormBannerSettings.vue';
import FormPreviewDialog from '@/components/modules/builder/FormPreviewDialog.vue';
import { fromBackendField, toBackendFields } from '@/components/modules/builder/fieldMapping';
import {
    defaultFormBannerState,
    prependFormBannerToBackendPayload,
    extractFormBannerFromBuilderFields,
    normalizeBannerSrc,
    type FormBannerState,
} from '@/components/modules/builder/formBanner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import LocalLottie from '@/components/core/LocalLottie.vue';
import {
    Type,
    AlignLeft,
    Mail,
    Phone,
    Hash,
    ChevronDown,
    SquareCheck,
    CircleDot,
    ImagePlus,
    Upload,
    Calendar,
    Clock,
    Star,
    Heading as HeadingIcon,
    TextCursorInput,
    Minus,
    GripVertical,
    ChevronRight,
    Search,
    Eye,
    Save,
    Settings,
    Plus,
    ArrowLeft,
    FileText,
} from 'lucide-vue-next';

defineOptions({ layout: DashboardFocusLayout });

const props = defineProps({
    event: Object,
    form: Object,
    fields: Array,
    saveFieldsUrl: String,
    updateFormUrl: String,
});

// ─── Field Categories ──────────────────────────────────────────
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
];

const visibilityOptions = [
    { value: 'public', label: 'Public' },
    { value: 'participant', label: 'Participant' },
    { value: 'admin', label: 'Admin' },
];

// ─── State ─────────────────────────────────────────────────────
const categories = ref(fieldCategories.map((c) => ({ ...c })));
const searchQuery = ref('');
const selectedFieldId = ref(null);
const dropIndicatorIndex = ref(-1);
const isDraggingOverCanvas = ref(false);
const dragSourceId = ref(null);
const activePanel = ref('fields'); // 'fields' | 'settings'
const showPreview = ref(false);

// Form settings
const settingsForm = useForm({
    _method: 'put',
    title: props?.form?.title,
    description: props?.form?.description,
    closed_at: props?.form?.closed_at ?? '',
    visible_for: [...props?.form?.visible_for],
});

/** Form banner (synced separately from draggable fields — persisted as synthetic field metadata). */
const bannerState = reactive(defaultFormBannerState());

// Builder fields (convert from backend)
const formFields = ref([]);
function syncFieldsFromProps() {
    const raw = JSON.parse(JSON.stringify(props.fields || []));
    raw.sort((a, b) => a.order - b.order);
    const mapped = raw.map((f) => fromBackendField(f));
    const { banner, canvasFields } = extractFormBannerFromBuilderFields(mapped);
    Object.assign(bannerState, banner);
    formFields.value = canvasFields;
}

watch(
    () => props.fields,
    () => syncFieldsFromProps(),
    { immediate: true, deep: true }
);
watch(
    () => props.form,
    (f) => {
        settingsForm.title = f?.title;
        settingsForm.description = f?.description;
        settingsForm.closed_at = f?.closed_at ?? '';
        settingsForm.visible_for = [...f?.visible_for];
    },
    { deep: true }
);

// Auto-save pending fields from Create flow
onMounted(() => {
    const pb = sessionStorage.getItem('pendingFormBanner');
    if (pb) {
        sessionStorage.removeItem('pendingFormBanner');
        try {
            const parsed = JSON.parse(pb) as Record<string, unknown>;
            Object.assign(bannerState, {
                ...defaultFormBannerState(),
                id: typeof parsed.id === 'string' ? parsed.id : null,
                bannerUrl: typeof parsed.bannerUrl === 'string' ? parsed.bannerUrl : '',
                bannerFileName: typeof parsed.bannerFileName === 'string' ? parsed.bannerFileName : '',
                caption: typeof parsed.caption === 'string' ? parsed.caption : '',
            });
        } catch {
            /* ignore */
        }
    }
    const pending = sessionStorage.getItem('pendingBuilderFields');
    if (pending) {
        sessionStorage.removeItem('pendingBuilderFields');
        try {
            formFields.value = JSON.parse(pending);
            saveFields();
        } catch {
            /* ignore */
        }
    }
});

// ─── Computed ──────────────────────────────────────────────────
const filteredCategories = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    if (!q) return categories.value;
    return categories.value
        .map((cat) => ({
            ...cat,
            isOpen: true,
            fields: cat.fields.filter((f) => f.label.toLowerCase().includes(q) || f.type.includes(q)),
        }))
        .filter((cat) => cat.fields.length > 0);
});
const selectedField = computed(() => formFields.value.find((f) => f.id === selectedFieldId.value) ?? null);
const isEmpty = computed(() => formFields.value.length === 0);
const mobileFieldType = ref('');
const allFieldTypes = computed(() => fieldCategories.flatMap((cat) => cat.fields));
const bannerPreviewSrc = computed(() => normalizeBannerSrc(bannerState.bannerUrl));

// ─── Field factory ─────────────────────────────────────────────
function addFieldFromPicker() {
    const picked = allFieldTypes.value.find((field) => field.type === mobileFieldType.value);
    if (!picked) return;
    const nf = createField(picked.type, picked.label);
    formFields.value.push(nf);
    selectedFieldId.value = nf.id;
    mobileFieldType.value = '';
}

function createField(type, label) {
    const defaults = {
        dropdown: { options: ['Option 1', 'Option 2'] },
        checkbox: { options: ['Choice A', 'Choice B', 'Choice C'] },
        radio: { options: ['Option 1', 'Option 2', 'Option 3'] },
        rating: { metadata: { maxStars: 5 } },
        heading: { metadata: { content: 'Section Heading' } },
        image_upload: { metadata: { accepts: 'png, jpg, jpeg' } },
        file_upload: { metadata: { accepts: 'pdf, doc, xls' } },
    };
    return {
        id: crypto.randomUUID(),
        type,
        label: label || 'Untitled Field',
        name: `field_${crypto.randomUUID().replace(/-/g, '').slice(0, 12)}`,
        description: '',
        placeholder: '',
        required: false,
        options: [],
        metadata: {},
        ...(defaults[type] || {}),
    };
}

// ─── DnD: Sidebar → Canvas ────────────────────────────────────
function onGapDragEnter(index) {
    dropIndicatorIndex.value = index;
}
function onCanvasDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = isDraggingOverCanvas.value ? 'move' : 'copy';
    if (dropIndicatorIndex.value === -1 && isEmpty.value) dropIndicatorIndex.value = 0;
}
function onCanvasDragLeave(e) {
    if (!e.currentTarget.contains(e.relatedTarget)) {
        dropIndicatorIndex.value = -1;
        isDraggingOverCanvas.value = false;
    }
}
function onCanvasDrop(e) {
    e.preventDefault();
    const raw = e.dataTransfer.getData('application/json');
    if (!raw) return;
    const data = JSON.parse(raw);
    const insertAt = dropIndicatorIndex.value < 0 ? formFields.value.length : dropIndicatorIndex.value;
    if (data.isNew) {
        const nf = createField(data.type, data.label);
        formFields.value.splice(insertAt, 0, nf);
        selectedFieldId.value = nf.id;
    } else {
        const from = formFields.value.findIndex((f) => f.id === data.id);
        if (from === -1) return;
        const [moved] = formFields.value.splice(from, 1);
        formFields.value.splice(insertAt > from ? insertAt - 1 : insertAt, 0, moved);
        selectedFieldId.value = moved.id;
    }
    dropIndicatorIndex.value = -1;
    isDraggingOverCanvas.value = false;
    dragSourceId.value = null;
}
function onCanvasDragStart(e, field, index) {
    dragSourceId.value = field.id;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('application/json', JSON.stringify({ id: field.id, fromIndex: index, isNew: false }));
    requestAnimationFrame(() => {
        isDraggingOverCanvas.value = true;
    });
}
function onDragEnd() {
    dropIndicatorIndex.value = -1;
    isDraggingOverCanvas.value = false;
    dragSourceId.value = null;
}

// ─── Field actions ─────────────────────────────────────────────
function selectField(id) {
    selectedFieldId.value = selectedFieldId.value === id ? null : id;
    activePanel.value = 'fields';
}
function deleteField(id) {
    formFields.value = formFields.value.filter((f) => f.id !== id);
    if (selectedFieldId.value === id) selectedFieldId.value = null;
}
function duplicateField(id) {
    const i = formFields.value.findIndex((f) => f.id === id);
    if (i === -1) return;
    const copy = {
        ...JSON.parse(JSON.stringify(formFields.value[i])),
        id: crypto.randomUUID(),
        label: `${formFields.value[i].label} (copy)`,
        name: `field_${crypto.randomUUID().replace(/-/g, '').slice(0, 12)}`,
    };
    formFields.value.splice(i + 1, 0, copy);
    selectedFieldId.value = copy.id;
}
function updateField(updated) {
    const i = formFields.value.findIndex((f) => f.id === updated.id);
    if (i !== -1) formFields.value[i] = updated;
}
function toggleCategory(idx) {
    categories.value[idx].isOpen = !categories.value[idx].isOpen;
}
function toggleVisibility(value, checked) {
    const isChecked = checked === true;
    if (isChecked) {
        if (!settingsForm.visible_for.includes(value)) settingsForm.visible_for = [...settingsForm.visible_for, value];
    } else {
        settingsForm.visible_for = settingsForm.visible_for.filter((v) => v !== value);
    }
}

// ─── Save actions ──────────────────────────────────────────────
function saveSettings() {
    settingsForm.put(props.updateFormUrl, {
        preserveScroll: true,
        onSuccess: () => toast.success('Form settings saved.'),
    });
}
const fieldForm = useForm({ fields: [] });
function saveFields() {
    const merged = prependFormBannerToBackendPayload(formFields.value, bannerState);
    fieldForm.fields = toBackendFields(merged);
    fieldForm.post(props.saveFieldsUrl, { preserveScroll: true, onSuccess: () => toast.success('Fields saved!') });
}
function saveAll() {
    saveSettings();
    saveFields();
}
</script>

<template>
    <Head :title="`Edit: ${form.title}`" />
    <div class="flex h-[calc(100svh-3.5rem)] flex-col overflow-hidden lg:flex-row">
        <!-- LEFT SIDEBAR -->
        <aside class="hidden w-[260px] shrink-0 flex-col border-r-[1.5px] border-(--brutal-ink)/10 bg-white lg:flex">
            <!-- Tabs: Components / Settings -->
            <div class="flex border-b border-(--brutal-ink)/8">
                <button
                    class="flex flex-1 items-center justify-center gap-1.5 py-2.5 text-[11px] font-bold tracking-wider uppercase transition-colors"
                    :class="
                        activePanel === 'fields'
                            ? 'border-primary text-primary border-b-2'
                            : 'text-muted-foreground hover:text-(--brutal-ink)'
                    "
                    @click="activePanel = 'fields'"
                >
                    <Sparkles class="size-3.5" />Components
                </button>
                <button
                    class="flex flex-1 items-center justify-center gap-1.5 py-2.5 text-[11px] font-bold tracking-wider uppercase transition-colors"
                    :class="
                        activePanel === 'settings'
                            ? 'border-primary text-primary border-b-2'
                            : 'text-muted-foreground hover:text-(--brutal-ink)'
                    "
                    @click="activePanel = 'settings'"
                >
                    <Settings class="size-3.5" />Settings
                </button>
            </div>

            <!-- Components panel -->
            <div v-show="activePanel === 'fields'" class="flex flex-1 flex-col overflow-hidden">
                <div class="px-3 pt-3 pb-2">
                    <div class="relative">
                        <Search class="text-muted-foreground/50 absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search components..."
                            class="bg-muted/20 placeholder:text-muted-foreground/50 w-full rounded-lg border-[1.5px] border-(--brutal-ink)/10 py-2 pr-3 pl-8 text-xs font-medium text-(--brutal-ink) transition-all focus:border-(--brutal-blue) focus:ring-2 focus:ring-(--brutal-blue)/20 focus:outline-none"
                        />
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-3 pb-3">
                    <div v-for="(cat, ci) in filteredCategories" :key="cat.name" class="mb-3 last:mb-0">
                        <button
                            class="text-muted-foreground mb-1.5 flex w-full items-center gap-2 px-1 py-1 text-left text-[11px] font-bold tracking-[0.08em] uppercase hover:text-(--brutal-ink)"
                            @click="toggleCategory(ci)"
                        >
                            <ChevronRight
                                class="size-3 shrink-0 transition-transform duration-200"
                                :class="cat.isOpen ? 'rotate-90' : ''"
                            />
                            <component :is="cat.icon" class="size-3.5 shrink-0" />{{ cat.name }}
                            <span class="text-muted-foreground/50 ml-auto text-[10px]">{{ cat.fields.length }}</span>
                        </button>
                        <div v-show="cat.isOpen" class="flex flex-col gap-1.5">
                            <DraggableItem v-for="f in cat.fields" :key="f.type" v-bind="f" />
                        </div>
                    </div>
                    <div v-if="filteredCategories.length === 0" class="flex flex-col items-center py-8 text-center">
                        <Search class="text-muted-foreground/30 mb-2 size-8" />
                        <p class="text-muted-foreground text-xs font-medium">No components found</p>
                    </div>
                </div>
            </div>

            <!-- Settings panel -->
            <div v-show="activePanel === 'settings'" class="flex-1 overflow-y-auto px-4 py-4">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-semibold">Title</Label>
                        <Input v-model="settingsForm.title" class="text-xs" />
                        <p v-if="settingsForm.errors.title" class="text-destructive text-[10px]">
                            {{ settingsForm.errors.title }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-semibold">Description</Label>
                        <textarea
                            v-model="settingsForm.description"
                            rows="3"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-lg border px-3 py-2 text-xs focus-visible:ring-2 focus-visible:outline-none"
                        ></textarea>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-semibold">Closes at</Label>
                        <Input v-model="settingsForm.closed_at" type="datetime-local" class="text-xs" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label class="text-xs font-semibold">Visible for</Label>
                        <div
                            v-for="opt in visibilityOptions"
                            :key="opt.value"
                            class="flex cursor-pointer items-center gap-2 text-xs"
                        >
                            <Checkbox
                                :checked="settingsForm.visible_for.includes(opt.value)"
                                @update:checked="(v) => toggleVisibility(opt.value, v)"
                            />
                            <span>{{ opt.label }}</span>
                        </div>
                    </div>
                    <FormBannerSettings
                        :model-value="bannerState"
                        @update:model-value="(value: FormBannerState) => Object.assign(bannerState, value)"
                    />
                    <p class="text-muted-foreground text-[10px] leading-relaxed">
                        Simpan banner bersama struktur formulir menggunakan tombol
                        <span class="text-foreground font-semibold">Save All</span> di atas.
                    </p>
                    <Button size="sm" :disabled="settingsForm.processing" @click="saveSettings">
                        <Save class="mr-1.5 size-3.5" />Update settings
                    </Button>
                </div>
            </div>
        </aside>

        <!-- CENTER CANVAS -->
        <main class="bg-background relative flex-1 overflow-y-auto">
            <div class="border-b border-(--brutal-ink)/8 bg-[var(--brutal-cream)]/70 px-4 py-3 lg:hidden">
                <label class="text-muted-foreground mb-1 block text-[10px] font-bold tracking-[0.16em] uppercase"
                    >Add field on mobile</label
                >
                <div class="flex gap-2">
                    <select
                        v-model="mobileFieldType"
                        class="min-w-0 flex-1 rounded-xl border-[1.5px] border-[var(--brutal-ink)] bg-white px-3 py-2 text-xs font-semibold"
                    >
                        <option value="">Choose field type</option>
                        <option v-for="fieldType in allFieldTypes" :key="fieldType.type" :value="fieldType.type">
                            {{ fieldType.label }}
                        </option>
                    </select>
                    <Button
                        size="sm"
                        class="h-9 shrink-0 text-xs"
                        :disabled="!mobileFieldType"
                        @click="addFieldFromPicker"
                    >
                        <Plus class="mr-1 size-3.5" />Add
                    </Button>
                </div>
            </div>

            <div
                class="sticky top-0 z-10 flex items-center justify-between border-b border-(--brutal-ink)/8 bg-white/90 px-6 py-2 backdrop-blur-md"
            >
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="sm" class="h-8 text-xs" as-child>
                        <a :href="`/dashboard/events/${event.id}/forms`"><ArrowLeft class="mr-1 size-3.5" />Back</a>
                    </Button>
                    <span class="text-muted-foreground text-xs font-semibold">{{
                        settingsForm.title || 'Untitled'
                    }}</span>
                    <span class="bg-primary/10 text-primary rounded-full px-2 py-0.5 text-[10px] font-bold"
                        >{{ formFields.length }} field{{ formFields.length !== 1 ? 's' : '' }}</span
                    >
                </div>
                <div class="flex items-center gap-1.5">
                    <Button variant="outline" size="sm" class="h-8 text-xs" as-child>
                        <a :href="`/dashboard/events/${event.id}/forms/${form.id}/submissions`">
                            <FileText class="mr-1 size-3.5" />Submissions
                        </a>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs"
                        :disabled="formFields.length === 0"
                        @click="showPreview = true"
                    >
                        <Eye class="mr-1 size-3.5" />Preview
                    </Button>
                    <Button size="sm" class="h-8 text-xs" :disabled="fieldForm.processing" @click="saveAll">
                        <Save class="mr-1 size-3.5" />Save All
                    </Button>
                </div>
            </div>
            <div class="flex justify-center px-6 py-8">
                <div class="w-full max-w-[420px]">
                    <div
                        class="overflow-hidden rounded-2xl border-[1.5px] border-(--brutal-ink)/12 bg-white shadow-(--shadow-md)"
                    >
                        <div
                            v-if="bannerPreviewSrc || bannerState.caption.trim()"
                            class="bg-muted/20 border-b border-(--brutal-ink)/10"
                        >
                            <img
                                v-if="bannerPreviewSrc"
                                :src="bannerPreviewSrc"
                                alt=""
                                class="aspect-[3/1] w-full object-cover"
                            />
                            <p
                                v-if="bannerState.caption.trim()"
                                class="text-muted-foreground px-5 py-3 text-[12px] leading-relaxed"
                            >
                                {{ bannerState.caption }}
                            </p>
                        </div>
                        <div
                            class="from-primary/5 border-b border-(--brutal-ink)/8 bg-linear-to-br via-transparent to-(--brutal-yellow)/5 px-5 pt-6 pb-5"
                            :class="bannerPreviewSrc || bannerState.caption.trim() ? '' : 'rounded-t-2xl'"
                        >
                            <h2 class="font-display text-xl font-bold tracking-tight text-(--brutal-ink)">
                                {{ settingsForm.title || 'Untitled Form' }}
                            </h2>
                            <p class="text-muted-foreground mt-2 text-[13px] leading-relaxed">
                                {{ settingsForm.description || 'No description' }}
                            </p>
                        </div>
                        <div
                            class="min-h-[400px] px-4 py-4"
                            :class="[
                                isEmpty && !isDraggingOverCanvas ? 'flex items-center justify-center' : '',
                                isDraggingOverCanvas && isEmpty ? 'bg-(--brutal-blue)/3' : '',
                            ]"
                            @dragover.prevent="onCanvasDragOver"
                            @dragleave="onCanvasDragLeave"
                            @drop="onCanvasDrop"
                        >
                            <!-- Empty state -->
                            <div
                                v-if="isEmpty && !isDraggingOverCanvas"
                                class="flex flex-col items-center py-6 text-center"
                            >
                                <div class="mb-4 rounded-2xl border-2 border-dashed border-(--brutal-ink)/15 px-8 py-8">
                                    <LocalLottie name="builderEmpty" :height="140" :width="140" />
                                </div>
                                <h3 class="font-display text-base font-bold text-(--brutal-ink)">
                                    Start building your form
                                </h3>
                                <p class="text-muted-foreground mt-1.5 max-w-[260px] text-xs leading-relaxed">
                                    Drag components from the left panel and drop them here.
                                </p>
                                <div class="bg-muted/40 mt-4 flex items-center gap-2 rounded-full px-3 py-1.5">
                                    <GripVertical class="text-muted-foreground/50 size-3.5" />
                                    <span class="text-muted-foreground text-[10px] font-medium"
                                        >Tip: Drag the handle to reorder fields</span
                                    >
                                </div>
                            </div>
                            <!-- Drop placeholder -->
                            <div
                                v-if="isEmpty && isDraggingOverCanvas"
                                class="flex min-h-[200px] flex-col items-center justify-center rounded-xl border-2 border-dashed border-(--brutal-blue)/40 bg-(--brutal-blue)/5"
                            >
                                <div class="size-10 rounded-full bg-(--brutal-blue)/10 p-2.5">
                                    <Plus class="size-full text-(--brutal-blue)" />
                                </div>
                                <p class="mt-2 text-xs font-semibold text-(--brutal-blue)">Drop here to add</p>
                            </div>
                            <!-- Field list -->
                            <div v-if="!isEmpty" class="flex flex-col">
                                <template v-for="(field, index) in formFields" :key="field.id">
                                    <div
                                        class="relative py-0.5"
                                        @dragenter.prevent="onGapDragEnter(index)"
                                        @dragover.prevent
                                    >
                                        <div
                                            class="pointer-events-none h-0.5 rounded-full transition-all duration-200"
                                            :class="
                                                dropIndicatorIndex === index
                                                    ? 'bg-(--brutal-blue) shadow-[0_0_8px_rgba(37,99,235,0.3)]'
                                                    : 'bg-transparent'
                                            "
                                        ></div>
                                    </div>
                                    <div class="group relative" :class="dragSourceId === field.id ? 'opacity-30' : ''">
                                        <div
                                            class="absolute top-1/2 -left-1 z-10 -translate-x-full -translate-y-1/2 cursor-grab opacity-0 transition-opacity group-hover:opacity-100 active:cursor-grabbing"
                                            draggable="true"
                                            @dragstart="(e) => onCanvasDragStart(e, field, index)"
                                            @dragend="onDragEnd"
                                        >
                                            <div
                                                class="text-muted-foreground/60 flex size-7 items-center justify-center rounded-lg bg-white shadow-(--shadow-sm) ring-1 ring-(--brutal-ink)/8 hover:text-(--brutal-ink)"
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
                                <div
                                    class="relative py-0.5"
                                    @dragenter.prevent="onGapDragEnter(formFields.length)"
                                    @dragover.prevent
                                >
                                    <div
                                        class="pointer-events-none h-0.5 rounded-full transition-all duration-200"
                                        :class="
                                            dropIndicatorIndex === formFields.length
                                                ? 'bg-(--brutal-blue) shadow-[0_0_8px_rgba(37,99,235,0.3)]'
                                                : 'bg-transparent'
                                        "
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted-foreground/50 mt-4 text-center text-[10px]">
                        Form preview · Mobile size (420px). On mobile, use the add-field dropdown above.
                    </p>
                </div>
            </div>
        </main>

        <!-- RIGHT SIDEBAR -->
        <aside class="hidden w-[300px] shrink-0 flex-col border-l-[1.5px] border-(--brutal-ink)/10 bg-white lg:flex">
            <div class="border-b border-(--brutal-ink)/8 px-4 pt-4 pb-3">
                <h2 class="font-display text-sm font-bold tracking-tight text-(--brutal-ink)">Properties</h2>
                <p class="text-muted-foreground mt-0.5 text-[10px]">
                    {{ selectedField ? 'Edit the selected field' : 'Select a field to edit' }}
                </p>
            </div>
            <div class="flex-1 overflow-y-auto px-4 py-4">
                <FieldEditor v-if="selectedField" :field="selectedField" @update:field="updateField" />
                <div v-else class="flex h-full flex-col items-center justify-center text-center">
                    <div class="rounded-2xl border-2 border-dashed border-(--brutal-ink)/10 p-6">
                        <LocalLottie name="fieldSelected" :height="100" :width="100" />
                    </div>
                    <p class="mt-4 text-xs font-semibold text-(--brutal-ink)/60">No field selected</p>
                    <p class="text-muted-foreground mt-1 max-w-[200px] text-[10px] leading-relaxed">
                        Click on any field in the canvas to edit its properties here.
                    </p>
                </div>
            </div>
        </aside>
    </div>

    <FormPreviewDialog
        :open="showPreview"
        :title="settingsForm.title || 'Untitled Form'"
        :description="settingsForm.description"
        :form-banner-url="bannerState.bannerUrl"
        :form-banner-caption="bannerState.caption"
        :fields="formFields"
        @close="showPreview = false"
    />
</template>
