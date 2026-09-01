<script setup lang="ts">
import { computed } from 'vue'
import { optionLabel, optionImageUrl } from '@/components/modules/builder/fieldMapping'
import FormParagraphContent from '@/components/modules/dashboard/FormParagraphContent.vue'
import { normalizeBannerSrc } from '@/components/modules/builder/formBanner'
import type { BuilderField, FieldOptionEntry } from '@/types/form-builder'
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

const props = withDefaults(
    defineProps<{
        field: BuilderField
        isSelected?: boolean
    }>(),
    { isSelected: false },
)

const emit = defineEmits<{
    (event: 'select'): void
    (event: 'delete'): void
    (event: 'duplicate'): void
}>()

const TYPE_CONFIG = {
    short_text: { icon: Type, label: 'Teks pendek', accent: '#2563eb' },
    long_text: { icon: AlignLeft, label: 'Teks panjang', accent: '#4f46e5' },
    email: { icon: Mail, label: 'Email', accent: '#0891b2' },
    phone: { icon: Phone, label: 'Telepon', accent: '#059669' },
    number: { icon: Hash, label: 'Angka', accent: '#d97706' },
    dropdown: { icon: ChevronDown, label: 'Dropdown', accent: '#7c3aed' },
    checkbox: { icon: SquareCheck, label: 'Centang', accent: '#db2777' },
    radio: { icon: CircleDot, label: 'Pilihan tunggal', accent: '#e11d48' },
    image_upload: { icon: ImagePlus, label: 'Gambar', accent: '#0d9488' },
    file_upload: { icon: Upload, label: 'File', accent: '#475569' },
    date: { icon: Calendar, label: 'Tanggal', accent: '#7c3aed' },
    time: { icon: Clock, label: 'Waktu', accent: '#059669' },
    rating: { icon: Star, label: 'Rating', accent: '#f59e0b' },
    heading: { icon: HeadingIcon, label: 'Judul', accent: '#1a1a2e' },
    paragraph: { icon: TextCursorInput, label: 'Paragraf', accent: '#6b7280' },
    divider: { icon: Minus, label: 'Pemisah', accent: '#9ca3af' },
}

const config = computed(() => TYPE_CONFIG[props.field.type as keyof typeof TYPE_CONFIG] || TYPE_CONFIG.short_text)

/** Petunjuk di kanvas bila admin belum mengisi placeholder — hanya tampilan, bukan nilai tersimpan. */
function canvasPlaceholder(f: BuilderField): string {
    const custom = String(f.placeholder ?? '').trim()
    if (custom) return custom
    const byType: Record<string, string> = {
        short_text: 'Ketik jawaban singkat…',
        long_text: 'Tulis jawaban di sini…',
        email: 'nama@email.com',
        phone: 'Nomor WhatsApp / telepon',
        number: 'Masukkan angka',
    }
    return byType[f.type] ?? 'Ketik di sini…'
}

const filledStars = computed(() => props.field.metadata?.maxStars ?? 5)

const choiceOptions = computed((): FieldOptionEntry[] => {
    const raw = props.field.options
    if (Array.isArray(raw) && raw.length > 0) return raw as FieldOptionEntry[]
    return ['Option 1', 'Option 2', 'Option 3'].map((label) => ({
        id: label.toLowerCase().replace(/\s+/g, '-'),
        type: 'text',
        label,
    }))
})

function choiceImageSrc(entry: FieldOptionEntry): string | undefined {
    const u = optionImageUrl(entry)
    return u ? normalizeBannerSrc(u) : undefined
}
</script>

<template>
    <div
        class="group relative cursor-pointer rounded-2xl border transition-[border-color,background-color,box-shadow] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
        :class="[
            isSelected
                ? 'border-primary bg-primary/[0.04] shadow-md ring-2 ring-primary/20'
                : 'border-border bg-card shadow-sm hover:border-primary/35 hover:shadow-md',
        ]"
        @click="emit('select')"
    >
        <!-- Type badge + actions bar -->
        <div class="flex items-center justify-between px-5 pt-4 pb-2">
            <span
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white"
                :style="{ backgroundColor: config.accent }"
            >
                <component :is="config.icon" class="size-3.5" />
                {{ config.label }}
            </span>
            <div class="flex gap-1 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                <button
                    class="grid size-8 place-items-center rounded-lg text-muted-foreground transition-colors duration-200 hover:bg-muted hover:text-foreground"
                    title="Gandakan"
                    @click.stop="emit('duplicate')"
                >
                    <Copy class="size-3.5" />
                </button>
                <button
                    class="grid size-8 place-items-center rounded-lg text-muted-foreground transition-colors duration-200 hover:bg-destructive/10 hover:text-destructive"
                    title="Hapus"
                    @click.stop="emit('delete')"
                >
                    <Trash2 class="size-3.5" />
                </button>
            </div>
        </div>

        <!-- Field content -->
        <div class="space-y-3 px-5 pb-5 pt-1">
            <!-- Label -->
            <label class="block font-display text-[15px] font-semibold tracking-tight text-foreground">
                {{ field.label || 'Untitled Field' }}
                <span v-if="field.required" class="text-destructive">*</span>
            </label>
            <p v-if="field.description" class="-mt-1 text-xs leading-relaxed text-muted-foreground">
                {{ field.description }}
            </p>

            <!-- Preview by type -->
            <div class="mt-1">
                <!-- Short text / Email / Phone / Number -->
                <div
                    v-if="['short_text', 'email', 'phone', 'number'].includes(field.type)"
                    class="rounded-xl border border-border bg-muted/25 p-2"
                >
                    <input
                        :type="
                            field.type === 'email'
                                ? 'email'
                                : field.type === 'phone'
                                  ? 'tel'
                                  : field.type === 'number'
                                    ? 'text'
                                    : 'text'
                        "
                        readonly
                        tabindex="-1"
                        value=""
                        :inputmode="field.type === 'number' ? 'decimal' : undefined"
                        :placeholder="canvasPlaceholder(field)"
                        class="pointer-events-none h-11 w-full rounded-full border border-border/80 bg-background px-4 text-sm text-foreground shadow-xs placeholder:text-muted-foreground/70"
                    />
                </div>

                <!-- Long text -->
                <div
                    v-else-if="field.type === 'long_text'"
                    class="rounded-xl border border-border bg-muted/25 p-2"
                >
                    <textarea
                        readonly
                        tabindex="-1"
                        rows="3"
                        :placeholder="canvasPlaceholder(field)"
                        class="pointer-events-none min-h-[4.75rem] w-full resize-none rounded-xl border border-border/80 bg-background px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground/70"
                    ></textarea>
                    <div class="mt-4 space-y-2 border-t border-dashed border-border/70 pt-3">
                        <div class="h-2 w-2/3 rounded-full bg-muted/70"></div>
                        <div class="h-2 w-1/2 rounded-full bg-muted/50"></div>
                    </div>
                </div>

                <!-- Dropdown -->
                <div v-else-if="field.type === 'dropdown'" class="space-y-2">
                    <div
                        class="flex items-center justify-between rounded-xl border border-border bg-muted/25 px-4 py-3"
                    >
                        <span class="text-sm text-muted-foreground/75">{{
                            String(field.placeholder ?? '').trim() || 'Pilih salah satu…'
                        }}</span>
                        <ChevronDown class="size-4 text-muted-foreground/50" />
                    </div>
                    <div class="space-y-1.5 rounded-xl border border-border/70 bg-card p-2.5">
                        <div
                            v-for="(opt, i) in choiceOptions"
                            :key="opt.id || i"
                            class="flex items-center gap-2.5 rounded-lg border border-border/60 bg-muted/20 px-2.5 py-2 text-xs text-foreground/85"
                        >
                            <div
                                v-if="opt.type === 'image' && choiceImageSrc(opt)"
                                class="size-10 shrink-0 overflow-hidden rounded-md border border-border"
                            >
                                <img
                                    :src="choiceImageSrc(opt)"
                                    alt=""
                                    class="size-full object-cover"
                                />
                            </div>
                            <span v-else>{{ optionLabel(opt) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Checkbox -->
                <div v-else-if="field.type === 'checkbox'" class="flex flex-col gap-2">
                    <label
                        v-for="(opt, i) in choiceOptions"
                        :key="opt.id || i"
                        class="flex items-center gap-2.5 text-xs text-foreground/80"
                    >
                        <div
                            class="flex size-4 shrink-0 items-center justify-center rounded border border-input bg-card"
                        ></div>
                        <div v-if="opt.type === 'image' && choiceImageSrc(opt)" class="size-12 shrink-0 overflow-hidden rounded-md border border-border">
                            <img
                                :src="choiceImageSrc(opt)"
                                alt=""
                                class="size-full object-cover"
                            />
                        </div>
                        <span v-else>{{ optionLabel(opt) }}</span>
                    </label>
                </div>

                <!-- Radio -->
                <div v-else-if="field.type === 'radio'" class="flex flex-col gap-2">
                    <label
                        v-for="(opt, i) in choiceOptions"
                        :key="opt.id || i"
                        class="flex items-center gap-2.5 text-xs text-foreground/80"
                    >
                        <div
                            class="flex size-4 shrink-0 items-center justify-center rounded-full border border-input bg-card"
                        ></div>
                        <div v-if="opt.type === 'image' && choiceImageSrc(opt)" class="size-12 shrink-0 overflow-hidden rounded-full border border-border">
                            <img
                                :src="choiceImageSrc(opt)"
                                alt=""
                                class="size-full object-cover"
                            />
                        </div>
                        <span v-else>{{ optionLabel(opt) }}</span>
                    </label>
                </div>

                <!-- Image upload -->
                <div
                    v-else-if="field.type === 'image_upload'"
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 py-6"
                >
                    <div
                        class="mb-2 flex size-10 items-center justify-center rounded-xl bg-primary/8 text-primary"
                    >
                        <ImagePlus class="size-5" />
                    </div>
                    <p class="text-xs font-semibold text-muted-foreground">Ketuk atau jatuhkan gambar di sini</p>
                    <p class="mt-0.5 text-[10px] text-muted-foreground/60">PNG, JPG hingga 5 MB</p>
                    <div class="mt-2 flex flex-wrap justify-center gap-1">
                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">4:3</span>
                        <span class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground"
                            >tengah</span
                        >
                    </div>
                </div>

                <!-- File upload -->
                <div
                    v-else-if="field.type === 'file_upload'"
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 py-6"
                >
                    <div
                        class="mb-2 flex size-10 items-center justify-center rounded-xl bg-muted/50 text-muted-foreground"
                    >
                        <Upload class="size-5" />
                    </div>
                    <p class="text-xs font-semibold text-muted-foreground">Unggah file di sini</p>
                    <p class="mt-0.5 text-[10px] text-muted-foreground/60">PDF, DOC, XLS hingga 10 MB</p>
                </div>

                <!-- File upload -->
                <div
                    v-else-if="field.type === 'date'"
                    class="rounded-xl border border-border bg-muted/25 p-2"
                >
                    <input
                        type="text"
                        readonly
                        tabindex="-1"
                        value=""
                        :placeholder="String(field.placeholder ?? '').trim() || 'Pilih tanggal'"
                        class="pointer-events-none h-11 w-full rounded-full border border-border/80 bg-background px-4 text-sm text-foreground placeholder:text-muted-foreground/70"
                    />
                </div>

                <!-- Time -->
                <div
                    v-else-if="field.type === 'time'"
                    class="rounded-xl border border-border bg-muted/25 p-2"
                >
                    <input
                        type="text"
                        readonly
                        tabindex="-1"
                        value=""
                        :placeholder="String(field.placeholder ?? '').trim() || 'Pilih jam (contoh: 09:30)'"
                        class="pointer-events-none h-11 w-full rounded-full border border-border/80 bg-background px-4 text-sm text-foreground placeholder:text-muted-foreground/70"
                    />
                </div>

                <!-- Rating -->
                <div v-else-if="field.type === 'rating'" class="flex flex-col gap-2">
                    <div class="flex items-center gap-2">
                        <Star
                            v-for="i in filledStars"
                            :key="i"
                            class="size-6 text-amber-400"
                            :fill="i <= 3 ? '#fbbf24' : 'none'"
                        />
                    </div>
                    <p class="text-[10px] text-muted-foreground/80">Tap bintang untuk nilai</p>
                </div>

                <!-- Heading -->
                <div v-else-if="field.type === 'heading'">
                    <h3 class="font-display text-lg font-bold text-foreground">
                        {{ field.metadata?.content || 'Judul bagian' }}
                    </h3>
                </div>

                <!-- Paragraph -->
                <div v-else-if="field.type === 'paragraph'">
                    <FormParagraphContent
                        :content="String(field.metadata?.content ?? '')"
                        fallback="Teks penjelasan untuk pengisi form."
                    />
                </div>

                <!-- Divider -->
                <div v-else-if="field.type === 'divider'" class="py-1">
                    <hr class="app-divider" />
                </div>
            </div>
        </div>
    </div>
</template>
