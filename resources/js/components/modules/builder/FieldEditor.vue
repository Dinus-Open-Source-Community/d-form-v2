<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { IBuilderField, IFieldOptionEntry } from '@/types/form-builder';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import { createUuid } from '@/lib/uuid';
import {
    fieldMaxLengthLimits,
    formatMaxLengthNumber,
    maxLengthHint,
    readMaxLengthState,
    sanitizeMaxLengthInput,
    stripNonDigits,
} from '@/lib/fieldMaxLength';
import BuilderImageUploadCard from '@/components/modules/builder/BuilderImageUploadCard.vue';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
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
    Plus,
    X,
    Image as ImageIcon,
} from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        field: IBuilderField;
        /** Endpoint `POST {form}/images`. Kosong = unggah belum tersedia (mis. demo builder). */
        uploadUrl?: string;
    }>(),
    { uploadUrl: '' }
);

const emit = defineEmits<{
    (event: 'update:field', field: IBuilderField): void;
}>();

const TYPE_CONFIG = {
    short_text: { icon: Type, label: 'Teks pendek', accent: '#2563eb' },
    long_text: { icon: AlignLeft, label: 'Teks panjang', accent: '#4f46e5' },
    email: { icon: Mail, label: 'Email', accent: '#0891b2' },
    phone: { icon: Phone, label: 'Telepon', accent: '#059669' },
    number: { icon: Hash, label: 'Angka', accent: '#d97706' },
    dropdown: { icon: ChevronDown, label: 'Dropdown', accent: '#7c3aed' },
    checkbox: { icon: SquareCheck, label: 'Centang', accent: '#db2777' },
    radio: { icon: CircleDot, label: 'Pilihan tunggal', accent: '#e11d48' },
    image_upload: { icon: ImagePlus, label: 'Unggah gambar', accent: '#0d9488' },
    file_upload: { icon: Upload, label: 'Unggah file', accent: '#475569' },
    date: { icon: Calendar, label: 'Tanggal', accent: '#7c3aed' },
    time: { icon: Clock, label: 'Waktu', accent: '#059669' },
    rating: { icon: Star, label: 'Rating', accent: '#f59e0b' },
    heading: { icon: HeadingIcon, label: 'Judul', accent: '#1a1a2e' },
    image: { icon: ImageIcon, label: 'Gambar (tampilan)', accent: '#0ea5e9' },
    paragraph: { icon: TextCursorInput, label: 'Paragraf', accent: '#6b7280' },
    divider: { icon: Minus, label: 'Garis pemisah', accent: '#9ca3af' },
};

const config = computed(() => TYPE_CONFIG[props.field.type as keyof typeof TYPE_CONFIG] || TYPE_CONFIG.short_text);

// --- helpers to mutate without losing reactivity ---
function update<K extends keyof IBuilderField>(key: K, value: IBuilderField[K]) {
    emit('update:field', { ...props.field, [key]: value });
}
function updateMeta(key: string, value: unknown) {
    const meta = { ...(props.field.metadata || {}) };
    meta[key] = value;
    emit('update:field', { ...props.field, metadata: meta });
}

// --- option list helpers (for checkbox / radio / dropdown) ---
const newOption = ref('');

function optionRows(): IFieldOptionEntry[] {
    const raw = props.field.options;
    if (!Array.isArray(raw)) return [];
    const rows = raw as IFieldOptionEntry[];
    // Dropdown is intentionally text-only to avoid unstable image option rendering.
    if (props.field.type === 'dropdown') {
        return rows.map((row) => ({
            ...row,
            type: 'text',
            imageUrl: '',
            label: String(row.label ?? ''),
        }));
    }
    return rows;
}

function emitOptions(next: IFieldOptionEntry[]) {
    emit('update:field', { ...props.field, options: next });
}

function addOption() {
    const text = newOption.value;
    emitOptions([...optionRows(), { id: createUuid(), type: 'text', label: text, imageUrl: '' }]);
    newOption.value = '';
}

function removeOption(index: number) {
    const opts = [...optionRows()];
    opts.splice(index, 1);
    emitOptions(opts);
}

function toggleOptionType(index: number) {
    if (props.field.type === 'dropdown') return;
    const opts = [...optionRows()];
    const cur = opts[index];
    opts[index] = { ...cur, type: cur.type === 'text' ? 'image' : 'text' };
    emitOptions(opts);
}

function setOptionLabel(index: number, label: string) {
    const opts = [...optionRows()];
    opts[index] = { ...opts[index], label: String(label ?? '') };
    emitOptions(opts);
}

function setOptionImageUrl(index: number, url: string) {
    const opts = [...optionRows()];
    opts[index] = { ...opts[index], imageUrl: url.trim() };
    emitOptions(opts);
}

function setOptionImageUrlById(optionId: string, url: string) {
    const opts = [...optionRows()];
    const index = opts.findIndex((row) => row.id === optionId);
    if (index === -1) return;
    opts[index] = { ...opts[index], imageUrl: url.trim() };
    emitOptions(opts);
}

function optionPreviewSrc(opt: IFieldOptionEntry): string {
    return opt.imageUrl ? normalizePublicStorageUrl(opt.imageUrl) : '';
}

function imageBlockSrc(): string {
    const raw = props.field.metadata?.imageUrl;
    return typeof raw === 'string' ? normalizePublicStorageUrl(raw) : '';
}

function clearImageBlock(): void {
    updateMeta('imageUrl', '');
}

const maxLengthLimits = computed(() => fieldMaxLengthLimits(props.field.type));
const maxLengthText = ref<string>(readMaxLengthState(props.field.metadata?.maxLength, maxLengthLimits.value).text);

/** Nilai tersimpan di luar batas tipe (data lama): ditandai, tidak di-clamp otomatis. */
const maxLengthStoredValue = computed((): number | null => {
    const raw = props.field.metadata?.maxLength;
    const parsed = typeof raw === 'number' ? raw : Number.parseInt(stripNonDigits(String(raw ?? '')), 10);

    return Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : null;
});
const maxLengthOutOfRange = computed(
    (): boolean =>
        maxLengthStoredValue.value !== null &&
        (maxLengthStoredValue.value < maxLengthLimits.value.min ||
            maxLengthStoredValue.value > maxLengthLimits.value.max)
);
const maxLengthHelper = computed(
    (): string => `${maxLengthHint(maxLengthLimits.value)}. Kosongkan jika tidak dibatasi.`
);

/** Sinkronkan teks input dengan metadata (mis. saat field lain dipilih). */
watch(
    () => [props.field.id, props.field.metadata?.maxLength],
    () => {
        maxLengthText.value = readMaxLengthState(props.field.metadata?.maxLength, maxLengthLimits.value).text;
    }
);

/**
 * Input teks (bukan number) agar mobile membuka keypad numerik tanpa spinner.
 * Digit disaring; mengetik melebihi batas berhenti di batas tipe (tidak melompat ke
 * angka lain), kosong menghapus batas. Nilai tersimpan yang di luar batas (data lama)
 * tidak diubah sampai user mengetik.
 */
function onMaxLengthInput(v: string | number) {
    const digits = stripNonDigits(String(v ?? ''));

    if (digits === '') {
        maxLengthText.value = '';

        const meta = { ...(props.field.metadata || {}) };
        delete meta.maxLength;
        emit('update:field', { ...props.field, metadata: meta });
        return;
    }

    const sanitized = sanitizeMaxLengthInput(digits, maxLengthLimits.value);

    if (sanitized === null) return;

    maxLengthText.value = String(sanitized);
    updateMeta('maxLength', sanitized);
}

const hasPlaceholder = computed(() =>
    ['short_text', 'long_text', 'email', 'phone', 'number'].includes(props.field.type)
);
const hasOptions = computed(() => ['dropdown', 'checkbox', 'radio'].includes(props.field.type));
const isContent = computed(() => ['heading', 'paragraph', 'divider', 'image'].includes(props.field.type));
const hasAdvancedFlags = computed(
    () => !['heading', 'paragraph', 'divider', 'banner', 'image'].includes(props.field.type)
);
</script>

<template>
    <div class="border-border/60 bg-card/50 flex flex-col gap-6 rounded-2xl border px-5 py-6">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div
                class="grid size-10 shrink-0 place-items-center rounded-xl text-white shadow-sm"
                :style="{ backgroundColor: config.accent }"
            >
                <component :is="config.icon" class="size-4" :stroke-width="2" />
            </div>
            <div>
                <h3 class="font-display text-foreground text-base font-bold tracking-tight">Properti field</h3>
                <p class="text-muted-foreground text-xs">Tipe: {{ config.label }}</p>
            </div>
        </div>

        <hr class="app-divider" />

        <!-- Label -->
        <div class="flex flex-col gap-2">
            <Label class="text-sm font-medium">Label</Label>
            <Input
                :model-value="field.label"
                placeholder="Misal: Nama lengkap"
                class="h-11 text-sm"
                @update:model-value="(v) => update('label', String(v))"
            />
            <p class="text-muted-foreground text-xs">Judul yang tampil di atas kolom.</p>
        </div>

        <!-- Description -->
        <div v-if="!isContent" class="flex flex-col gap-2">
            <Label class="text-sm font-medium">Teks bantu</Label>
            <Textarea
                :model-value="field.description"
                placeholder="Opsional — penjelasan singkat untuk pengisi"
                rows="2"
                class="min-h-[4.5rem] text-sm"
                @update:model-value="(v) => update('description', String(v))"
            />
        </div>

        <!-- Petunjuk di dalam kolom (hanya teks input) -->
        <div v-if="hasPlaceholder" class="flex flex-col gap-2">
            <Label class="text-sm font-medium">Teks petunjuk (placeholder)</Label>
            <Input
                :model-value="field.placeholder ?? ''"
                placeholder="Contoh di kolom pengisi"
                class="h-11 text-sm"
                @update:model-value="(v) => update('placeholder', String(v ?? ''))"
            />
            <p class="text-muted-foreground text-xs">
                Kosong = tidak ada petunjuk. Teks abu-abu hanya contoh di UI, bukan nilai tersimpan.
            </p>
        </div>

        <!-- Maks. karakter (teks pendek / panjang) -->
        <div v-if="['short_text', 'long_text'].includes(field.type)" class="flex flex-col gap-2">
            <Label class="text-sm font-medium">Maks. karakter</Label>
            <Input
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                autocomplete="off"
                :model-value="maxLengthText"
                placeholder="Tanpa batas"
                class="h-11 text-sm"
                :aria-invalid="maxLengthOutOfRange"
                :aria-describedby="maxLengthOutOfRange ? 'f-max-length-error' : undefined"
                @update:model-value="onMaxLengthInput"
            />
            <p class="text-muted-foreground text-xs">{{ maxLengthHelper }}</p>
            <p v-if="maxLengthOutOfRange" id="f-max-length-error" class="text-destructive text-xs">
                Nilai tersimpan ({{ maxLengthStoredValue === null ? '' : formatMaxLengthNumber(maxLengthStoredValue) }})
                di luar batas. Isi ulang maksimal {{ formatMaxLengthNumber(maxLengthLimits.max) }} karakter.
            </p>
        </div>

        <!-- Content text (heading / paragraph) -->
        <div v-if="field.type === 'heading'" class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Isi judul</Label>
            <Input
                :model-value="String(field.metadata?.content ?? '')"
                placeholder="Judul bagian"
                class="text-xs"
                @update:model-value="(v) => updateMeta('content', String(v))"
            />
        </div>
        <div v-if="field.type === 'paragraph'" class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Isi paragraf</Label>
            <Textarea
                :model-value="String(field.metadata?.content ?? '')"
                placeholder="Teks penjelasan…"
                rows="3"
                class="text-xs"
                @update:model-value="(v) => updateMeta('content', String(v))"
            />
        </div>

        <!-- Image block (display-only): gambar + caption + alt -->
        <div v-if="field.type === 'image'" class="flex flex-col gap-2">
            <Label class="text-xs font-semibold">Gambar tampilan</Label>
            <BuilderImageUploadCard
                :key="field.id"
                :upload-url="uploadUrl"
                :preview-url="imageBlockSrc()"
                :alt="String(field.metadata?.alt ?? '')"
                idle-label="Unggah gambar"
                @uploaded="(url) => updateMeta('imageUrl', url)"
                @cleared="clearImageBlock"
            />
            <Input
                :model-value="String(field.metadata?.imageUrl ?? '')"
                placeholder="atau tempel URL gambar…"
                class="text-[11px]"
                @update:model-value="(v) => updateMeta('imageUrl', String(v ?? '').trim())"
            />
            <div class="flex flex-col gap-1.5">
                <Label class="text-xs font-semibold">Caption (opsional)</Label>
                <Input
                    :model-value="String(field.metadata?.caption ?? '')"
                    placeholder="Keterangan di bawah gambar…"
                    class="text-xs"
                    @update:model-value="(v) => updateMeta('caption', String(v ?? ''))"
                />
            </div>
            <div class="flex flex-col gap-1.5">
                <Label class="text-xs font-semibold">Alt text (aksesibilitas)</Label>
                <Input
                    :model-value="String(field.metadata?.alt ?? '')"
                    placeholder="Deskripsi singkat gambar…"
                    class="text-xs"
                    @update:model-value="(v) => updateMeta('alt', String(v ?? ''))"
                />
            </div>
        </div>

        <!-- Rating max stars -->
        <div v-if="field.type === 'rating'" class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Jumlah bintang maksimal</Label>
            <Input
                type="number"
                :model-value="Number(field.metadata?.maxStars ?? 5)"
                min="1"
                max="10"
                class="text-xs"
                @update:model-value="(v) => updateMeta('maxStars', Number(v))"
            />
        </div>

        <!-- File types (image / file / banner) -->
        <div v-if="['image_upload', 'file_upload'].includes(field.type)" class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Ekstensi file yang diizinkan</Label>
            <Input
                :model-value="String(field.metadata?.accepts ?? '')"
                :placeholder="field.type === 'banner' ? 'gif, png, jpg' : 'pdf, jpg, png'"
                class="text-xs"
                @update:model-value="(v) => updateMeta('accepts', String(v))"
            />
            <p class="text-muted-foreground text-[10px]">Pisahkan dengan koma (tanpa titik di depan).</p>
            <div v-if="field.type === 'image_upload'" class="flex flex-wrap gap-1.5">
                <span
                    class="border-primary/20 bg-primary/8 text-primary rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                >
                    4:3
                </span>
                <span
                    class="border-border bg-background text-muted-foreground rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                >
                    1200 x 900
                </span>
                <span
                    class="rounded-full border border-amber-500/25 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300"
                >
                    area tengah aman
                </span>
            </div>
        </div>

        <!-- Options editor (dropdown / checkbox / radio) -->
        <div v-if="hasOptions" class="flex flex-col gap-2">
            <Label class="text-xs font-semibold">Daftar opsi</Label>
            <p class="text-muted-foreground text-[10px]">
                {{
                    field.type === 'dropdown'
                        ? 'Satu baris satu pilihan teks untuk dropdown.'
                        : 'Tiap opsi bisa teks saja atau gambar saja (pakai tombol ganti).'
                }}
            </p>
            <div class="flex flex-col gap-2">
                <div
                    v-for="(opt, i) in optionRows()"
                    :key="opt.id || i"
                    class="border-border bg-muted/30 rounded-xl border p-2.5"
                >
                    <div class="flex items-start gap-2">
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground text-[10px] font-bold tracking-wider uppercase">
                                    Opsi {{ i + 1 }} — {{ opt.type === 'text' ? 'teks' : 'gambar' }}
                                </span>
                                <button
                                    v-if="field.type !== 'dropdown'"
                                    type="button"
                                    @click="toggleOptionType(i)"
                                    class="text-primary text-[9px] font-bold underline-offset-2 hover:underline"
                                >
                                    {{ opt.type === 'text' ? 'Pakai gambar' : 'Pakai teks' }}
                                </button>
                            </div>

                            <div v-if="opt.type === 'text'">
                                <Input
                                    :model-value="opt.label"
                                    placeholder="Teks opsi"
                                    class="text-xs"
                                    @update:model-value="(v) => setOptionLabel(i, String(v ?? ''))"
                                />
                            </div>
                            <div v-else class="flex flex-col gap-1.5">
                                <BuilderImageUploadCard
                                    :upload-url="uploadUrl"
                                    :preview-url="optionPreviewSrc(opt)"
                                    :alt="opt.label"
                                    idle-label="Unggah gambar"
                                    @uploaded="(url) => setOptionImageUrlById(opt.id, url)"
                                    @cleared="setOptionImageUrlById(opt.id, '')"
                                />
                                <Input
                                    :model-value="opt.imageUrl"
                                    placeholder="URL gambar"
                                    class="text-[11px]"
                                    @update:model-value="(v) => setOptionImageUrl(i, String(v ?? ''))"
                                />
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        class="border-primary/20 bg-primary/8 text-primary rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                    >
                                        1:1
                                    </span>
                                    <span
                                        class="border-border bg-background text-muted-foreground rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                    >
                                        800 x 800
                                    </span>
                                </div>
                                <div class="hidden">
                                    <!-- Keep label synced for accessibility or fallback -->
                                    <Input
                                        :model-value="opt.label"
                                        placeholder="Label aksesibilitas"
                                        @update:model-value="(v) => setOptionLabel(i, String(v ?? ''))"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-1">
                            <button
                                type="button"
                                class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive flex size-8 items-center justify-center transition-colors"
                                title="Hapus opsi"
                                @click="removeOption(i)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-1 flex gap-2">
                <Button variant="outline" size="sm" class="w-full text-xs font-bold" type="button" @click="addOption">
                    <Plus class="mr-1 size-3.5" />Tambah opsi
                </Button>
            </div>
        </div>

        <!-- Advanced (team / bundle) -->
        <div v-if="hasAdvancedFlags" class="mt-2 space-y-4">
            <hr class="app-divider" />
            <p class="text-foreground text-sm font-semibold">Lanjutan</p>
            <div
                class="border-border/60 bg-muted/20 flex items-center justify-between gap-4 rounded-xl border px-4 py-3"
            >
                <div class="min-w-0">
                    <Label class="text-sm font-medium">Anggota tim bisa ubah (lampiran)</Label>
                    <p class="text-muted-foreground text-xs">
                        Untuk alur tim: undangan bisa mengubah field ini saat konfirmasi.
                    </p>
                </div>
                <Switch :model-value="!!field.is_append" @update:model-value="(v) => update('is_append', Boolean(v))" />
            </div>
            <div
                class="border-border/60 bg-muted/20 flex items-center justify-between gap-4 rounded-xl border px-4 py-3"
            >
                <div class="min-w-0">
                    <Label class="text-sm font-medium">Duplikat per peserta (paket / bundle)</Label>
                    <p class="text-muted-foreground text-xs">
                        Ulang field ini untuk setiap anggota tambahan pada pendaftaran paket.
                    </p>
                </div>
                <Switch
                    :model-value="field.metadata?.duplicatable === true"
                    @update:model-value="(v) => updateMeta('duplicatable', Boolean(v))"
                />
            </div>
        </div>

        <!-- Required toggle (not for content types) -->
        <div v-if="!isContent" class="mt-2">
            <hr class="app-divider mb-5" />
            <div
                class="border-border/60 bg-muted/20 flex items-center justify-between gap-4 rounded-xl border px-4 py-3"
            >
                <div>
                    <Label class="text-sm font-medium">Wajib diisi</Label>
                    <p class="text-muted-foreground text-xs">Pengguna harus mengisi field ini</p>
                </div>
                <Switch :model-value="!!field.required" @update:model-value="(v) => update('required', Boolean(v))" />
            </div>
        </div>
    </div>
</template>
