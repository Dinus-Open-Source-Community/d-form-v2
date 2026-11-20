<script setup lang="ts">
import { computed } from 'vue';
import { optionLabel, optionImageUrl } from '@/components/modules/builder/fieldMapping';
import { FIELD_TONE_CHIP_CLASS, resolveFieldTypeMeta } from '@/components/modules/builder/fieldTypePresentation';
import FormParagraphContent from '@/components/core/FormParagraphContent.vue';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import {
    FIELD_INPUT_PATTERNS,
    isFieldInputSubtype,
    type IFieldInputPattern,
    type TFieldInputSubtype,
} from '@/lib/fieldInputPatterns';
import type { IBuilderField, IFieldOptionEntry } from '@/types/form-builder';
import { ChevronDown, Copy, ImagePlus, Info, Trash2, Upload } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        field: IBuilderField;
        isSelected?: boolean;
    }>(),
    { isSelected: false }
);

/**
 * Kartu adalah pratinjau read-only: satu-satunya jalur edit adalah drawer.
 * `open` dipakai pemilik kanvas untuk membuka drawer field ini.
 */
const emit = defineEmits<{
    (event: 'open'): void;
    (event: 'delete'): void;
    (event: 'duplicate'): void;
}>();

const config = computed(() => resolveFieldTypeMeta(props.field.type));

/** Teks aksesibel trigger: label field bila ada, fallback label tipe. */
const triggerLabel = computed(() => `Ubah field ${props.field.label.trim() || config.value.label}`);

/** Petunjuk di kanvas bila admin belum mengisi placeholder: hanya tampilan, bukan nilai tersimpan. */
function canvasPlaceholder(f: IBuilderField): string {
    const custom = String(f.placeholder ?? '').trim();
    if (custom) return custom;
    const byType: Record<string, string> = {
        short_text: 'Ketik jawaban singkat…',
        long_text: 'Tulis jawaban di sini…',
        email: 'nama@email.com',
        phone: 'Nomor WhatsApp / telepon',
        number: 'Masukkan angka',
        nik: 'Masukkan NIK',
        nim: 'Masukkan NIM',
        postal_code: 'Masukkan kode pos',
    };
    return byType[f.type] ?? 'Ketik di sini…';
}

/** Subtype identitas berdigit: pratinjau memakai aturan input yang sama dengan form isian. */
const IDENTITY_FIELD_TYPES: readonly TFieldInputSubtype[] = ['nik', 'nim', 'postal_code'];

/**
 * Batas dan mode keyboard diambil dari `FIELD_INPUT_PATTERNS` (sumber yang sama dengan
 * form isian). Teks petunjuk di bawahnya versi ringkas untuk kartu kanvas.
 */
const IDENTITY_FORMAT_HINTS: Partial<Record<TFieldInputSubtype, string>> = {
    nik: '16 angka',
    nim: '5 sampai 20 angka',
    postal_code: '5 angka',
};

const identitySubtype = computed((): TFieldInputSubtype | null => {
    const type: string = props.field.type;
    if (!isFieldInputSubtype(type)) return null;

    return IDENTITY_FIELD_TYPES.includes(type) ? type : null;
});

const identityPattern = computed((): IFieldInputPattern | null =>
    identitySubtype.value === null ? null : FIELD_INPUT_PATTERNS[identitySubtype.value]
);

const identityHint = computed((): string => {
    const subtype = identitySubtype.value;

    return subtype === null ? '' : (IDENTITY_FORMAT_HINTS[subtype] ?? '');
});

const maxLengthMarker = computed((): number | null => {
    const raw = props.field.metadata?.maxLength;
    const parsed = typeof raw === 'number' ? raw : Number.parseInt(String(raw ?? ''), 10);

    return Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : null;
});

/** Penanda aturan pada kartu: satu baris muted per konsekuensi, bukan badge. */
const ruleMarkers = computed((): string[] => {
    const markers: string[] = [];

    if (maxLengthMarker.value !== null) markers.push(`Maks ${maxLengthMarker.value} karakter`);
    if (props.field.is_append === true) markers.push('Anggota tim bisa mengubah field ini saat konfirmasi');
    if (props.field.metadata?.duplicatable === true) markers.push('Diulang untuk setiap anggota paket');

    return markers;
});

const filledStars = computed<number>(() => {
    const raw = props.field.metadata?.maxStars;
    return typeof raw === 'number' && Number.isFinite(raw) ? raw : 5;
});

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

/** Blok gambar display-only: path storage / URL / pratinjau blob menjadi sumber gambar. */
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
        data-testid="field-card"
        class="group bg-card relative isolate rounded-xl border shadow-sm transition-[border-color,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]"
        :class="
            isSelected
                ? 'border-primary/60 bg-primary/[0.03] ring-primary/20 ring-1'
                : 'border-border hover:border-primary/40'
        "
    >
        <!-- Seluruh permukaan kartu adalah trigger drawer; konten di bawahnya non-interaktif. -->
        <button
            type="button"
            class="focus-visible:ring-ring/40 absolute inset-0 z-10 cursor-pointer rounded-xl outline-none focus-visible:ring-[3px]"
            :aria-label="triggerLabel"
            @click="emit('open')"
        />

        <div class="pointer-events-none">
            <div class="flex items-center gap-2.5 px-4 pt-2 sm:px-5">
                <span
                    class="grid size-7 shrink-0 place-items-center rounded-md"
                    :class="FIELD_TONE_CHIP_CLASS[config.tone]"
                >
                    <component :is="config.icon" class="size-4" :stroke-width="2.25" aria-hidden="true" />
                </span>
                <span
                    class="text-muted-foreground hidden min-w-0 truncate text-[11px] font-semibold [@media(min-width:360px)]:block"
                >
                    {{ config.label }}
                </span>

                <div class="pointer-events-auto relative z-20 -mr-2 ml-auto flex shrink-0 items-center gap-0.5">
                    <button
                        type="button"
                        class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring/40 inline-flex size-11 min-h-[44px] min-w-[44px] shrink-0 cursor-pointer items-center justify-center rounded-lg transition-colors outline-none focus-visible:ring-[3px]"
                        title="Gandakan"
                        aria-label="Gandakan field"
                        @click.stop="emit('duplicate')"
                    >
                        <Copy class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:ring-destructive/40 inline-flex size-11 min-h-[44px] min-w-[44px] shrink-0 cursor-pointer items-center justify-center rounded-lg transition-colors outline-none focus-visible:ring-[3px]"
                        title="Hapus"
                        aria-label="Hapus field"
                        @click.stop="emit('delete')"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </div>

            <div class="space-y-3 px-4 pt-2.5 pb-4 sm:px-5 sm:pb-5">
                <div class="space-y-1">
                    <p
                        class="font-display text-foreground text-[15px] leading-snug font-semibold tracking-tight break-words"
                    >
                        {{ field.label || 'Label pertanyaan'
                        }}<span v-if="field.required" class="text-destructive"> *</span>
                    </p>
                    <p v-if="field.description" class="text-muted-foreground text-xs leading-relaxed break-words">
                        {{ field.description }}
                    </p>
                </div>

                <div class="mt-1">
                    <div
                        v-if="['short_text', 'email', 'phone', 'number'].includes(field.type)"
                        class="bg-muted/50 rounded-lg px-3.5 py-2.5"
                    >
                        <span class="text-muted-foreground text-sm">{{ canvasPlaceholder(field) }}</span>
                    </div>

                    <div v-else-if="identityPattern" class="bg-muted/50 rounded-lg px-3.5 py-2.5">
                        <input
                            type="text"
                            :inputmode="identityPattern.inputMode"
                            :maxlength="identityPattern.maxLength"
                            :placeholder="canvasPlaceholder(field)"
                            readonly
                            tabindex="-1"
                            aria-hidden="true"
                            class="text-muted-foreground placeholder:text-muted-foreground/80 w-full cursor-default bg-transparent text-sm outline-none"
                        />
                        <p class="text-muted-foreground/80 mt-1 text-[11px]">{{ identityHint }}</p>
                    </div>

                    <div v-else-if="field.type === 'long_text'" class="bg-muted/50 rounded-lg px-3.5 py-2.5">
                        <p class="text-muted-foreground text-sm">{{ canvasPlaceholder(field) }}</p>
                        <div class="border-border/60 mt-3 space-y-2 border-t border-dashed pt-3">
                            <div class="bg-muted-foreground/15 h-1.5 w-2/3 rounded-full"></div>
                            <div class="bg-muted-foreground/10 h-1.5 w-1/2 rounded-full"></div>
                        </div>
                    </div>

                    <div v-else-if="field.type === 'dropdown'" class="space-y-2">
                        <div class="bg-muted/50 flex items-center justify-between gap-3 rounded-lg px-3.5 py-2.5">
                            <span class="text-muted-foreground text-sm">{{
                                String(field.placeholder ?? '').trim() || 'Pilih salah satu…'
                            }}</span>
                            <ChevronDown class="text-muted-foreground/70 size-4 shrink-0" aria-hidden="true" />
                        </div>
                        <div class="space-y-1.5">
                            <div
                                v-for="(opt, i) in choiceOptions"
                                :key="opt.id || i"
                                class="bg-background/80 text-foreground/85 flex items-center gap-2.5 rounded-md px-2.5 py-2 text-xs"
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

                    <div v-else-if="field.type === 'checkbox'" class="flex flex-col gap-2">
                        <div
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
                        </div>
                    </div>

                    <div v-else-if="field.type === 'radio'" class="flex flex-col gap-2">
                        <div
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
                        </div>
                    </div>

                    <div
                        v-else-if="field.type === 'image_upload'"
                        class="bg-muted/50 flex flex-col items-center justify-center rounded-lg py-6"
                    >
                        <div
                            class="bg-primary/10 text-primary mb-2 flex size-10 items-center justify-center rounded-full"
                        >
                            <ImagePlus class="size-5" aria-hidden="true" />
                        </div>
                        <p class="text-muted-foreground text-xs font-semibold">Ketuk atau jatuhkan gambar di sini</p>
                        <p class="text-muted-foreground/70 mt-0.5 text-[10px]">PNG, JPG hingga 5 MB</p>
                        <div class="mt-2 flex flex-wrap justify-center gap-1">
                            <span class="bg-primary/10 text-primary rounded-md px-2 py-0.5 text-[10px] font-semibold"
                                >4:3</span
                            >
                            <span
                                class="bg-background text-muted-foreground rounded-md px-2 py-0.5 text-[10px] font-semibold"
                                >tengah</span
                            >
                        </div>
                    </div>

                    <div
                        v-else-if="field.type === 'file_upload'"
                        class="bg-muted/50 flex flex-col items-center justify-center rounded-lg py-6"
                    >
                        <div
                            class="bg-background text-muted-foreground mb-2 flex size-10 items-center justify-center rounded-full"
                        >
                            <Upload class="size-5" aria-hidden="true" />
                        </div>
                        <p class="text-muted-foreground text-xs font-semibold">Unggah file di sini</p>
                        <p class="text-muted-foreground/70 mt-0.5 text-[10px]">PDF, JPG, PNG hingga 5 MB</p>
                    </div>

                    <div v-else-if="field.type === 'date'" class="bg-muted/50 rounded-lg px-3.5 py-2.5">
                        <span class="text-muted-foreground text-sm">{{
                            String(field.placeholder ?? '').trim() || 'Pilih tanggal'
                        }}</span>
                    </div>

                    <div v-else-if="field.type === 'time'" class="bg-muted/50 rounded-lg px-3.5 py-2.5">
                        <span class="text-muted-foreground text-sm">{{
                            String(field.placeholder ?? '').trim() || 'Pilih jam (contoh: 09:30)'
                        }}</span>
                    </div>

                    <div v-else-if="field.type === 'rating'" class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <span
                                v-for="i in filledStars"
                                :key="i"
                                class="inline-flex"
                                :class="i <= filledStars ? 'text-warning' : 'text-muted-foreground/30'"
                            >
                                <svg
                                    width="20"
                                    height="20"
                                    viewBox="0 0 24 24"
                                    :fill="i <= 3 ? 'currentColor' : 'none'"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M12 3.6l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.8l5.9-.9L12 3.6z"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </span>
                        </div>
                        <p class="text-muted-foreground text-[10px]">Pratinjau komponen rating</p>
                    </div>

                    <div v-else-if="field.type === 'heading'">
                        <h3 class="font-display text-foreground text-lg font-bold">
                            {{ field.metadata?.content || 'Judul bagian' }}
                        </h3>
                    </div>

                    <div v-else-if="field.type === 'image'">
                        <div v-if="builderImageSrc(field)" class="border-border/70 overflow-hidden rounded-lg border">
                            <img
                                :src="builderImageSrc(field)"
                                :alt="builderImageAlt(field)"
                                class="w-full object-cover"
                            />
                            <p
                                v-if="builderImageCaption(field)"
                                class="bg-card text-muted-foreground px-3 py-2 text-xs leading-relaxed"
                            >
                                {{ builderImageCaption(field) }}
                            </p>
                        </div>
                        <div v-else class="bg-muted/50 flex flex-col items-center justify-center rounded-lg py-6">
                            <div
                                class="bg-background text-muted-foreground mb-2 flex size-10 items-center justify-center rounded-full"
                            >
                                <ImagePlus class="size-5" aria-hidden="true" />
                            </div>
                            <p class="text-muted-foreground text-xs font-semibold">Belum ada gambar</p>
                            <p class="text-muted-foreground/70 mt-0.5 text-[10px]">Atur gambar lewat properti field</p>
                        </div>
                    </div>

                    <div v-else-if="field.type === 'paragraph'">
                        <FormParagraphContent
                            :content="String(field.metadata?.content ?? '')"
                            fallback="Teks penjelasan untuk pengisi form."
                        />
                    </div>

                    <div v-else-if="field.type === 'divider'" class="py-1">
                        <hr class="app-divider" />
                    </div>
                </div>

                <div v-if="ruleMarkers.length > 0" class="space-y-1">
                    <p
                        v-for="marker in ruleMarkers"
                        :key="marker"
                        class="text-muted-foreground flex items-start gap-1.5 text-[11px] leading-snug"
                    >
                        <Info class="mt-px size-3 shrink-0" aria-hidden="true" />
                        <span class="min-w-0">{{ marker }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
