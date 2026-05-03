<script setup lang="ts">
import { ref, reactive, computed, type Component } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import DraggableItem from '@/components/modules/builder/DraggableItem.vue';
import FieldRenderer from '@/components/modules/builder/FieldRenderer.vue';
import FieldEditor from '@/components/modules/builder/FieldEditor.vue';
import FormBannerSettings from '@/components/modules/builder/FormBannerSettings.vue';
import FormPreviewDialog from '@/components/modules/builder/FormPreviewDialog.vue';
import {
    defaultFormBannerState,
    normalizeBannerSrc,
    prependFormBannerToBackendPayload,
} from '@/components/modules/builder/formBanner';
import { toBackendFields } from '@/components/modules/builder/fieldMapping';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetTrigger } from '@/components/ui/sheet';
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
    Save,
    Eye,
    Settings,
    Plus,
    ArrowLeft,
} from 'lucide-vue-next';

defineOptions({ layout: DashboardFocusLayout });

const props = defineProps<{
    event: { id: string; title: string };
}>();

// ─── Categories ─────────────────────────────────────────────
interface FieldTemplate {
    type: FormBuilderType;
    label: string;
    icon: Component;
    description: string;
}

interface FieldCategory {
    name: string;
    icon: Component;
    isOpen: boolean;
    fields: FieldTemplate[];
}

const fieldCategories: FieldCategory[] = [
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
const categories = ref<FieldCategory[]>(fieldCategories.map((c) => ({ ...c })));
const searchQuery = ref('');
const selectedFieldId = ref<string | null>(null);
const dropIndicatorIndex = ref(-1);
const isDraggingOverCanvas = ref(false);
const dragSourceId = ref<string | null>(null);
const activePanel = ref<'fields' | 'settings'>('fields');

const formTitle = ref('');
const formDescription = ref('');
const closedAt = ref('');
const visibleFor = ref<string[]>([]);
const formFields = ref<IFormField[]>([]);
const bannerState = reactive(defaultFormBannerState());
const isSaving = ref(false);
const showPreview = ref(false);
const showMobileMenu = ref(false);

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
const allFieldTypes = computed(() => fieldCategories.flatMap((cat) => cat.fields));
const bannerPreviewSrc = computed(() => normalizeBannerSrc(bannerState.bannerUrl));

// ─── Field factory ─────────────────────────────────────────────
function addField(type: FormBuilderType, label: string) {
    const nf = createField(type, label);
    formFields.value.push(nf);
    selectedFieldId.value = nf.id;
    showMobileMenu.value = false;
}

function createField(type: FormBuilderType, label: string): IFormField {
    const defaults: Record<string, Partial<IFormField>> = {
        short_text: { placeholder: 'Enter answer...' },
        long_text: { placeholder: 'Enter detailed answer...' },
        dropdown: { options: [{ id: crypto.randomUUID(), type: 'text', label: 'Option 1' }] },
        checkbox: { options: [{ id: crypto.randomUUID(), type: 'text', label: 'Choice A' }] },
        radio: { options: [{ id: crypto.randomUUID(), type: 'text', label: 'Radio 1' }] },
        rating: { metadata: { maxStars: 5 } },
        heading: { metadata: { content: 'Section Heading' } },
        paragraph: { metadata: { content: 'Description text goes here...' } },
    };
    return {
        id: crypto.randomUUID(),
        type,
        label: label || 'Untitled Field',
        name: `field_${crypto.randomUUID().replace(/-/g, '').slice(0, 12)}`,
        description: '',
        order: 0,
        metadata: {},
        required: false,
        placeholder: '',
        options: [],
        ...(defaults[type] || {}),
    } as IFormField;
}

// ─── DnD ───────────────────────────────────────────────────────
function onGapDragEnter(index: number) { dropIndicatorIndex.value = index; }
function onCanvasDragOver(e: DragEvent) {
    e.preventDefault();
    if (e.dataTransfer) e.dataTransfer.dropEffect = isDraggingOverCanvas.value ? 'move' : 'copy';
    if (dropIndicatorIndex.value === -1 && isEmpty.value) dropIndicatorIndex.value = 0;
}
function onCanvasDragLeave(e: DragEvent) {
    if (!e.currentTarget || !(e.currentTarget as HTMLElement).contains(e.relatedTarget as Node)) {
        dropIndicatorIndex.value = -1;
        isDraggingOverCanvas.value = false;
    }
}
function onCanvasDrop(e: DragEvent) {
    e.preventDefault();
    const raw = e.dataTransfer?.getData('application/json');
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
function onCanvasDragStart(e: DragEvent, field: IFormField, index: number) {
    dragSourceId.value = field.id;
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('application/json', JSON.stringify({ id: field.id, fromIndex: index, isNew: false }));
    }
    requestAnimationFrame(() => { isDraggingOverCanvas.value = true; });
}
function onDragEnd() {
    dropIndicatorIndex.value = -1;
    isDraggingOverCanvas.value = false;
    dragSourceId.value = null;
}

// ─── Actions ───────────────────────────────────────────────────
function selectField(id: string) { selectedFieldId.value = selectedFieldId.value === id ? null : id; }
function deleteField(id: string) {
    formFields.value = formFields.value.filter((f) => f.id !== id);
    if (selectedFieldId.value === id) selectedFieldId.value = null;
}
function duplicateField(id: string) {
    const i = formFields.value.findIndex((f) => f.id === id);
    if (i === -1) return;
    const copy = JSON.parse(JSON.stringify(formFields.value[i]));
    copy.id = crypto.randomUUID();
    copy.name = `field_${crypto.randomUUID().replace(/-/g, '').slice(0, 12)}`;
    formFields.value.splice(i + 1, 0, copy);
    selectedFieldId.value = copy.id;
}
function updateField(updated: IFormField) {
    const i = formFields.value.findIndex((f) => f.id === updated.id);
    if (i !== -1) formFields.value[i] = updated;
}
function toggleVisibility(value: string, checked: boolean) {
    if (checked) visibleFor.value = [...visibleFor.value, value];
    else visibleFor.value = visibleFor.value.filter((v) => v !== value);
}

// ─── Save ──────────────────────────────────────────────────────
const createForm = useForm({
    title: '',
    description: '',
    closed_at: '',
    visible_for: [] as string[],
    banner_url: '',
    banner_caption: '',
    fields: [] as any[],
});
function saveNewForm() {
    if (!formTitle.value.trim() || !formDescription.value.trim() || !closedAt.value || visibleFor.value.length === 0) {
        toast.error('Please complete all required settings.');
        activePanel.value = 'settings';
        return;
    }
    createForm.title = formTitle.value;
    createForm.description = formDescription.value;
    createForm.closed_at = closedAt.value;
    createForm.visible_for = visibleFor.value;
    createForm.banner_url = bannerState.bannerUrl;
    createForm.banner_caption = bannerState.caption;

    const merged = prependFormBannerToBackendPayload(formFields.value as any, bannerState);
    createForm.fields = toBackendFields(merged as any);

    isSaving.value = true;
    createForm.post(`/dashboard/events/${props.event.id}/forms`, {
        forceFormData: true,
        onSuccess: () => toast.success('Form created successfully!'),
        onError: (err) => {
            isSaving.value = false;
            toast.error(Object.values(err)[0] || 'Failed to create form.');
        },
    });
}
</script>

<template>
    <Head title="Create Form" />
    <div class="flex h-[calc(100svh-3.5rem)] flex-col overflow-hidden lg:flex-row">
        <!-- Sidebar Palette (Desktop) -->
        <aside class="hidden w-[280px] shrink-0 flex-col border-r-2 border-foreground bg-white lg:flex">
            <div class="flex border-b-2 border-foreground">
                <button
                    v-for="panel in (['fields', 'settings'] as const)"
                    :key="panel"
                    class="flex flex-1 items-center justify-center gap-2 py-3 text-[11px] font-black uppercase tracking-widest transition-colors"
                    :class="activePanel === panel ? 'bg-(--brutal-yellow) text-foreground' : 'text-muted-foreground hover:bg-muted/50'"
                    @click="activePanel = panel"
                >
                    <component :is="panel === 'fields' ? Plus : Settings" class="size-3.5" />
                    {{ panel }}
                </button>
            </div>
            
            <div v-show="activePanel === 'fields'" class="flex flex-1 flex-col overflow-hidden">
                <div class="p-4">
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="searchQuery" type="text" placeholder="Search..." class="w-full rounded-xl border-2 border-foreground py-2 pl-9 pr-3 text-xs font-bold shadow-[2px_2px_0_var(--brutal-ink)] focus:outline-none" />
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-4 pb-4 space-y-4">
                    <div v-for="(cat, ci) in filteredCategories" :key="cat.name">
                        <button class="mb-2 flex w-full items-center gap-2 text-[10px] font-black uppercase tracking-widest text-muted-foreground" @click="cat.isOpen = !cat.isOpen">
                            <ChevronRight class="size-3 transition-transform" :class="cat.isOpen ? 'rotate-90' : ''" />
                            {{ cat.name }}
                        </button>
                        <div v-show="cat.isOpen" class="grid gap-2">
                            <DraggableItem v-for="f in cat.fields" :key="f.type" v-bind="f" class="border-2 border-foreground shadow-[3px_3px_0_var(--brutal-ink)]" />
                        </div>
                    </div>
                </div>
            </div>

            <div v-show="activePanel === 'settings'" class="flex-1 overflow-y-auto p-4 space-y-5">
                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-black">Title *</Label>
                        <Input v-model="formTitle" class="border-2 border-foreground shadow-[3px_3px_0_var(--brutal-ink)]" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-black">Description *</Label>
                        <Textarea v-model="formDescription" class="border-2 border-foreground shadow-[3px_3px_0_var(--brutal-ink)]" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-black">Closes At *</Label>
                        <Input v-model="closedAt" type="datetime-local" class="border-2 border-foreground shadow-[3px_3px_0_var(--brutal-ink)]" />
                    </div>
                    <div class="space-y-2">
                        <Label class="text-xs font-black">Visibility *</Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="opt in visibilityOptions" :key="opt.value"
                                type="button"
                                @click="toggleVisibility(opt.value, !visibleFor.includes(opt.value))"
                                :class="[
                                    'rounded-lg border-2 px-3 py-1.5 text-xs font-black transition-all',
                                    visibleFor.includes(opt.value) ? 'bg-(--brutal-mint) shadow-[2px_2px_0_var(--brutal-ink)] -translate-x-0.5 -translate-y-0.5' : 'bg-white hover:bg-muted'
                                ]"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>
                    <FormBannerSettings :model-value="bannerState" @update:model-value="(v) => Object.assign(bannerState, v)" />
                </div>
            </div>
        </aside>

        <!-- Canvas -->
        <main class="relative flex-1 overflow-y-auto bg-muted/20">
            <div class="sticky top-0 z-20 border-b-2 border-foreground bg-white/90 px-4 py-3 backdrop-blur-md">
                <div class="mx-auto flex max-w-2xl items-center justify-between gap-4">
                    <div class="flex items-center gap-2 min-w-0">
                        <Button variant="ghost" size="icon" class="size-8 rounded-xl border-2 border-transparent hover:border-foreground" as-child>
                            <Link :href="`/dashboard/events/${props.event.id}/forms`"><ArrowLeft class="size-4" /></Link>
                        </Button>
                        <div class="min-w-0">
                            <h2 class="truncate text-sm font-black">{{ formTitle || 'New Form' }}</h2>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-tight">{{ formFields.length }} Fields</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" class="h-9 rounded-xl border-2 font-black shadow-[3px_3px_0_var(--brutal-ink)]" @click="showPreview = true">
                            <Eye class="size-4 sm:mr-2" /> <span class="hidden sm:inline">Preview</span>
                        </Button>
                        <Button size="sm" class="h-9 rounded-xl border-2 font-black shadow-[3px_3px_0_var(--brutal-ink)]" :disabled="isSaving" @click="saveNewForm">
                            <Save class="size-4 sm:mr-2" /> <span class="hidden sm:inline">Save Form</span>
                        </Button>
                    </div>
                </div>
            </div>

            <div class="flex justify-center px-4 py-10">
                <div class="w-full max-w-[440px]">
                    <div class="overflow-hidden rounded-3xl border-2 border-foreground bg-white shadow-[10px_10px_0_var(--brutal-ink)]">
                        <!-- Preview Header -->
                        <div v-if="bannerPreviewSrc" class="border-b-2 border-foreground">
                            <img :src="bannerPreviewSrc" class="aspect-[3/1] w-full object-cover" />
                        </div>
                        <div class="border-b-2 border-foreground bg-(--brutal-yellow)/10 p-6">
                            <h1 class="font-display text-2xl font-black">{{ formTitle || 'Untitled Form' }}</h1>
                            <p class="mt-2 text-sm font-bold text-muted-foreground leading-relaxed">{{ formDescription || 'Add a description in settings...' }}</p>
                        </div>

                        <div class="min-h-[400px] p-4 space-y-1" @dragover.prevent="onCanvasDragOver" @dragleave="onCanvasDragLeave" @drop="onCanvasDrop">
                            <div v-if="isEmpty && !isDraggingOverCanvas" class="flex flex-col items-center justify-center py-20 text-center">
                                <LocalLottie name="builderEmpty" :height="120" :width="120" class="opacity-50" />
                                <p class="mt-4 text-sm font-black text-muted-foreground italic">Your canvas is empty</p>
                                <p class="text-[10px] font-bold text-muted-foreground/60 uppercase mt-1">Drag or tap + to add components</p>
                            </div>

                            <template v-for="(field, index) in formFields" :key="field.id">
                                <div class="relative h-1 w-full" @dragenter.prevent="onGapDragEnter(index)">
                                    <div v-if="dropIndicatorIndex === index" class="absolute inset-0 bg-primary h-0.5 rounded-full" />
                                </div>
                                <div class="group relative" :class="dragSourceId === field.id ? 'opacity-20' : ''">
                                    <div class="absolute -left-10 top-1/2 -translate-y-1/2 hidden lg:block">
                                        <div class="p-2 cursor-grab active:cursor-grabbing" draggable="true" @dragstart="(e) => onCanvasDragStart(e, field, index)" @dragend="onDragEnd">
                                            <GripVertical class="size-5 text-muted-foreground/40 hover:text-foreground transition-colors" />
                                        </div>
                                    </div>
                                    <FieldRenderer :field="field" :is-selected="selectedFieldId === field.id" @select="selectField(field.id)" @delete="deleteField(field.id)" @duplicate="duplicateField(field.id)" class="border-2 border-foreground" />
                                </div>
                            </template>
                            <div class="h-4 w-full" @dragenter.prevent="onGapDragEnter(formFields.length)" />
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Right Side (Desktop Properties) -->
        <aside class="hidden w-[320px] shrink-0 flex-col border-l-2 border-foreground bg-white lg:flex">
            <div class="border-b-2 border-foreground p-4 bg-muted/10">
                <h3 class="font-black text-sm uppercase tracking-widest">Properties</h3>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <FieldEditor v-if="selectedField" :field="selectedField" @update:field="updateField" />
                <div v-else class="flex flex-col items-center justify-center py-20 text-center opacity-30">
                    <Settings class="size-10 mb-4" />
                    <p class="text-xs font-black uppercase">No field selected</p>
                </div>
            </div>
        </aside>

        <!-- Mobile Floating Action Button -->
        <Sheet v-model:open="showMobileMenu">
            <SheetTrigger as-child>
                <Button size="icon-lg" class="fixed bottom-6 right-6 z-50 size-14 rounded-full border-2 border-foreground shadow-[4px_4px_0_var(--brutal-ink)] lg:hidden">
                    <Plus class="size-6" />
                </Button>
            </SheetTrigger>
            <SheetContent side="bottom" class="h-[85vh] rounded-t-3xl border-t-4 border-foreground p-0 overflow-hidden flex flex-col">
                <div class="flex border-b-2 border-foreground shrink-0">
                    <button v-for="t in (['components', 'settings'] as const)" :key="t" @click="activePanel = (t === 'components' ? 'fields' : 'settings')" class="flex-1 py-4 text-xs font-black uppercase tracking-widest" :class="((activePanel === 'fields' && t === 'components') || (activePanel === 'settings' && t === 'settings')) ? 'bg-(--brutal-yellow)' : ''">
                        {{ t }}
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-6 bg-white">
                    <div v-show="activePanel === 'fields'" class="space-y-6">
                        <div v-for="cat in fieldCategories" :key="cat.name">
                            <h4 class="text-[10px] font-black uppercase tracking-widest text-muted-foreground mb-3">{{ cat.name }}</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <button v-for="f in cat.fields" :key="f.type" @click="addField(f.type, f.label)" class="flex flex-col items-center gap-2 rounded-2xl border-2 border-foreground p-4 text-center transition-all active:translate-x-0.5 active:translate-y-0.5 active:shadow-none hover:bg-muted shadow-[3px_3px_0_var(--brutal-ink)]">
                                    <component :is="f.icon" class="size-5" />
                                    <span class="text-[10px] font-black leading-tight">{{ f.label }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-show="activePanel === 'settings'" class="space-y-6">
                        <div class="space-y-4">
                            <div class="space-y-1.5"><Label class="text-xs font-black uppercase">Title</Label><Input v-model="formTitle" class="border-2 border-foreground" /></div>
                            <div class="space-y-1.5"><Label class="text-xs font-black uppercase">Description</Label><Textarea v-model="formDescription" class="border-2 border-foreground" /></div>
                            <div class="space-y-1.5"><Label class="text-xs font-black uppercase">Close Date</Label><Input v-model="closedAt" type="datetime-local" class="border-2 border-foreground" /></div>
                            <FormBannerSettings :model-value="bannerState" @update:model-value="(v) => Object.assign(bannerState, v)" />
                        </div>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <!-- Mobile Property Drawer (Auto-opens on select) -->
        <Sheet :open="!!selectedField && showMobileMenu === false" @update:open="(v) => !v && (selectedFieldId = null)">
            <SheetContent side="right" class="w-full sm:max-w-md border-l-4 border-foreground p-0 flex flex-col">
                <SheetHeader class="p-6 border-b-2 border-foreground bg-muted/10 shrink-0">
                    <SheetTitle class="font-black uppercase tracking-widest">Field Properties</SheetTitle>
                    <SheetDescription class="font-bold">Customize your component below.</SheetDescription>
                </SheetHeader>
                <div class="flex-1 overflow-y-auto p-6 bg-white">
                    <FieldEditor v-if="selectedField" :field="selectedField" @update:field="updateField" />
                </div>
                <div class="p-6 border-t-2 border-foreground bg-muted/5 shrink-0">
                    <Button class="w-full rounded-xl border-2 font-black h-12 shadow-[4px_4px_0_var(--brutal-ink)]" @click="selectedFieldId = null">Done Editing</Button>
                </div>
            </SheetContent>
        </Sheet>
    </div>

    <FormPreviewDialog :open="showPreview" :title="formTitle || 'Untitled Form'" :description="formDescription" :form-banner-url="bannerState.bannerUrl" :form-banner-caption="bannerState.caption" :fields="formFields" @close="showPreview = false" />
</template>

<style scoped>
.brutal-divider { border: none; border-top: 2px solid var(--foreground); opacity: 0.1; }
</style>
