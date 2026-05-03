<script setup lang="ts">
import { computed } from 'vue'
import { optionLabel, optionImageUrl, type FieldOptionEntry } from '@/components/modules/builder/fieldMapping'
import { normalizeBannerSrc } from '@/components/modules/builder/formBanner'
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
    Trash2,
    Copy,
} from 'lucide-vue-next'

const props = defineProps({
    field: { type: Object, required: true },
    isSelected: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'delete', 'duplicate'])

const TYPE_CONFIG = {
    short_text: { icon: Type, label: 'Short Text', accent: '#2563eb' },
    long_text: { icon: AlignLeft, label: 'Long Text', accent: '#4f46e5' },
    email: { icon: Mail, label: 'Email', accent: '#0891b2' },
    phone: { icon: Phone, label: 'Phone', accent: '#059669' },
    number: { icon: Hash, label: 'Number', accent: '#d97706' },
    dropdown: { icon: ChevronDown, label: 'Dropdown', accent: '#7c3aed' },
    checkbox: { icon: SquareCheck, label: 'Checkbox', accent: '#db2777' },
    radio: { icon: CircleDot, label: 'Radio', accent: '#e11d48' },
    image_upload: { icon: ImagePlus, label: 'Image', accent: '#0d9488' },
    file_upload: { icon: Upload, label: 'File', accent: '#475569' },
    date: { icon: Calendar, label: 'Date', accent: '#7c3aed' },
    time: { icon: Clock, label: 'Time', accent: '#059669' },
    rating: { icon: Star, label: 'Rating', accent: '#f59e0b' },
    heading: { icon: HeadingIcon, label: 'Heading', accent: '#1a1a2e' },
    paragraph: { icon: TextCursorInput, label: 'Paragraph', accent: '#6b7280' },
    divider: { icon: Minus, label: 'Divider', accent: '#9ca3af' },
}

const config = computed(() => TYPE_CONFIG[props.field.type] || TYPE_CONFIG.short_text)

const filledStars = computed(() => props.field.metadata?.maxStars ?? 5)

const choiceOptions = computed((): FieldOptionEntry[] => {
    const raw = props.field.options
    if (Array.isArray(raw) && raw.length > 0) return raw as FieldOptionEntry[]
    return ['Option 1', 'Option 2', 'Option 3']
})

function choiceImageSrc(entry: FieldOptionEntry): string | undefined {
    const u = optionImageUrl(entry)
    return u ? normalizeBannerSrc(u) : undefined
}
</script>

<template>
    <div
        class="group relative cursor-pointer rounded-xl border-[1.5px] transition-all duration-200"
        :class="[
            isSelected
                ? 'border-[var(--brutal-blue)] bg-[var(--brutal-blue)]/[0.03] shadow-[0_0_0_3px_rgba(37,99,235,0.12)]'
                : 'border-[var(--brutal-ink)]/10 bg-white hover:border-[var(--brutal-ink)]/20',
        ]"
        @click="emit('select')"
    >
        <!-- Type badge + actions bar -->
        <div class="flex items-center justify-between px-4 pt-3 pb-1">
            <span
                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white"
                :style="{ backgroundColor: config.accent }"
            >
                <component :is="config.icon" class="size-3" />
                {{ config.label }}
            </span>
            <div class="flex gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                <button
                    class="flex size-7 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted/50 hover:text-[var(--brutal-ink)]"
                    title="Duplicate"
                    @click.stop="emit('duplicate')"
                >
                    <Copy class="size-3.5" />
                </button>
                <button
                    class="flex size-7 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                    title="Delete"
                    @click.stop="emit('delete')"
                >
                    <Trash2 class="size-3.5" />
                </button>
            </div>
        </div>

        <!-- Field content -->
        <div class="px-4 pt-1 pb-4">
            <!-- Label -->
            <label class="mb-0.5 block font-display text-sm font-bold text-[var(--brutal-ink)]">
                {{ field.label || 'Untitled Field' }}
                <span v-if="field.required" class="text-destructive">*</span>
            </label>
            <p v-if="field.description" class="mb-2 text-[11px] leading-snug text-muted-foreground">
                {{ field.description }}
            </p>

            <!-- Preview by type -->
            <div class="mt-2">
                <!-- Short text / Email / Phone / Number -->
                <div
                    v-if="['short_text', 'email', 'phone', 'number'].includes(field.type)"
                    class="flex items-center gap-2 rounded-lg border-[1.5px] border-[var(--brutal-ink)]/10 bg-muted/20 px-3 py-2.5"
                >
                    <component :is="config.icon" class="size-4 shrink-0 text-muted-foreground/50" />
                    <span class="text-xs text-muted-foreground/60">
                        {{ field.placeholder || `Enter ${config.label.toLowerCase()}...` }}
                    </span>
                </div>

                <!-- Long text -->
                <div
                    v-else-if="field.type === 'long_text'"
                    class="rounded-lg border-[1.5px] border-[var(--brutal-ink)]/10 bg-muted/20 px-3 py-2.5"
                >
                    <span class="text-xs text-muted-foreground/60">
                        {{ field.placeholder || 'Enter your answer here...' }}
                    </span>
                    <div class="mt-3 border-t border-dashed border-[var(--brutal-ink)]/8 pt-2">
                        <div class="h-1.5 w-2/3 rounded-full bg-[var(--brutal-ink)]/6"></div>
                    </div>
                    <div class="mt-1.5">
                        <div class="h-1.5 w-1/2 rounded-full bg-[var(--brutal-ink)]/4"></div>
                    </div>
                </div>

                <!-- Dropdown -->
                <div
                    v-else-if="field.type === 'dropdown'"
                    class="flex items-center justify-between rounded-lg border-[1.5px] border-[var(--brutal-ink)]/10 bg-muted/20 px-3 py-2.5"
                >
                    <span class="text-xs text-muted-foreground/60">Select an option...</span>
                    <ChevronDown class="size-4 text-muted-foreground/40" />
                </div>

                <!-- Checkbox -->
                <div v-else-if="field.type === 'checkbox'" class="flex flex-col gap-2">
                    <label
                        v-for="(opt, i) in choiceOptions"
                        :key="i"
                        class="flex items-center gap-2.5 text-xs text-[var(--brutal-ink)]/70"
                    >
                        <div
                            class="flex size-4 shrink-0 items-center justify-center rounded border-[1.5px] border-[var(--brutal-ink)]/20 bg-white"
                        ></div>
                        <img
                            v-if="choiceImageSrc(opt)"
                            :src="choiceImageSrc(opt)"
                            alt=""
                            class="size-7 shrink-0 rounded-md border border-[var(--brutal-ink)]/10 object-cover"
                        />
                        <span>{{ optionLabel(opt) }}</span>
                    </label>
                </div>

                <!-- Radio -->
                <div v-else-if="field.type === 'radio'" class="flex flex-col gap-2">
                    <label
                        v-for="(opt, i) in choiceOptions"
                        :key="i"
                        class="flex items-center gap-2.5 text-xs text-[var(--brutal-ink)]/70"
                    >
                        <div
                            class="flex size-4 shrink-0 items-center justify-center rounded-full border-[1.5px] border-[var(--brutal-ink)]/20 bg-white"
                        ></div>
                        <img
                            v-if="choiceImageSrc(opt)"
                            :src="choiceImageSrc(opt)"
                            alt=""
                            class="size-7 shrink-0 rounded-full border border-[var(--brutal-ink)]/10 object-cover"
                        />
                        <span>{{ optionLabel(opt) }}</span>
                    </label>
                </div>

                <!-- Image upload -->
                <div
                    v-else-if="field.type === 'image_upload'"
                    class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-[var(--brutal-ink)]/15 bg-muted/10 py-6"
                >
                    <div
                        class="mb-2 flex size-10 items-center justify-center rounded-xl bg-primary/8 text-primary"
                    >
                        <ImagePlus class="size-5" />
                    </div>
                    <p class="text-xs font-semibold text-muted-foreground">Click or drag to upload</p>
                    <p class="mt-0.5 text-[10px] text-muted-foreground/60">PNG, JPG up to 5MB</p>
                </div>

                <!-- File upload -->
                <div
                    v-else-if="field.type === 'file_upload'"
                    class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-[var(--brutal-ink)]/15 bg-muted/10 py-6"
                >
                    <div
                        class="mb-2 flex size-10 items-center justify-center rounded-xl bg-muted/50 text-muted-foreground"
                    >
                        <Upload class="size-5" />
                    </div>
                    <p class="text-xs font-semibold text-muted-foreground">Upload a file</p>
                    <p class="mt-0.5 text-[10px] text-muted-foreground/60">PDF, DOC, XLS up to 10MB</p>
                </div>

                <!-- File upload -->
                <div
                    v-else-if="field.type === 'date'"
                    class="flex items-center gap-2 rounded-lg border-[1.5px] border-[var(--brutal-ink)]/10 bg-muted/20 px-3 py-2.5"
                >
                    <Calendar class="size-4 shrink-0 text-muted-foreground/50" />
                    <span class="text-xs text-muted-foreground/60">dd/mm/yyyy</span>
                </div>

                <!-- Time -->
                <div
                    v-else-if="field.type === 'time'"
                    class="flex items-center gap-2 rounded-lg border-[1.5px] border-[var(--brutal-ink)]/10 bg-muted/20 px-3 py-2.5"
                >
                    <Clock class="size-4 shrink-0 text-muted-foreground/50" />
                    <span class="text-xs text-muted-foreground/60">--:-- AM</span>
                </div>

                <!-- Rating -->
                <div v-else-if="field.type === 'rating'" class="flex gap-1.5">
                    <Star
                        v-for="i in filledStars"
                        :key="i"
                        class="size-6 text-amber-400"
                        :fill="i <= 3 ? '#fbbf24' : 'none'"
                    />
                </div>

                <!-- Heading -->
                <div v-else-if="field.type === 'heading'">
                    <h3 class="font-display text-lg font-bold text-[var(--brutal-ink)]">
                        {{ field.metadata?.content || 'Section Heading' }}
                    </h3>
                </div>

                <!-- Paragraph -->
                <div v-else-if="field.type === 'paragraph'">
                    <p class="text-sm leading-relaxed text-muted-foreground">
                        {{ field.metadata?.content || 'Add descriptive text to guide users through your form.' }}
                    </p>
                </div>

                <!-- Divider -->
                <div v-else-if="field.type === 'divider'" class="py-1">
                    <hr class="brutal-divider" />
                </div>
            </div>
        </div>
    </div>
</template>
