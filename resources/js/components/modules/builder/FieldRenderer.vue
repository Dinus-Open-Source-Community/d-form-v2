<script setup lang="ts">
import { computed } from 'vue';
import { optionLabel, optionImageUrl } from '@/components/modules/builder/fieldMapping';
import FormParagraphContent from '@/components/core/FormParagraphContent.vue';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import type { IBuilderField, IFieldOptionEntry } from '@/types/form-builder';
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
    Settings2,
    Trash2,
    Copy,
    GripVertical,
} from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        field: IBuilderField;
        isSelected?: boolean;
    }>(),
    { isSelected: false }
);

const emit = defineEmits<{
    (event: 'select'): void;
    (event: 'delete'): void;
    (event: 'duplicate'): void;
    (event: 'updateField', field: IBuilderField): void;
    (event: 'manage'): void;
}>();

/** Gabung perubahan parsial ke salinan field — kartu selalu kirim objek baru (immutable). */
function patch(partial: Partial<IBuilderField>): void {
    emit('updateField', { ...props.field, ...partial });
}

const hasOptions = computed(() => ['dropdown', 'checkbox', 'radio'].includes(props.field.type));

/** TTone aksen yang tersedia untuk ikon kartu field. */
type TFieldTone = 'neutral' | 'info' | 'primary' | 'success' | 'warning';

const TYPE_CONFIG: Record<string, { icon: typeof Type; label: string; tone: TFieldTone }> = {
    short_text: { icon: Type, label: 'Teks pendek', tone: 'neutral' },
    long_text: { icon: AlignLeft, label: 'Teks panjang', tone: 'neutral' },
    email: { icon: Mail, label: 'Email', tone: 'info' },
    phone: { icon: Phone, label: 'Telepon', tone: 'info' },
    number: { icon: Hash, label: 'Angka', tone: 'info' },
    dropdown: { icon: ChevronDown, label: 'Dropdown', tone: 'primary' },
    checkbox: { icon: SquareCheck, label: 'Centang', tone: 'primary' },
    radio: { icon: CircleDot, label: 'Pilihan tunggal', tone: 'primary' },
    image_upload: { icon: ImagePlus, label: 'Gambar', tone: 'success' },
    file_upload: { icon: Upload, label: 'File', tone: 'success' },
    date: { icon: Calendar, label: 'Tanggal', tone: 'info' },
    time: { icon: Clock, label: 'Waktu', tone: 'info' },
    rating: { icon: Star, label: 'Rating', tone: 'warning' },
    heading: { icon: HeadingIcon, label: 'Judul', tone: 'neutral' },
    image: { icon: ImagePlus, label: 'Gambar', tone: 'neutral' },
    paragraph: { icon: TextCursorInput, label: 'Paragraf', tone: 'neutral' },
    divider: { icon: Minus, label: 'Pemisah', tone: 'neutral' },
};

/** Aksen kecil per kategori field — berbasis tone token proyek, bukan warna acak. */
const FIELD_TONE_CLASSES: Record<TFieldTone, string> = {
    neutral: 'text-muted-foreground',
    info: 'text-info',
    primary: 'text-primary',
    success: 'text-success',
    warning: 'text-warning',
};

const config = computed(() => TYPE_CONFIG[props.field.type] ?? TYPE_CONFIG.short_text);

/** Petunjuk di kanvas bila admin belum mengisi placeholder — hanya tampilan, bukan nilai tersimpan. */
function canvasPlaceholder(f: IBuilderField): string {
    const custom = String(f.placeholder ?? '').trim();
    if (custom) return custom;
    const byType: Record<string, string> = {
        short_text: 'Ketik jawaban singkat…',
        long_text: 'Tulis jawaban di sini…',
        email: 'nama@email.com',
        phone: 'Nomor WhatsApp / telepon',
        number: 'Masukkan angka',
    };
    return byType[f.type] ?? 'Ketik di sini…';
}

const filledStars = computed(() => props.field.metadata?.maxStars ?? 5);

const choiceOptions = computed((): IFieldOptionEntry[] => {
    const raw = props.field.options;
    if (Array.isArray(raw) && raw.length > 0) return raw as IFieldOptionEntry[];
    return ['Option 1', 'Option 2', 'Option 3'].map((label) => ({
        id: label.toLowerCase().replace(/\s+/g, '-'),
        type: 'text',
        label,
    }));
});

function choiceImageSrc(entry: IFieldOptionEntry): string | undefined {
    const u = optionImageUrl(entry);
    return u ? normalizePublicStorageUrl(u) : undefined;
}

/** Display-only image block: storage path / URL / blob preview → <img> src. */
function builderImageSrc(f: IBuilderField): string {
    const raw = f.metadata?.imageUrl;
    return typeof raw === 'string' ? normalizePublicStorageUrl(raw) : '';
}

function builderImageCaption(f: IBuilderField): string {
    const raw = f.metadata?.caption;
    return typeof raw === 'string' ? raw.trim() : '';
}

function builderImageAlt(f: IBuilderField): string {
    const raw = f.metadata?.alt;
    return typeof raw === 'string' && raw.trim() !== '' ? raw.trim() : builderImageCaption(f) || f.label;
}
</script>

<template>
    <div
        class="group relative cursor-pointer rounded-2xl border transition-[border-color,background-color,box-shadow] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
        :class="[
            isSelected
                ? 'border-primary bg-primary/[0.04] ring-primary/20 shadow-md ring-2'
                : 'border-border bg-card hover:border-primary/35 shadow-sm hover:shadow-md',
        ]"
        @click="emit('select')"
    >
        <!-- Type badge + actions bar -->
        <div class="group/head flex items-center gap-2.5 px-4 pt-3.5 sm:px-5">
            <GripVertical
                class="text-muted-foreground/40 group-hover:text-muted-foreground/80 hidden size-3.5 cursor-grab transition-colors lg:block"
                aria-hidden="true"
            />
            <span
                class="inline-flex items-center gap-2 rounded-lg border px-1.5 py-1.5"
                :class="
                    isSelected
                        ? 'border-border/80 bg-background shadow-xs'
                        : 'border-border/80 bg-background/60 group-hover:border-border shadow-xs'
                "
            >
                <component
                    :is="config.icon"
                    class="size-4"
                    :stroke-width="2.25"
                    :class="FIELD_TONE_CLASSES[config.tone]"
                    aria-hidden="true"
                />
            </span>
            <span
                class="text-muted-foreground min-w-0 truncate text-[11px] font-semibold tracking-wide"
                :class="isSelected ? '' : 'text-muted-foreground/85'"
            >
                {{ config.label }}
            </span>
            <div class="ml-auto flex shrink-0 items-center gap-0.5">
                <!-- Aksi langsung (pola sama dengan baris Forms di card event) -->
                <div
                    class="flex shrink-0 items-center gap-0.5 opacity-60 transition-opacity group-hover:opacity-100 focus-within:opacity-100"
                >
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-primary focus-visible:text-primary focus-visible:ring-ring/30 inline-flex size-7 items-center justify-center rounded-md transition-colors outline-none hover:bg-transparent focus-visible:ring-[3px]"
                        :title="hasOptions ? 'Kelola opsi' : 'Pengaturan'"
                        :aria-label="hasOptions ? 'Kelola opsi' : 'Pengaturan field'"
                        @click.stop="emit('manage')"
                    >
                        <Settings2 class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-primary focus-visible:text-primary focus-visible:ring-ring/30 inline-flex size-7 items-center justify-center rounded-md transition-colors outline-none hover:bg-transparent focus-visible:ring-[3px]"
                        title="Gandakan"
                        aria-label="Gandakan field"
                        @click.stop="emit('duplicate')"
                    >
                        <Copy class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-destructive focus-visible:text-destructive focus-visible:ring-ring/30 inline-flex size-7 items-center justify-center rounded-md transition-colors outline-none hover:bg-transparent focus-visible:ring-[3px]"
                        title="Hapus"
                        aria-label="Hapus field"
                        @click.stop="emit('delete')"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Field content -->
        <div class="space-y-3 px-4 pt-3 pb-4 sm:px-5 sm:pb-5">
            <!-- Label -->
            <label class="group/label flex items-center gap-0.5">
                <input
                    :value="field.label"
                    :placeholder="field.required ? 'Label pertanyaan' : 'Label pertanyaan (opsional)'"
                    class="font-display text-foreground placeholder:text-muted-foreground/50 focus:border-primary/60 group-hover/label:border-border w-full border-0 border-b border-transparent bg-transparent p-0 text-[15px] font-semibold tracking-tight transition-colors duration-200 outline-none"
                    @input="patch({ label: ($event.target as HTMLInputElement).value })"
                />
                <span v-if="field.required" class="text-destructive">*</span>
            </label>
            <input
                :value="field.description ?? ''"
                :placeholder="field.description ? '' : 'Teks bantu (opsional)'"
                class="text-muted-foreground placeholder:text-muted-foreground/45 hover:border-border/70 focus:border-primary/50 w-full border-0 border-b border-transparent bg-transparent p-0 text-xs leading-relaxed transition-colors duration-200 outline-none"
                @input="patch({ description: ($event.target as HTMLInputElement).value })"
            />

            <!-- Preview by type -->
            <div class="mt-1">
                <!-- Short text / Email / Phone / Number -->
                <div
                    v-if="['short_text', 'email', 'phone', 'number'].includes(field.type)"
                    class="border-border/70 bg-muted/20 rounded-xl border p-1.5"
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
                        class="border-border/80 bg-background text-foreground placeholder:text-muted-foreground/70 pointer-events-none h-11 w-full rounded-full border px-4 text-sm shadow-xs"
                    />
                </div>

                <!-- Long text -->
                <div
                    v-else-if="field.type === 'long_text'"
                    class="border-border/70 bg-muted/20 rounded-xl border p-1.5"
                >
                    <textarea
                        readonly
                        tabindex="-1"
                        rows="3"
                        :placeholder="canvasPlaceholder(field)"
                        class="border-border/80 bg-background text-foreground placeholder:text-muted-foreground/70 pointer-events-none min-h-[4.75rem] w-full resize-none rounded-xl border px-3 py-2.5 text-sm"
                    ></textarea>
                    <div class="border-border/70 mt-4 space-y-2 border-t border-dashed pt-3">
                        <div class="bg-muted/70 h-2 w-2/3 rounded-full"></div>
                        <div class="bg-muted/50 h-2 w-1/2 rounded-full"></div>
                    </div>
                </div>

                <!-- Dropdown -->
                <div v-else-if="field.type === 'dropdown'" class="space-y-2">
                    <div
                        class="border-border/70 bg-muted/20 flex items-center justify-between rounded-xl border px-4 py-3"
                    >
                        <span class="text-muted-foreground/75 text-sm">{{
                            String(field.placeholder ?? '').trim() || 'Pilih salah satu…'
                        }}</span>
                        <ChevronDown class="text-muted-foreground/50 size-4" />
                    </div>
                    <div class="border-border/70 bg-card space-y-1.5 rounded-xl border p-2.5">
                        <div
                            v-for="(opt, i) in choiceOptions"
                            :key="opt.id || i"
                            class="border-border/60 bg-muted/20 text-foreground/85 flex items-center gap-2.5 rounded-lg border px-2.5 py-2 text-xs"
                        >
                            <div
                                v-if="opt.type === 'image' && choiceImageSrc(opt)"
                                class="border-border size-10 shrink-0 overflow-hidden rounded-md border"
                            >
                                <img :src="choiceImageSrc(opt)" alt="" class="size-full object-cover" />
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
                        class="text-foreground/80 flex items-center gap-2.5 text-xs"
                    >
                        <div
                            class="border-input bg-card flex size-4 shrink-0 items-center justify-center rounded border"
                        ></div>
                        <div
                            v-if="opt.type === 'image' && choiceImageSrc(opt)"
                            class="border-border size-12 shrink-0 overflow-hidden rounded-md border"
                        >
                            <img :src="choiceImageSrc(opt)" alt="" class="size-full object-cover" />
                        </div>
                        <span v-else>{{ optionLabel(opt) }}</span>
                    </label>
                </div>

                <!-- Radio -->
                <div v-else-if="field.type === 'radio'" class="flex flex-col gap-2">
                    <label
                        v-for="(opt, i) in choiceOptions"
                        :key="opt.id || i"
                        class="text-foreground/80 flex items-center gap-2.5 text-xs"
                    >
                        <div
                            class="border-input bg-card flex size-4 shrink-0 items-center justify-center rounded-full border"
                        ></div>
                        <div
                            v-if="opt.type === 'image' && choiceImageSrc(opt)"
                            class="border-border size-12 shrink-0 overflow-hidden rounded-full border"
                        >
                            <img :src="choiceImageSrc(opt)" alt="" class="size-full object-cover" />
                        </div>
                        <span v-else>{{ optionLabel(opt) }}</span>
                    </label>
                </div>

                <!-- Image upload -->
                <div
                    v-else-if="field.type === 'image_upload'"
                    class="border-border/80 bg-muted/20 flex flex-col items-center justify-center rounded-xl border border-dashed py-6"
                >
                    <div class="bg-primary/8 text-primary mb-2 flex size-10 items-center justify-center rounded-full">
                        <ImagePlus class="size-5" />
                    </div>
                    <p class="text-muted-foreground text-xs font-semibold">Ketuk atau jatuhkan gambar di sini</p>
                    <p class="text-muted-foreground/60 mt-0.5 text-[10px]">PNG, JPG hingga 5 MB</p>
                    <div class="mt-2 flex flex-wrap justify-center gap-1">
                        <span class="bg-primary/10 text-primary rounded-full px-2 py-0.5 text-[10px] font-semibold"
                            >4:3</span
                        >
                        <span class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-[10px] font-semibold"
                            >tengah</span
                        >
                    </div>
                </div>

                <!-- File upload -->
                <div
                    v-else-if="field.type === 'file_upload'"
                    class="border-border/80 bg-muted/20 flex flex-col items-center justify-center rounded-xl border border-dashed py-6"
                >
                    <div
                        class="bg-muted/50 text-muted-foreground mb-2 flex size-10 items-center justify-center rounded-full"
                    >
                        <Upload class="size-5" />
                    </div>
                    <p class="text-muted-foreground text-xs font-semibold">Unggah file di sini</p>
                    <p class="text-muted-foreground/60 mt-0.5 text-[10px]">PDF, JPG, PNG hingga 5 MB</p>
                </div>

                <!-- Date -->
                <div v-else-if="field.type === 'date'" class="border-border/70 bg-muted/20 rounded-xl border p-1.5">
                    <input
                        type="text"
                        readonly
                        tabindex="-1"
                        value=""
                        :placeholder="String(field.placeholder ?? '').trim() || 'Pilih tanggal'"
                        class="border-border/80 bg-background text-foreground placeholder:text-muted-foreground/70 pointer-events-none h-11 w-full rounded-full border px-4 text-sm"
                    />
                </div>

                <!-- Time -->
                <div v-else-if="field.type === 'time'" class="border-border/70 bg-muted/20 rounded-xl border p-1.5">
                    <input
                        type="text"
                        readonly
                        tabindex="-1"
                        value=""
                        :placeholder="String(field.placeholder ?? '').trim() || 'Pilih jam (contoh: 09:30)'"
                        class="border-border/80 bg-background text-foreground placeholder:text-muted-foreground/70 pointer-events-none h-11 w-full rounded-full border px-4 text-sm"
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
                    <p class="text-muted-foreground/80 text-[10px]">Tap bintang untuk nilai</p>
                </div>

                <!-- Heading -->
                <div v-else-if="field.type === 'heading'">
                    <h3 class="font-display text-foreground text-lg font-bold">
                        {{ field.metadata?.content || 'Judul bagian' }}
                    </h3>
                </div>

                <!-- Image (display-only) -->
                <div v-else-if="field.type === 'image'">
                    <div v-if="builderImageSrc(field)" class="border-border/70 overflow-hidden rounded-xl border">
                        <img :src="builderImageSrc(field)" :alt="builderImageAlt(field)" class="w-full object-cover" />
                        <p
                            v-if="builderImageCaption(field)"
                            class="border-border/70 bg-card text-muted-foreground border-t px-3 py-2 text-xs leading-relaxed"
                        >
                            {{ builderImageCaption(field) }}
                        </p>
                    </div>
                    <div
                        v-else
                        class="border-border/80 bg-muted/20 flex flex-col items-center justify-center rounded-xl border border-dashed py-6"
                    >
                        <div
                            class="bg-muted/50 text-muted-foreground mb-2 flex size-10 items-center justify-center rounded-full"
                        >
                            <ImagePlus class="size-5" />
                        </div>
                        <p class="text-muted-foreground text-xs font-semibold">Belum ada gambar</p>
                        <p class="text-muted-foreground/60 mt-0.5 text-[10px]">Atur gambar lewat properti field</p>
                    </div>
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
