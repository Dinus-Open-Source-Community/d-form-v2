<script setup lang="ts">
import { computed, ref } from 'vue'
import { optionLabel, optionImageUrl, type FieldOptionEntry } from '@/components/modules/builder/fieldMapping'
import { Switch } from '@/components/ui/switch'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Button } from '@/components/ui/button'
import {
    Type, AlignLeft, Mail, Phone, Hash, ChevronDown, SquareCheck, CircleDot,
    ImagePlus, Upload, Calendar, Clock, Star,
    Heading as HeadingIcon, TextCursorInput, Minus, Plus, X,
} from 'lucide-vue-next'

const props = defineProps({
    field: { type: Object, required: true },
})

const emit = defineEmits(['update:field'])

const TYPE_CONFIG = {
    short_text: { icon: Type, label: 'Short Text', accent: '#2563eb' },
    long_text: { icon: AlignLeft, label: 'Long Text', accent: '#4f46e5' },
    email: { icon: Mail, label: 'Email', accent: '#0891b2' },
    phone: { icon: Phone, label: 'Phone', accent: '#059669' },
    number: { icon: Hash, label: 'Number', accent: '#d97706' },
    dropdown: { icon: ChevronDown, label: 'Dropdown', accent: '#7c3aed' },
    checkbox: { icon: SquareCheck, label: 'Checkbox', accent: '#db2777' },
    radio: { icon: CircleDot, label: 'Radio', accent: '#e11d48' },
    image_upload: { icon: ImagePlus, label: 'Image Upload', accent: '#0d9488' },
    file_upload: { icon: Upload, label: 'File Upload', accent: '#475569' },
    date: { icon: Calendar, label: 'Date', accent: '#7c3aed' },
    time: { icon: Clock, label: 'Time', accent: '#059669' },
    rating: { icon: Star, label: 'Rating', accent: '#f59e0b' },
    heading: { icon: HeadingIcon, label: 'Heading', accent: '#1a1a2e' },
    paragraph: { icon: TextCursorInput, label: 'Paragraph', accent: '#6b7280' },
    divider: { icon: Minus, label: 'Divider', accent: '#9ca3af' },
}

const config = computed(() => TYPE_CONFIG[props.field.type] || TYPE_CONFIG.short_text)

// --- helpers to mutate without losing reactivity ---
function update(key, value) {
    emit('update:field', { ...props.field, [key]: value })
}
function updateMeta(key, value) {
    const meta = { ...(props.field.metadata || {}) }
    meta[key] = value
    emit('update:field', { ...props.field, metadata: meta })
}

// --- option list helpers (for checkbox / radio / dropdown) ---
const newOption = ref('')

function optionRows(): FieldOptionEntry[] {
    const raw = props.field.options
    return Array.isArray(raw) ? (raw as FieldOptionEntry[]) : []
}

function emitOptions(next: FieldOptionEntry[]) {
    emit('update:field', { ...props.field, options: next })
}

function addOption() {
    const text = newOption.value.trim()
    if (!text) return
    emitOptions([...optionRows(), { label: text, imageUrl: '' }])
    newOption.value = ''
}

function removeOption(index: number) {
    const opts = [...optionRows()]
    opts.splice(index, 1)
    emitOptions(opts)
}

function setOptionLabel(index: number, label: string) {
    const opts = [...optionRows()]
    const cur = opts[index]
    const img = optionImageUrl(cur as FieldOptionEntry)?.trim() ?? ''
    const trimmed = label.trim()
    if (!trimmed) {
        opts.splice(index, 1)
        emitOptions(opts)
        return
    }
    opts[index] = img ? { label: trimmed, imageUrl: img } : trimmed
    emitOptions(opts)
}

function setOptionImageUrl(index: number, url: string) {
    const opts = [...optionRows()]
    const cur = opts[index]
    const lab = optionLabel(cur as FieldOptionEntry)
    if (!lab) return
    const u = url.trim()
    opts[index] = u ? { label: lab, imageUrl: u } : lab
    emitOptions(opts)
}

function onOptionImageFile(index: number, event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file || !file.type.startsWith('image/')) return
    const reader = new FileReader()
    reader.onload = () => {
        if (typeof reader.result !== 'string') return
        setOptionImageUrl(index, reader.result)
    }
    reader.readAsDataURL(file)
}

const hasPlaceholder = computed(() =>
    ['short_text', 'long_text', 'email', 'phone', 'number'].includes(props.field.type),
)
const hasOptions = computed(() =>
    ['dropdown', 'checkbox', 'radio'].includes(props.field.type),
)
const isContent = computed(() =>
    ['heading', 'paragraph', 'divider'].includes(props.field.type),
)
</script>

<template>
    <div class="flex flex-col gap-5">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div
                class="flex size-9 shrink-0 items-center justify-center rounded-xl text-white"
                :style="{ backgroundColor: config.accent }"
            >
                <component :is="config.icon" class="size-4.5" />
            </div>
            <div>
                <h3 class="font-display text-base font-bold text-[var(--brutal-ink)]">Field Properties</h3>
                <p class="text-[11px] font-medium text-muted-foreground">
                    {{ config.label }} field
                </p>
            </div>
        </div>

        <hr class="brutal-divider" />

        <!-- Label -->
        <div class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Label</Label>
            <Input
                :model-value="field.label"
                placeholder="e.g. Full Name"
                class="text-xs"
                @update:model-value="(v) => update('label', v)"
            />
            <p class="text-[10px] text-muted-foreground">
                The label shown above the field.
            </p>
        </div>

        <!-- Description -->
        <div v-if="!isContent" class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Help Text</Label>
            <Textarea
                :model-value="field.description"
                placeholder="Brief instruction for the user"
                rows="2"
                class="text-xs"
                @update:model-value="(v) => update('description', v)"
            />
        </div>

        <!-- Placeholder (text fields only) -->
        <div v-if="hasPlaceholder" class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Placeholder</Label>
            <Input
                :model-value="field.placeholder"
                placeholder="e.g. Enter your name..."
                class="text-xs"
                @update:model-value="(v) => update('placeholder', v)"
            />
        </div>

        <!-- Content text (heading / paragraph) -->
        <div v-if="field.type === 'heading'" class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Heading Text</Label>
            <Input
                :model-value="field.metadata?.content ?? ''"
                placeholder="Section Heading"
                class="text-xs"
                @update:model-value="(v) => updateMeta('content', v)"
            />
        </div>
        <div v-if="field.type === 'paragraph'" class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Paragraph Text</Label>
            <Textarea
                :model-value="field.metadata?.content ?? ''"
                placeholder="Descriptive text..."
                rows="3"
                class="text-xs"
                @update:model-value="(v) => updateMeta('content', v)"
            />
        </div>

        <!-- Rating max stars -->
        <div v-if="field.type === 'rating'" class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Max Stars</Label>
            <Input
                type="number"
                :model-value="field.metadata?.maxStars ?? 5"
                min="1"
                max="10"
                class="text-xs"
                @update:model-value="(v) => updateMeta('maxStars', Number(v))"
            />
        </div>

        <!-- File types (image / file / banner) -->
        <div v-if="['image_upload', 'file_upload'].includes(field.type)" class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Accepted Formats</Label>
            <Input
                :model-value="field.metadata?.accepts ?? ''"
                :placeholder="field.type === 'banner' ? 'gif, png, jpg' : 'pdf, jpg, png'"
                class="text-xs"
                @update:model-value="(v) => updateMeta('accepts', v)"
            />
            <p class="text-[10px] text-muted-foreground">Comma-separated file extensions.</p>
        </div>

        <!-- Options editor (dropdown / checkbox / radio) -->
        <div v-if="hasOptions" class="flex flex-col gap-2">
            <Label class="text-xs font-semibold">Options</Label>
            <p class="text-[10px] text-muted-foreground">
                Add text for each choice. Optionally add an image URL or upload a picture — shown next to the label for respondents.
            </p>
            <div class="flex flex-col gap-2">
                <div
                    v-for="(opt, i) in optionRows()"
                    :key="i"
                    class="rounded-xl border border-[var(--brutal-ink)]/12 bg-muted/15 p-2.5"
                >
                    <div class="flex items-start gap-2">
                        <div
                            v-if="optionImageUrl(opt as FieldOptionEntry)"
                            class="size-11 shrink-0 overflow-hidden rounded-lg border border-[var(--brutal-ink)]/15 bg-white"
                        >
                            <img
                                :src="optionImageUrl(opt as FieldOptionEntry) ?? ''"
                                alt=""
                                class="size-full object-cover"
                            />
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                            <Input
                                :model-value="optionLabel(opt as FieldOptionEntry)"
                                placeholder="Choice label"
                                class="text-xs"
                                @update:model-value="(v) => setOptionLabel(i, String(v ?? ''))"
                            />
                            <Input
                                :model-value="optionImageUrl(opt as FieldOptionEntry) ?? ''"
                                placeholder="Image URL (optional)"
                                class="text-[11px]"
                                @update:model-value="(v) => setOptionImageUrl(i, String(v ?? ''))"
                            />
                        </div>
                        <div class="flex shrink-0 flex-col gap-1">
                            <label
                                class="flex cursor-pointer items-center justify-center rounded-lg border border-[var(--brutal-ink)]/15 bg-white px-2 py-1.5 text-[10px] font-semibold text-muted-foreground transition-colors hover:bg-muted/50"
                            >
                                <ImagePlus class="mr-1 size-3.5" />
                                Upload
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="sr-only"
                                    @change="onOptionImageFile(i, $event)"
                                />
                            </label>
                            <button
                                type="button"
                                class="flex size-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                title="Remove option"
                                @click="removeOption(i)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                <Input
                    v-model="newOption"
                    placeholder="New option label..."
                    class="text-xs"
                    @keydown.enter.prevent="addOption"
                />
                <Button variant="outline" size="sm" class="shrink-0 text-xs" type="button" @click="addOption">
                    <Plus class="mr-1 size-3" />Add
                </Button>
            </div>
            <p class="text-[10px] text-muted-foreground">
                Press Enter or click Add. Images appear in dropdowns and choice lists.
            </p>
        </div>

        <!-- Required toggle (not for content types) -->
        <div v-if="!isContent" class="mt-1">
            <hr class="brutal-divider mb-4" />
            <div class="flex items-center justify-between">
                <div>
                    <Label class="text-xs font-semibold">Required</Label>
                    <p class="text-[10px] text-muted-foreground">User must fill this field</p>
                </div>
                <Switch
                    :checked="!!field.required"
                    @update:checked="(v) => update('required', v)"
                />
            </div>
        </div>
    </div>
</template>
