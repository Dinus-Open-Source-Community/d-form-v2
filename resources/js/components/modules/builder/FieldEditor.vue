<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue'
import type { BuilderField, FieldOptionEntry } from '@/types/form-builder'
import { normalizeBannerSrc } from '@/components/modules/builder/formBanner'
import {
    createLocalImagePreviewUrl,
    revokeLocalImagePreviewUrl,
    useBuilderImageUpload,
} from '@/utils/composables/useBuilderImageUpload'
import { Switch } from '@/components/ui/switch'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Button } from '@/components/ui/button'
import {
    Type, AlignLeft, Mail, Phone, Hash, ChevronDown, SquareCheck, CircleDot,
    ImagePlus, Upload, Calendar, Clock, Star,
    Heading as HeadingIcon, TextCursorInput, Minus, Plus, X, Loader2, Image as ImageIcon,
} from 'lucide-vue-next'

const props = withDefaults(
    defineProps<{
        field: BuilderField
        /** Endpoint `POST {form}/images`. Kosong = preview lokal saja (tidak dipersist). */
        uploadUrl?: string
    }>(),
    { uploadUrl: '' },
)

const emit = defineEmits<{
    (event: 'update:field', field: BuilderField): void
}>()

const optionUploadError = ref('')
const imageBlockUploadError = ref('')
const imageBlockUploading = ref(false)
/** Id opsi yang sedang mengunggah (badge per opsi). */
const uploadingOptionIds = ref<Set<string>>(new Set())

const { uploadImage } = useBuilderImageUpload({
    uploadUrl: () => props.uploadUrl,
})

function trackOptionUpload(id: string, active: boolean): void {
    const next = new Set(uploadingOptionIds.value)
    if (active) next.add(id)
    else next.delete(id)
    uploadingOptionIds.value = next
}

function isOptionUploading(id: string): boolean {
    return uploadingOptionIds.value.has(id)
}

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
}

const config = computed(() => TYPE_CONFIG[props.field.type as keyof typeof TYPE_CONFIG] || TYPE_CONFIG.short_text)

// --- helpers to mutate without losing reactivity ---
function update<K extends keyof BuilderField>(key: K, value: BuilderField[K]) {
    emit('update:field', { ...props.field, [key]: value })
}
function updateMeta(key: string, value: unknown) {
    const meta = { ...(props.field.metadata || {}) }
    meta[key] = value
    emit('update:field', { ...props.field, metadata: meta })
}

// --- option list helpers (for checkbox / radio / dropdown) ---
const newOption = ref('')

function optionRows(): FieldOptionEntry[] {
    const raw = props.field.options
    if (!Array.isArray(raw)) return []
    const rows = raw as FieldOptionEntry[]
    // Dropdown is intentionally text-only to avoid unstable image option rendering.
    if (props.field.type === 'dropdown') {
        return rows.map((row) => ({
            ...row,
            type: 'text',
            imageUrl: '',
            label: String(row.label ?? ''),
        }))
    }
    return rows
}

function emitOptions(next: FieldOptionEntry[]) {
    emit('update:field', { ...props.field, options: next })
}

function addOption() {
    const text = newOption.value
    emitOptions([...optionRows(), { id: crypto.randomUUID(), type: 'text', label: text, imageUrl: '' }])
    newOption.value = ''
}

function removeOption(index: number) {
    const opts = [...optionRows()]
    opts.splice(index, 1)
    emitOptions(opts)
}

function toggleOptionType(index: number) {
    if (props.field.type === 'dropdown') return
    const opts = [...optionRows()]
    const cur = opts[index]
    opts[index] = { ...cur, type: cur.type === 'text' ? 'image' : 'text' }
    emitOptions(opts)
}

function setOptionLabel(index: number, label: string) {
    const opts = [...optionRows()]
    opts[index] = { ...opts[index], label: String(label ?? '') }
    emitOptions(opts)
}

function setOptionImageUrl(index: number, url: string) {
    const opts = [...optionRows()]
    opts[index] = { ...opts[index], imageUrl: url.trim() }
    emitOptions(opts)
}

function onOptionImageFile(optionId: string, event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file || !file.type.startsWith('image/')) return
    optionUploadError.value = ''
    void (async () => {
        // Preview instan (object URL) — tidak pernah base64.
        const localUrl = createLocalImagePreviewUrl(file)
        setOptionImageUrlById(optionId, localUrl)
        trackOptionUpload(optionId, true)
        try {
            const result = await uploadImage(file)
            if (result) {
                revokeLocalImagePreviewUrl(localUrl)
                setOptionImageUrlById(optionId, result.url)
            } else if (props.uploadUrl.trim() !== '') {
                setOptionImageUrlById(optionId, '')
                optionUploadError.value = 'Gagal mengunggah gambar opsi. Coba lagi.'
            }
            // Tanpa uploadUrl: pertahankan preview lokal (sanitizer membuangnya sebelum save).
        } finally {
            trackOptionUpload(optionId, false)
        }
    })()
}

function setOptionImageUrlById(optionId: string, url: string) {
    const opts = [...optionRows()]
    const index = opts.findIndex((row) => row.id === optionId)
    if (index === -1) return
    opts[index] = { ...opts[index], imageUrl: url.trim() }
    emitOptions(opts)
}

function optionPreviewSrc(opt: FieldOptionEntry): string {
    return opt.imageUrl ? normalizeBannerSrc(opt.imageUrl) : ''
}

function imageBlockSrc(): string {
    const raw = props.field.metadata?.imageUrl
    return typeof raw === 'string' ? normalizeBannerSrc(raw) : ''
}

async function onImageBlockFile(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file || !file.type.startsWith('image/')) return
    imageBlockUploadError.value = ''
    const previous = typeof props.field.metadata?.imageUrl === 'string' ? props.field.metadata.imageUrl : ''
    // Preview instan (object URL) — tidak pernah base64.
    const localUrl = createLocalImagePreviewUrl(file)
    updateMeta('imageUrl', localUrl)
    imageBlockUploading.value = true
    try {
        const result = await uploadImage(file)
        if (result) {
            revokeLocalImagePreviewUrl(localUrl)
            updateMeta('imageUrl', result.url)
        } else if (props.uploadUrl.trim() !== '') {
            revokeLocalImagePreviewUrl(localUrl)
            updateMeta('imageUrl', previous)
            imageBlockUploadError.value = 'Gagal mengunggah gambar. Coba lagi.'
        }
        // Tanpa uploadUrl: pertahankan preview lokal (sanitizer membuangnya sebelum save).
    } finally {
        imageBlockUploading.value = false
    }
}

function clearImageBlock(): void {
    const raw = props.field.metadata?.imageUrl
    if (typeof raw === 'string') revokeLocalImagePreviewUrl(raw)
    updateMeta('imageUrl', '')
}

onUnmounted(() => {
    const raw = props.field.metadata?.imageUrl
    if (typeof raw === 'string' && raw.startsWith('blob:')) revokeLocalImagePreviewUrl(raw)
    for (const opt of optionRows()) {
        if (typeof opt.imageUrl === 'string' && opt.imageUrl.startsWith('blob:')) {
            revokeLocalImagePreviewUrl(opt.imageUrl)
        }
    }
})

const hasPlaceholder = computed(() =>
    ['short_text', 'long_text', 'email', 'phone', 'number'].includes(props.field.type),
)
const hasOptions = computed(() =>
    ['dropdown', 'checkbox', 'radio'].includes(props.field.type),
)
const isContent = computed(() =>
    ['heading', 'paragraph', 'divider', 'image'].includes(props.field.type),
)
function onMaxLengthInput(v: string) {
    const t = String(v ?? '').trim()
    if (t === '') {
        const meta = { ...(props.field.metadata || {}) }
        delete meta.maxLength
        emit('update:field', { ...props.field, metadata: meta })
        return
    }
    const n = parseInt(t, 10)
    if (!Number.isFinite(n) || n < 1) return
    updateMeta('maxLength', Math.min(n, 100_000))
}

function maxLengthInputDisplay(): string {
    const raw = props.field.metadata?.maxLength
    if (raw == null || raw === '') return ''
    const n = Number(raw)
    return Number.isFinite(n) && n > 0 ? String(Math.floor(n)) : ''
}
const hasAdvancedFlags = computed(
    () => !['heading', 'paragraph', 'divider', 'banner', 'image'].includes(props.field.type),
)
</script>

<template>
    <div class="flex flex-col gap-6 rounded-2xl border border-border/60 bg-card/50 px-5 py-6">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div
                class="grid size-10 shrink-0 place-items-center rounded-xl text-white shadow-sm"
                :style="{ backgroundColor: config.accent }"
            >
                <component :is="config.icon" class="size-4" :stroke-width="2" />
            </div>
            <div>
                <h3 class="font-display text-base font-bold tracking-tight text-foreground">Properti field</h3>
                <p class="text-xs text-muted-foreground">Tipe: {{ config.label }}</p>
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
            <p class="text-xs text-muted-foreground">Judul yang tampil di atas kolom.</p>
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
            <p class="text-xs text-muted-foreground">
                Kosong = tidak ada petunjuk. Teks abu-abu hanya contoh di UI, bukan nilai tersimpan.
            </p>
        </div>

        <!-- Maks. karakter (teks pendek / panjang) -->
        <div v-if="['short_text', 'long_text'].includes(field.type)" class="flex flex-col gap-2">
            <Label class="text-sm font-medium">Maks. karakter</Label>
            <Input
                type="number"
                min="1"
                max="100000"
                step="1"
                :model-value="maxLengthInputDisplay()"
                placeholder="Tanpa batas"
                class="h-11 text-sm"
                @update:model-value="onMaxLengthInput"
            />
            <p class="text-xs text-muted-foreground">Kosongkan jika tidak dibatasi. Pengisi tidak bisa melebihi nilai ini.</p>
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
            <div
                v-if="imageBlockSrc()"
                class="relative overflow-hidden rounded-xl border border-border bg-card"
            >
                <img :src="imageBlockSrc()" :alt="String(field.metadata?.alt ?? '')" class="w-full object-cover" />
                <span
                    v-if="imageBlockUploading"
                    class="bg-foreground/55 text-background absolute inset-x-0 bottom-0 flex items-center justify-center gap-1 py-1 text-[10px] font-semibold"
                >
                    <Loader2 class="size-3 animate-spin" aria-hidden="true" />
                    Mengunggah…
                </span>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <label
                    class="flex cursor-pointer items-center justify-center rounded-lg border border-border bg-card px-3 py-2 text-xs font-semibold text-muted-foreground transition-colors hover:border-primary/30 hover:text-foreground"
                >
                    <ImagePlus class="mr-1.5 size-4" />
                    {{ imageBlockSrc() ? 'Ganti gambar' : 'Unggah gambar' }}
                    <input type="file" accept="image/*" class="sr-only" @change="onImageBlockFile" />
                </label>
                <Button
                    v-if="imageBlockSrc()"
                    variant="outline"
                    size="sm"
                    type="button"
                    class="text-xs"
                    @click="clearImageBlock"
                >
                    Hapus
                </Button>
            </div>
            <Input
                :model-value="String(field.metadata?.imageUrl ?? '')"
                placeholder="atau tempel URL gambar…"
                class="text-[11px]"
                @update:model-value="(v) => updateMeta('imageUrl', String(v ?? '').trim())"
            />
            <p v-if="imageBlockUploadError" class="text-[10px] font-semibold text-destructive">
                {{ imageBlockUploadError }}
            </p>
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
            <p class="text-[10px] text-muted-foreground">Pisahkan dengan koma (tanpa titik di depan).</p>
            <div v-if="field.type === 'image_upload'" class="flex flex-wrap gap-1.5">
                <span class="rounded-full border border-primary/20 bg-primary/8 px-2 py-0.5 text-[10px] font-semibold text-primary">
                    4:3
                </span>
                <span class="rounded-full border border-border bg-background px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">
                    1200 x 900
                </span>
                <span class="rounded-full border border-amber-500/25 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">
                    area tengah aman
                </span>
            </div>
        </div>

        <!-- Options editor (dropdown / checkbox / radio) -->
        <div v-if="hasOptions" class="flex flex-col gap-2">
            <Label class="text-xs font-semibold">Daftar opsi</Label>
            <p class="text-[10px] text-muted-foreground">
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
                    class="rounded-xl border border-border bg-muted/30 p-2.5"
                >
                    <div class="flex items-start gap-2">
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                    Opsi {{ i + 1 }} — {{ opt.type === 'text' ? 'teks' : 'gambar' }}
                                </span>
                                <button
                                    v-if="field.type !== 'dropdown'"
                                    type="button"
                                    @click="toggleOptionType(i)"
                                    class="text-[9px] font-bold text-primary underline-offset-2 hover:underline"
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
                                <div
                                    v-if="optionPreviewSrc(opt)"
                                    class="relative h-20 w-full overflow-hidden rounded-lg border border-border bg-card"
                                >
                                    <img
                                        :src="optionPreviewSrc(opt)"
                                        alt=""
                                        class="size-full object-cover"
                                    />
                                    <span
                                        v-if="isOptionUploading(opt.id)"
                                        class="bg-foreground/55 text-background absolute inset-x-0 bottom-0 flex items-center justify-center gap-1 py-0.5 text-[10px] font-semibold"
                                    >
                                        <Loader2 class="size-3 animate-spin" aria-hidden="true" />
                                        Mengunggah…
                                    </span>
                                </div>
                                <Input
                                    :model-value="opt.imageUrl"
                                    placeholder="URL gambar"
                                    class="text-[11px]"
                                    @update:model-value="(v) => setOptionImageUrl(i, String(v ?? ''))"
                                />
                                <div class="flex flex-wrap gap-1.5">
                                    <span class="rounded-full border border-primary/20 bg-primary/8 px-2 py-0.5 text-[10px] font-semibold text-primary">
                                        1:1
                                    </span>
                                    <span class="rounded-full border border-border bg-background px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">
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
                            <label
                                v-if="opt.type === 'image'"
                                class="flex cursor-pointer items-center justify-center rounded-lg border border-border bg-card px-2 py-1.5 text-[10px] font-semibold text-muted-foreground transition-colors hover:border-primary/30 hover:text-foreground"
                            >
                                <ImagePlus class="mr-1 size-3.5" />
                                Unggah
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="sr-only"
                                    @change="onOptionImageFile(opt.id, $event)"
                                />
                            </label>
                            <button
                                type="button"
                                class="flex size-8 items-center justify-center text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
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
            <p v-if="optionUploadError" class="text-[10px] font-semibold text-destructive">
                {{ optionUploadError }}
            </p>
        </div>

        <!-- Advanced (team / bundle) -->
        <div v-if="hasAdvancedFlags" class="mt-2 space-y-4">
            <hr class="app-divider" />
            <p class="text-sm font-semibold text-foreground">Lanjutan</p>
            <div class="flex items-center justify-between gap-4 rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                <div class="min-w-0">
                    <Label class="text-sm font-medium">Anggota tim bisa ubah (lampiran)</Label>
                    <p class="text-xs text-muted-foreground">Untuk alur tim: undangan bisa mengubah field ini saat konfirmasi.</p>
                </div>
                <Switch
                    :model-value="!!field.is_append"
                    @update:model-value="(v) => update('is_append', Boolean(v))"
                />
            </div>
            <div class="flex items-center justify-between gap-4 rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                <div class="min-w-0">
                    <Label class="text-sm font-medium">Duplikat per peserta (paket / bundle)</Label>
                    <p class="text-xs text-muted-foreground">Ulang field ini untuk setiap anggota tambahan pada pendaftaran paket.</p>
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
            <div class="flex items-center justify-between gap-4 rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                <div>
                    <Label class="text-sm font-medium">Wajib diisi</Label>
                    <p class="text-xs text-muted-foreground">Pengguna harus mengisi field ini</p>
                </div>
                <Switch
                    :model-value="!!field.required"
                    @update:model-value="(v) => update('required', Boolean(v))"
                />
            </div>
        </div>
    </div>
</template>
