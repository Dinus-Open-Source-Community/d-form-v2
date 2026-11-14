<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DeferredRender from '@/components/core/DeferredRender.vue';
import FileUploadCard, { type TFileUploadState } from '@/components/core/FileUploadCard.vue';
import { LazyTipTapEditor } from '@/components/core/lazyTipTapEditor';
import EventMultiValuePicker from '@/components/modules/dashboard/events/EventMultiValuePicker.vue';
import EventScheduleFields from '@/components/modules/dashboard/events/EventScheduleFields.vue';
import { FileText, MapPin, MapPinned, PanelsTopLeft, Save, Send, Ticket, Tags } from 'lucide-vue-next';
import {
    store as storeEvent,
    update as updateEvent,
} from '@/actions/App/Http/Controllers/Dashboard/Events/EventController';
import { getFieldError } from '@/lib/error-message';
import { cn } from '@/lib/utils';
import { validateUploadFile } from '@/lib/uploadValidation';
import { uploadPercent } from '@/lib/fileDisplay';
import { createObjectUrl, revokeObjectUrl } from '@/adapters/browser/objectUrl';
import {
    formatIntegerId,
    formatPriceId,
    formatPriceTyping,
    parsePriceInput,
    parseQuotaInput,
    sanitizeQuotaTyping,
} from '@/lib/indonesianNumericInput';

export type TEventDashboardFormVariant = 'create' | 'edit';

const props = defineProps<{
    variant: TEventDashboardFormVariant;
    /** Edit: wajib. Create: tidak dipakai. */
    event?: IEvent;
    /** Create: opsional (fallback default). Edit: wajib dari halaman. */
    options?: { categories: { value: string; label: string }[]; sessions: { value: string; label: string }[] };
    /** Mode wizard (Create → forms): POST via Inertia (X-Inertia) → BE render ulang
     *  Create 200 dgn draftEvent; halaman induk mendeteksi & pindah step. */
    wizardMode?: boolean;
}>();

const { showSuccessToast } = useToastNotifications();

const defaultSessions = [
    { value: 'general', label: 'General' },
    { value: 'programming', label: 'Programming' },
    { value: 'network', label: 'Networking' },
    { value: 'media_creative', label: 'Media Creative' },
    { value: 'data', label: 'Data' },
];

const defaultCategories = [
    { value: 'rkt', label: 'RKT' },
    { value: 'non-rkt', label: 'NON RKT' },
    { value: 'recruitment', label: 'Recruitment' },
    { value: 'etc', label: 'Etc' },
];

const sessions = computed(() =>
    props.variant === 'edit' && props.options ? props.options.sessions : (props.options?.sessions ?? defaultSessions)
);

const categories = computed(() =>
    props.variant === 'edit' && props.options
        ? props.options.categories
        : (props.options?.categories ?? defaultCategories)
);

function toTokenList(v: unknown): string[] {
    if (Array.isArray(v)) return v.map((s) => String(s).trim()).filter(Boolean);
    if (typeof v === 'string')
        return v
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean);
    return [];
}

interface EventFormPayload {
    _method?: 'PUT';
    title: string;
    description: string;
    location: string;
    start_date: string;
    end_date: string;
    registration_start: string;
    registration_end: string;
    quota: number;
    price: number;
    session: string;
    category: string;
    banner: File | null;
    publish: boolean;
}

function buildFormPayload(): EventFormPayload {
    if (props.variant === 'create') {
        return {
            title: '',
            description: '',
            location: '',
            start_date: '',
            end_date: '',
            registration_start: '',
            registration_end: '',
            quota: 100,
            price: 0,
            session: '',
            category: '',
            banner: null,
            publish: false,
        };
    }

    const e = props.event;
    if (!e) {
        throw new Error('EventDashboardForm varian edit membutuhkan prop event.');
    }
    const initialCategories = toTokenList(e.category);
    const initialSessions = toTokenList(e.session);

    return {
        _method: 'PUT' as const,
        title: e.title,
        description: e.description,
        location: e.location,
        start_date: e.start_date,
        end_date: e.end_date,
        registration_start: e.registration_start.replace(/\+.*$/, '').slice(0, 16),
        registration_end: e.registration_end.replace(/\+.*$/, '').slice(0, 16),
        quota: e.quota,
        price: e.price,
        session: initialSessions.join(','),
        category: initialCategories.join(','),
        banner: null,
        publish: e.status === 'published',
    };
}

const form = useForm(buildFormPayload());

/** Batas & format banner di frontend; backend tetap memvalidasi. */
const BANNER_MAX_SIZE_KB = 5120;
const BANNER_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'] as const;
const classificationDescription = computed(() =>
    props.variant === 'create'
        ? 'Sesi (divisi) dan kategori dipakai untuk filter di dashboard. Bisa lebih dari satu.'
        : 'Sesi dan kategori untuk filter internal. Kombinasikan beberapa nilai bila perlu.'
);

/** Batas karakter frontend (counter) — backend tetap memvalidasi. */
const TITLE_MAX = 200;
const DESCRIPTION_MAX = 5000;

const titleLength = computed(() => form.title.length);

/** Hitung panjang teks deskripsi (strip tag HTML dari TipTap). */
const descriptionLength = computed(() => {
    const html = String(form.description ?? '');
    const plain = html
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .trim();
    return plain.length;
});

function onTitleInput(v: string | number): void {
    const s = String(v).slice(0, TITLE_MAX);
    form.title = s;
}

const sessionPickerId = computed(() => (props.variant === 'create' ? 'field-session' : 'edit-field-session'));

const categoryPickerId = computed(() => (props.variant === 'create' ? 'field-category' : 'edit-field-category'));

/**
 * Field yang wajib diisi, selaras dengan StoreEventRequest.
 * Kuota & harga opsional. Banner wajib hanya saat create (saat edit banner lama dipakai).
 */
const REQUIRED_FIELD_NAMES = [
    'title',
    'description',
    'location',
    'start_date',
    'end_date',
    'registration_start',
    'registration_end',
    'session',
    'category',
    'banner',
] as const satisfies readonly (keyof EventFormPayload)[];

type RequiredFieldName = (typeof REQUIRED_FIELD_NAMES)[number];

const requiredFieldNameSet: ReadonlySet<string> = new Set(REQUIRED_FIELD_NAMES);

/** Penanda wajib (*) softcoded: cukup edit REQUIRED_FIELD_NAMES, tanpa menyentuh template. */
function isRequired(fieldName: string): boolean {
    if (!requiredFieldNameSet.has(fieldName)) return false;
    if (fieldName === 'banner') return props.variant === 'create';
    return true;
}

/** Sesi & kategori: perilaku input sama (multi, daftar + ketik). */
const multiValueFieldHint = 'Pilih dari daftar atau ketik; boleh lebih dari satu.';

const primaryActionLabel = computed(() => {
    if (props.wizardMode && props.variant === 'create') return 'Simpan & lanjutkan';
    return props.variant === 'create' ? 'Terbitkan' : 'Simpan & terbitkan';
});

const secondaryActionLabel = computed(() => {
    if (props.wizardMode && props.variant === 'create') return 'Simpan draf';
    return props.variant === 'create' ? 'Simpan draf' : 'Simpan perubahan';
});

const pageTitle = computed(() => (props.variant === 'create' ? 'Buat event baru' : 'Edit event'));

const pageSubtitle = computed(() =>
    props.variant === 'create'
        ? 'Lengkapi informasi event kamu — judul, jadwal, dan detail pendaftaran.'
        : 'Perbarui detail event kamu — simpan perubahan atau terbitkan kembali.'
);

/** Banner yang sudah tersimpan: URL server dari props (diperbarui setelah submit sukses). */
const serverBannerUrl = computed<string | null>(() =>
    props.variant === 'edit' ? (props.event?.banner_url ?? null) : null
);

/** Object URL pratinjau untuk banner yang baru dipilih (belum diunggah). */
const bannerObjectUrl = ref<string | null>(null);
/** Error pilih berkas (format/ukuran) — ditampilkan oleh kartu unggah. */
const bannerPickError = ref('');
/** Persentase unggah nyata dari kunjungan Inertia; null = tidak berjalan. */
const bannerProgress = ref<number | null>(null);

const bannerCardState = computed<TFileUploadState>(() => {
    if (bannerPickError.value !== '') return 'error';
    return form.banner !== null ? 'completed' : 'idle';
});

const bannerIdleLabel = computed<string>(() => (serverBannerUrl.value !== null ? 'Ganti banner' : 'Unggah banner'));

const quotaDisplay = ref(form.quota > 0 ? formatIntegerId(form.quota) : '');

const priceDisplay = ref(Number(form.price) > 0 ? formatPriceId(Number(form.price)) : '');

/** Field yang sedang bergoyang (shake) karena error validasi, per input field. */
const shakingFields = ref(new Set<string>());
const shakeTimers = new Map<string, ReturnType<typeof setTimeout>>();

function shakeFields(keys: string[]): void {
    if (keys.length === 0) return;
    const next = new Set(shakingFields.value);
    for (const key of keys) {
        const previousTimer = shakeTimers.get(key);
        if (previousTimer !== undefined) clearTimeout(previousTimer);
        next.add(key);
        shakeTimers.set(
            key,
            setTimeout(() => {
                const after = new Set(shakingFields.value);
                after.delete(key);
                shakingFields.value = after;
                shakeTimers.delete(key);
            }, 450)
        );
    }
    shakingFields.value = next;
}

function isFieldShaking(key: string): boolean {
    return shakingFields.value.has(key);
}

/** Class error untuk input sederhana (Input, DatePicker, TimeAmPmInput, Combobox). */
function errorClass(key: string): string {
    return fieldError(key)
        ? 'border-destructive/70 bg-red-50 dark:bg-red-500/10 focus-visible:border-destructive focus-visible:ring-destructive/20'
        : '';
}

function onQuotaInput(v: string | number): void {
    const s = sanitizeQuotaTyping(String(v));
    quotaDisplay.value = s;
    form.quota = parseQuotaInput(s);
}

function onQuotaBlur(): void {
    const q = parseQuotaInput(quotaDisplay.value);
    form.quota = q;
    quotaDisplay.value = q > 0 ? formatIntegerId(q) : '';
}

function onPriceInput(v: string | number): void {
    priceDisplay.value = formatPriceTyping(String(v));
    form.price = parsePriceInput(priceDisplay.value);
}

function onPriceBlur(): void {
    const p = parsePriceInput(priceDisplay.value);
    form.price = p;
    priceDisplay.value = p > 0 ? formatPriceId(p) : '';
}

function commitQuotaPriceFromFields(): void {
    form.quota = parseQuotaInput(quotaDisplay.value);
    form.price = parsePriceInput(priceDisplay.value);
}

/**
 * Pilih banner: validasi format/ukuran lalu simpan sebagai berkas tertunda.
 * Berkas ikut payload form saat submit (`forceFormData`); tidak ada request terpisah.
 */
async function onBannerPick(file: File): Promise<void> {
    bannerPickError.value = '';
    const validationError = await validateUploadFile(file, BANNER_EXTENSIONS);

    if (validationError !== null) {
        bannerPickError.value = validationError;
        return;
    }

    revokeObjectUrl(bannerObjectUrl.value);
    bannerObjectUrl.value = createObjectUrl(file);
    form.banner = file;
}

function removeBanner(): void {
    revokeObjectUrl(bannerObjectUrl.value);
    bannerObjectUrl.value = null;
    bannerPickError.value = '';
    form.banner = null;
}

onUnmounted(() => {
    revokeObjectUrl(bannerObjectUrl.value);
});

function submitForm(publish: boolean): void {
    form.publish = publish;
    commitQuotaPriceFromFields();
    if (typeof form.start_date === 'string') form.start_date = form.start_date.trim();
    if (typeof form.end_date === 'string') form.end_date = form.end_date.trim();
    if (typeof form.registration_start === 'string') form.registration_start = form.registration_start.trim();
    if (typeof form.registration_end === 'string') form.registration_end = form.registration_end.trim();
    form.transform((data) => ({
        ...data,
        category: toTokenList(data.category),
        session: toTokenList(data.session),
    }));

    const url = props.variant === 'create' ? storeEvent().url : props.event ? updateEvent(props.event.id).url : null;
    if (url === null) return;

    const isWizard = props.wizardMode === true && props.variant === 'create';

    form.post(url, {
        forceFormData: true,
        // Progres byte nyata dari kunjungan Inertia (multipart FormData).
        onStart: () => {
            bannerProgress.value = 0;
        },
        onProgress: (progress) => {
            if (progress === undefined) return;
            bannerProgress.value = uploadPercent(progress.loaded, progress.total ?? 0);
        },
        onFinish: () => {
            bannerProgress.value = null;
        },
        onSuccess: () => {
            if (isWizard) {
                // Backend merender ulang Create (200, draftEvent prop) → halaman
                // otomatis pindah ke step forms via watcher di Create.vue.
                return;
            }
            if (publish) {
                showSuccessToast(
                    props.variant === 'create'
                        ? 'Acara berhasil dipublikasikan.'
                        : 'Acara diperbarui dan dipublikasikan.'
                );
            } else {
                showSuccessToast(props.variant === 'create' ? 'Disimpan sebagai draf.' : 'Perubahan disimpan.');
            }
        },
        onError: (errors) => {
            shakeFields(Object.keys(errors));
        },
    });
}

function fieldError(key: string): string | undefined {
    return getFieldError(form.errors, key);
}

function validateRequired(): boolean {
    const missing: RequiredFieldName[] = [];
    for (const key of REQUIRED_FIELD_NAMES) {
        if (key === 'banner') {
            if (props.variant === 'create' && !form.banner) missing.push(key);
            continue;
        }
        if (String(form[key] ?? '').trim() === '') missing.push(key);
    }
    if (missing.length) {
        shakeFields(missing);
        for (const k of missing) {
            if (!form.errors[k]) {
                form.setError(k, 'Wajib diisi.');
            }
        }
        return false;
    }
    return true;
}

defineExpose({ submitForm, validateRequired, form });
</script>

<style scoped>
/* Area editor TipTap ber-latar kartu; ketika error, tint merah sampai ke area ketik. */
.event-form-description-error {
    box-shadow: 0 0 0 1px var(--destructive);
}
.event-form-description-error :deep(.dform-rich-text) {
    background-color: color-mix(in srgb, var(--destructive) 5%, white);
}
[data-theme='dark'] .event-form-description-error :deep(.dform-rich-text) {
    background-color: color-mix(in srgb, var(--destructive) 12%, transparent);
}
</style>

<template>
    <div class="flex flex-col gap-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div :class="['flex min-w-0 items-center gap-4', props.wizardMode ? 'min-w-0 flex-1' : '']">
                <slot name="header-leading" />
                <div v-if="!props.wizardMode" class="min-w-0">
                    <h1 class="font-display text-foreground text-2xl font-semibold tracking-tight sm:text-3xl">
                        {{ pageTitle }}
                    </h1>
                    <p class="text-muted-foreground mt-1.5 text-base">{{ pageSubtitle }}</p>
                </div>
            </div>
            <div v-if="!props.wizardMode" class="flex shrink-0 flex-wrap items-center gap-2 sm:gap-3">
                <Button type="button" variant="outline" :disabled="form.processing" @click="submitForm(false)">
                    <Save class="size-4 shrink-0 stroke-[1.75]" aria-hidden="true" />
                    {{ secondaryActionLabel }}
                </Button>
                <Button
                    type="button"
                    :disabled="form.processing"
                    @click="submitForm(props.wizardMode && props.variant === 'create' ? false : true)"
                >
                    <Send class="size-4 shrink-0 stroke-[1.75]" aria-hidden="true" />
                    {{ primaryActionLabel }}
                </Button>
            </div>
        </div>

        <div class="mb-5 grid gap-5 lg:grid-cols-12 lg:items-stretch">
            <div class="flex flex-col gap-6 lg:col-span-7">
                <div class="border-border/60 bg-card flex h-full flex-col rounded-xl border p-5 shadow-xs sm:p-6">
                    <div class="mb-5 flex items-center gap-2.5">
                        <FileText class="text-foreground/80 size-5 shrink-0 stroke-[1.75]" aria-hidden="true" />
                        <div>
                            <p class="text-foreground text-base font-semibold tracking-tight">Informasi utama</p>
                            <p class="text-muted-foreground mt-0.5 text-xs">
                                Judul, deskripsi, banner, dan lokasi yang terlihat peserta.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-6">
                        <div class="flex flex-col gap-5">
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between gap-3">
                                    <Label for="title" class="text-sm font-medium">
                                        Judul acara
                                        <span v-if="isRequired('title')" class="text-destructive">*</span>
                                    </Label>
                                    <span
                                        class="text-muted-foreground text-xs tabular-nums"
                                        :class="titleLength > TITLE_MAX ? 'text-destructive font-medium' : ''"
                                    >
                                        {{ titleLength }}/{{ TITLE_MAX }}
                                    </span>
                                </div>
                                <Input
                                    id="title"
                                    :model-value="form.title"
                                    placeholder="Contoh: Bootcamp Web 2026"
                                    :aria-invalid="!!fieldError('title')"
                                    :class="[
                                        'bg-white',
                                        errorClass('title'),
                                        isFieldShaking('title') ? 'animate-shake' : '',
                                    ]"
                                    @update:model-value="onTitleInput"
                                />
                                <p v-if="fieldError('title')" class="text-destructive text-xs">
                                    {{ fieldError('title') }}
                                </p>
                            </div>

                            <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between gap-3">
                                    <Label class="text-sm font-medium">
                                        Deskripsi event
                                        <span v-if="isRequired('description')" class="text-destructive">*</span>
                                    </Label>
                                </div>
                                <div
                                    :class="[
                                        fieldError('description') ? 'event-form-description-error rounded-xl' : '',
                                        isFieldShaking('description') ? 'animate-shake' : '',
                                    ]"
                                >
                                    <DeferredRender min-height="14rem">
                                        <LazyTipTapEditor v-model="form.description" />
                                    </DeferredRender>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <p v-if="fieldError('description')" class="text-destructive text-xs">
                                        {{ fieldError('description') }}
                                    </p>
                                    <p v-else class="text-xs" aria-hidden="true"></p>
                                    <span
                                        class="text-muted-foreground text-xs tabular-nums"
                                        :class="
                                            descriptionLength > DESCRIPTION_MAX ? 'text-destructive font-medium' : ''
                                        "
                                    >
                                        {{ descriptionLength }}/{{ DESCRIPTION_MAX }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="border-border/60 border-t" />

                        <div class="flex flex-col gap-4">
                            <div>
                                <Label class="flex items-center gap-1.5 text-sm font-medium">
                                    <PanelsTopLeft
                                        class="text-foreground/80 size-4.5 shrink-0 stroke-[1.75]"
                                        aria-hidden="true"
                                    />
                                    Banner / poster
                                    <span v-if="isRequired('banner')" class="text-destructive">*</span>
                                </Label>
                                <p class="text-muted-foreground mt-1 text-xs">
                                    Rasio 16:7 — PNG, JPG, atau WebP maks. 5MB.
                                </p>
                            </div>

                            <div
                                v-if="serverBannerUrl !== null && form.banner === null"
                                class="border-border bg-muted/25 overflow-hidden rounded-xl border-2"
                            >
                                <img
                                    :src="serverBannerUrl"
                                    alt="Banner saat ini"
                                    class="aspect-[16/7] w-full object-cover"
                                />
                            </div>

                            <div :class="isFieldShaking('banner') ? 'animate-shake' : ''">
                                <FileUploadCard
                                    :state="bannerCardState"
                                    :idle-label="bannerIdleLabel"
                                    accept="image/*"
                                    hint="PNG, JPG, atau WebP — rasio 16:7."
                                    :max-size-kb="BANNER_MAX_SIZE_KB"
                                    :file-name="form.banner?.name ?? ''"
                                    :file-size="form.banner?.size ?? 0"
                                    :mime="form.banner?.type ?? ''"
                                    :preview-url="bannerObjectUrl ?? ''"
                                    :error-message="bannerPickError"
                                    :disabled="form.processing"
                                    @pick="onBannerPick"
                                    @remove="removeBanner"
                                />

                                <!-- Progress comes from the Inertia visit; cancellation is unavailable. -->
                                <div
                                    v-if="form.banner !== null && bannerProgress !== null"
                                    class="mt-2"
                                    role="progressbar"
                                    :aria-valuenow="bannerProgress"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-label="Progres unggah banner event"
                                >
                                    <p class="text-muted-foreground text-xs" aria-live="polite">
                                        Mengunggah banner… {{ bannerProgress }}%
                                    </p>
                                    <div class="bg-muted mt-1 h-2 w-full overflow-hidden rounded-full">
                                        <div
                                            class="bg-primary h-full rounded-full transition-[width] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none"
                                            :style="{ width: `${bannerProgress}%` }"
                                        />
                                    </div>
                                </div>
                            </div>

                            <p v-if="fieldError('banner')" class="text-destructive text-xs">
                                {{ fieldError('banner') }}
                            </p>
                        </div>

                        <div class="border-border/60 border-t" />

                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-2.5">
                                <MapPinned
                                    class="text-foreground/80 size-5 shrink-0 stroke-[1.75]"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p class="text-foreground text-sm font-semibold tracking-tight">
                                        Lokasi event
                                        <span v-if="isRequired('location')" class="text-destructive">*</span>
                                    </p>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Tempat acara berlangsung — online atau fisik.
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <div class="relative">
                                    <MapPin
                                        class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 stroke-[1.75]"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="location"
                                        v-model="form.location"
                                        placeholder="Mis. Online — Zoom, atau Semarang — Auditorium A"
                                        :aria-invalid="!!fieldError('location')"
                                        :class="[
                                            'bg-white pl-9',
                                            errorClass('location'),
                                            isFieldShaking('location') ? 'animate-shake' : '',
                                        ]"
                                    />
                                </div>
                                <p v-if="fieldError('location')" class="text-destructive text-xs">
                                    {{ fieldError('location') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-8 lg:col-span-5">
                <EventScheduleFields
                    v-model:start-date="form.start_date"
                    v-model:end-date="form.end_date"
                    v-model:registration-start="form.registration_start"
                    v-model:registration-end="form.registration_end"
                    :start-date-error="fieldError('start_date')"
                    :end-date-error="fieldError('end_date')"
                    :registration-start-error="fieldError('registration_start')"
                    :registration-end-error="fieldError('registration_end')"
                    :start-date-shaking="isFieldShaking('start_date')"
                    :end-date-shaking="isFieldShaking('end_date')"
                    :registration-start-shaking="isFieldShaking('registration_start')"
                    :registration-end-shaking="isFieldShaking('registration_end')"
                    :start-date-required="isRequired('start_date')"
                    :end-date-required="isRequired('end_date')"
                    :start-date-class="
                        cn('bg-white', errorClass('start_date'), isFieldShaking('start_date') && 'animate-shake')
                    "
                    :end-date-class="
                        cn('bg-white', errorClass('end_date'), isFieldShaking('end_date') && 'animate-shake')
                    "
                    :registration-start-required="isRequired('registration_start')"
                    :registration-end-required="isRequired('registration_end')"
                />

                <div class="border-border/60 bg-card rounded-xl border p-5 shadow-xs sm:p-6">
                    <div class="mb-5 flex items-center gap-2.5">
                        <Ticket class="text-foreground/80 size-5 shrink-0 stroke-[1.75]" aria-hidden="true" />
                        <div>
                            <p class="text-foreground text-base font-semibold tracking-tight">Kapasitas dan Harga</p>
                            <p class="text-muted-foreground mt-0.5 text-xs">Batas peserta dan biaya pendaftaran.</p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-5">
                        <div class="flex flex-col gap-2">
                            <Label for="quota" class="text-foreground text-sm font-medium"
                                >Kuota <span v-if="isRequired('quota')" class="text-destructive">*</span></Label
                            >
                            <div class="relative">
                                <Input
                                    id="quota"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    :aria-invalid="!!fieldError('quota')"
                                    :class="[
                                        'h-10 bg-white pr-14 text-sm tabular-nums',
                                        errorClass('quota'),
                                        isFieldShaking('quota') ? 'animate-shake' : '',
                                    ]"
                                    :model-value="quotaDisplay"
                                    placeholder="contoh: 500"
                                    @update:model-value="onQuotaInput"
                                    @blur="onQuotaBlur"
                                />
                                <span
                                    class="text-muted-foreground pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-sm"
                                >
                                    orang
                                </span>
                            </div>
                            <p v-if="fieldError('quota')" class="text-destructive text-xs">
                                {{ fieldError('quota') }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-2">
                            <Label for="price" class="text-foreground text-sm font-medium"
                                >Harga (Rp) <span v-if="isRequired('price')" class="text-destructive">*</span></Label
                            >
                            <div class="relative">
                                <Input
                                    id="price"
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    :aria-invalid="!!fieldError('price')"
                                    :class="[
                                        'h-10 bg-white pl-9 text-sm tabular-nums',
                                        errorClass('price'),
                                        isFieldShaking('price') ? 'animate-shake' : '',
                                    ]"
                                    :model-value="priceDisplay"
                                    placeholder="0"
                                    @update:model-value="onPriceInput"
                                    @blur="onPriceBlur"
                                />
                                <span
                                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-sm"
                                >
                                    Rp
                                </span>
                            </div>
                            <p v-if="fieldError('price')" class="text-destructive text-xs">
                                {{ fieldError('price') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="border-border/60 bg-card rounded-xl border p-5 shadow-xs sm:p-6">
                    <div class="mb-5 flex items-center gap-2.5">
                        <Tags class="text-foreground/80 size-5 shrink-0 stroke-[1.75]" aria-hidden="true" />
                        <div>
                            <p class="text-foreground text-base font-semibold tracking-tight">Klasifikasi</p>
                            <p class="text-muted-foreground mt-0.5 text-xs">{{ classificationDescription }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-5">
                        <EventMultiValuePicker
                            :id="sessionPickerId"
                            v-model="form.session"
                            :options="sessions"
                            label="Sesi / divisi"
                            :required="isRequired('session')"
                            :description="multiValueFieldHint"
                            :error="fieldError('session')"
                            :shaking="isFieldShaking('session')"
                        />
                        <EventMultiValuePicker
                            :id="categoryPickerId"
                            v-model="form.category"
                            :options="categories"
                            label="Kategori"
                            :required="isRequired('category')"
                            :description="multiValueFieldHint"
                            :error="fieldError('category')"
                            :shaking="isFieldShaking('category')"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
