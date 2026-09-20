<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import FormFillLayout from '@/layouts/FormFillLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { AutosaveStatus } from '@/components/ui/autosave-status';
import FormFillFieldSlotRows from '@/components/modules/dashboard/FormFillFieldSlotRows.vue';
import { useFormFillPage } from '@/utils/composables/useFormFillPage';
import { useAutosaveSync, type AutosaveStatus as AutosaveStatusType } from '@/utils/composables/useAutosaveSync';
import { readFieldRules } from '@/lib/formFieldMetadata';
import type { FormFillPageEvent, FormFillPageForm } from '@/types/form';
import { routes } from '@/lib/routes';
import { CircleAlert } from 'lucide-vue-next';

defineOptions({ layout: FormFillLayout });

interface ApplyPageProps {
    period: Record<string, unknown> | null;
    registration: { is_open: boolean; message: string | null };
    divisions: Array<Record<string, unknown>>;
    oprecForm: FormFillPageForm;
    oprecEvent: FormFillPageEvent;
    fields: IFormField[];
    submitUrl: string;
}

const props = defineProps<ApplyPageProps>();

const ctx = useFormFillPage({
    event: props.oprecEvent,
    form: props.oprecForm,
    fields: props.fields,
    submitUrl: props.submitUrl,
    accessStatus: 'allowed',
    accessMessage: '',
    memberSlots: 0,
    registrationMode: 'single',
});

const DRAFT_KEY = 'oprec-apply-draft-v1';
const TOTAL_STEPS = 3;
const STEP_LIST = [1, 2, 3];
const currentStep = ref<number>(1);
const clientErrors = ref<Record<string, string>>({});
const isSubmitting = ref(false);
const savedAt = ref<Date | null>(null);

function stepOf(field: IFormField): number {
    return typeof field.metadata.step === 'number' ? field.metadata.step : 0;
}

function stepFields(step: number): IFormField[] {
    return props.fields.filter((field) => stepOf(field) === step);
}

function isEmptyValue(value: unknown): boolean {
    return value === null || value === undefined || value === '';
}

/** Sembunyikan cabang portfolio yang tak dipilih; belum pilih / none = keduanya opsional tampil. */
function isFieldVisible(field: IFormField): boolean {
    const portfolioType: unknown = ctx.answerForm['portfolio_type']
    if (field.name === 'portfolio_url') return portfolioType !== 'file' && portfolioType !== 'none'
    if (field.name === 'portfolio_file') return portfolioType !== 'url' && portfolioType !== 'none'
    return true
}

function validateStep(step: number): boolean {
    const fieldsInStep = stepFields(step);
    const missing = fieldsInStep
        .filter((field) => Boolean(readFieldRules(field).required))
        .filter((field) => isEmptyValue(ctx.answerForm[field.name]));

    const next: Record<string, string> = { ...clientErrors.value };
    for (const field of fieldsInStep) delete next[field.name];
    for (const field of missing) next[field.name] = `${field.label} wajib diisi.`;
    clientErrors.value = next;

    return missing.length === 0;
}

function clearClientErrorsForStep(step: number): void {
    const next: Record<string, string> = { ...clientErrors.value };
    for (const field of stepFields(step)) delete next[field.name];
    clientErrors.value = next;
}

/** Pesan error server Inertia untuk satu field, dirender oleh FormFillFieldSlotRows di dalam CardContent. */
function serverMessagesFor(field: IFormField): string[] {
    return ctx.cardErrorsForFields([field]);
}

function fieldHasError(field: IFormField): boolean {
    return serverMessagesFor(field).length > 0 || Boolean(clientErrors.value[field.name]);
}

/** Pesan validasi client; dikosongkan bila field sudah punya pesan server agar tidak dobel. */
function clientMessageFor(field: IFormField): string {
    if (serverMessagesFor(field).length > 0) return '';
    return clientErrors.value[field.name] ?? '';
}

function fieldErrorId(field: IFormField): string {
    return `oprec-field-error-${field.name}`;
}

function fieldCardId(field: IFormField): string {
    return `oprec-field-card-${field.name}`;
}

function cardErrorClass(field: IFormField): string {
    if (!fieldHasError(field)) return '';
    return 'border-destructive/40 bg-destructive/5 hover:border-destructive/50';
}

function focusFirstInvalidField(step: number): void {
    const target = stepFields(step).find((field) => fieldHasError(field));
    if (!target) return;
    const control = document.getElementById(target.name);
    if (control instanceof HTMLElement) {
        control.focus();
        return;
    }
    document.getElementById(fieldCardId(target))?.focus();
}

function syncStepToUrl(step: number, replace: boolean): void {
    const url = new URL(window.location.href);
    url.searchParams.set('step', String(step));
    if (replace) window.history.replaceState({}, '', url);
    else window.history.pushState({}, '', url);
}

function goToStep(step: number): void {
    if (step > currentStep.value && !validateStep(currentStep.value)) {
        void nextTick(() => focusFirstInvalidField(currentStep.value));
        return;
    }
    if (step !== currentStep.value) clearClientErrorsForStep(currentStep.value);
    currentStep.value = step;
    syncStepToUrl(step, false);
}

function onPopState(): void {
    const step = Number(new URL(window.location.href).searchParams.get('step') || '1');
    if (step >= 1 && step <= TOTAL_STEPS) {
        currentStep.value = step;
        clientErrors.value = {};
    }
}

function draftSnapshot(): string {
    const values: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(ctx.answerForm.data())) {
        if (value instanceof File) continue;
        values[key] = value;
    }
    return JSON.stringify({ step: currentStep.value, values });
}

const {
    status: draftStatus,
    flush: flushDraft,
    cancel: cancelDraft,
} = useAutosaveSync(draftSnapshot, async () => {}, {
    debounceMs: 800,
    storageKey: DRAFT_KEY,
    storage: {
        read: (key: string): string | null => window.localStorage.getItem(key),
        write: (key: string, value: string): void => window.localStorage.setItem(key, value),
        remove: (key: string): void => window.localStorage.removeItem(key),
    },
    onError: () => {},
});

watch(draftStatus, (value: AutosaveStatusType): void => {
    if (value === 'saved') savedAt.value = new Date();
});

/** Hapus pesan error begitu isian sudah diperbaiki, agar card-nya tidak tetap merah. */
watch(
    (): Record<string, unknown> => {
        const snapshot: Record<string, unknown> = {};
        for (const field of props.fields) snapshot[field.name] = ctx.answerForm[field.name];
        return snapshot;
    },
    (values: Record<string, unknown>): void => {
        for (const field of props.fields) {
            if (!isEmptyValue(values[field.name])) delete clientErrors.value[field.name];
        }
    }
);

const draftStatusText = computed((): string => {
    if (draftStatus.value === 'saving') return 'Menyimpan…';
    if (draftStatus.value === 'saved') {
        const time = savedAt.value?.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) ?? '';
        return `Draft tersimpan otomatis · ${time}`;
    }
    return '';
});

function isDraftShape(value: unknown): value is { step?: unknown; values?: unknown } {
    return typeof value === 'object' && value !== null;
}

function restoreDraft(): void {
    const raw = window.localStorage.getItem(DRAFT_KEY);
    if (!raw) return;
    let parsed: unknown;
    try {
        parsed = JSON.parse(raw);
    } catch {
        return;
    }
    if (!isDraftShape(parsed)) return;
    if (typeof parsed.step === 'number' && parsed.step >= 1 && parsed.step <= TOTAL_STEPS) {
        currentStep.value = parsed.step;
    }
    if (typeof parsed.values === 'object' && parsed.values !== null) {
        for (const [key, value] of Object.entries(parsed.values)) {
            if (typeof value === 'string' && key in ctx.answerForm) {
                ctx.answerForm[key] = value;
            }
        }
    }
}

function firstStepWithErrors(): number {
    for (let step = 1; step <= TOTAL_STEPS; step += 1) {
        if (ctx.cardErrorsForFields(stepFields(step)).length > 0) return step;
    }
    return currentStep.value;
}

async function submitStep(): Promise<void> {
    if (isSubmitting.value || ctx.answerForm.processing) {
        return;
    }

    isSubmitting.value = true;

    for (let step = 1; step <= TOTAL_STEPS; step += 1) {
        if (!validateStep(step)) {
            isSubmitting.value = false;
            currentStep.value = step;
            syncStepToUrl(step, false);
            await nextTick();
            focusFirstInvalidField(step);
            return;
        }
    }

    await flushDraft();
    ctx.answerForm.post(props.submitUrl, {
        forceFormData: true,
        onSuccess: () => window.localStorage.removeItem(DRAFT_KEY),
        onError: () => {
            isSubmitting.value = false;
            const step = firstStepWithErrors();
            currentStep.value = step;
            syncStepToUrl(step, false);
            void nextTick(() => focusFirstInvalidField(step));
        },
    });
}

function openUploadLightbox(src: string | undefined): void {
    if (src) window.open(src, '_blank', 'noopener');
}

const reviewRows = computed((): Array<{ label: string; value: string }> => {
    const get = (name: string): string => {
        const value: unknown = ctx.answerForm[name];
        if (value instanceof File) return value.name;
        return typeof value === 'string' && value !== '' ? value : '—';
    };
    return [
        { label: 'Nama Lengkap', value: get('full_name') },
        { label: 'NIM', value: get('nim') },
        { label: 'Semester', value: get('semester') },
        { label: 'Nomor WhatsApp', value: get('phone') },
        { label: 'Email Pribadi', value: get('personal_email') },
        { label: 'Email Kampus', value: get('student_email') },
        { label: 'Instagram', value: get('instagram_username') },
        { label: 'Divisi Utama', value: get('primary_division_id') },
        { label: 'Divisi Cadangan', value: get('secondary_division_id') },
        { label: 'Bentuk Portfolio', value: get('portfolio_type') },
        { label: 'Link Portfolio', value: get('portfolio_url') },
        { label: 'File Portfolio', value: get('portfolio_file') },
        { label: 'CV', value: get('cv') },
        { label: 'Bukti Follow Instagram', value: get('instagram_follow_proof') },
        { label: 'Link Twibbon', value: get('twibbon_url') },
    ];
});

onMounted(() => {
    const fromUrl = Number(new URL(window.location.href).searchParams.get('step') || '1');
    if (fromUrl >= 1 && fromUrl <= TOTAL_STEPS) currentStep.value = fromUrl;
    restoreDraft();
    syncStepToUrl(currentStep.value, true);
    window.addEventListener('popstate', onPopState);
});

onBeforeUnmount(() => {
    window.removeEventListener('popstate', onPopState);
    cancelDraft();
});

const isBlocked = computed((): boolean => !props.registration.is_open);

const STEPS = [
    { n: 1, label: 'Data diri', hint: 'Nama, NIM, kontak' },
    { n: 2, label: 'Divisi', hint: 'Utama dan cadangan' },
    { n: 3, label: 'Berkas', hint: 'CV, portfolio, bukti IG' },
] as const;

const progressPct = computed((): number => (currentStep.value / TOTAL_STEPS) * 100);

const periodName = computed((): string => {
    const raw: unknown = props.period?.name;
    return typeof raw === 'string' && raw !== '' ? raw : 'Open Recruitment';
});
</script>

<template>
    <Head :title="`Daftar — ${periodName}`" />

    <div class="selection:bg-primary/15 mx-auto max-w-2xl px-2">
        <div class="mb-6 space-y-2 text-center">
            <h1 class="text-2xl font-bold tracking-tight">Formulir Pendaftaran</h1>
            <p v-if="period" class="text-muted-foreground text-sm">{{ periodName }}</p>

            <AutosaveStatus :status="draftStatus" :saved-text="draftStatusText" variant="block" />
        </div>

        <Card v-if="isBlocked" class="border-border/70 rounded-2xl">
            <CardContent class="space-y-4 p-6 text-center">
                <p class="font-medium">Pendaftaran belum tersedia</p>
                <p class="text-muted-foreground text-sm">{{ registration.message }}</p>
                <Button as-child variant="outline">
                    <Link :href="routes.recruitment.track.login">Ke portal tracking</Link>
                </Button>
            </CardContent>
        </Card>

        <template v-else>
            <nav aria-label="Langkah pendaftaran" class="mb-4">
                <ol class="flex items-start gap-1 sm:gap-2">
                    <li v-for="s in STEPS" :key="s.n" class="flex-1">
                        <button
                            type="button"
                            class="focus-visible:ring-ring flex w-full flex-col items-center gap-1 rounded-xl px-1 py-2 focus-visible:ring-2 focus-visible:outline-none"
                            :aria-current="currentStep === s.n ? 'step' : undefined"
                            @click="goToStep(s.n)"
                        >
                            <span
                                class="flex size-7 items-center justify-center rounded-full text-xs font-semibold tabular-nums"
                                :class="
                                    currentStep === s.n
                                        ? 'bg-primary text-primary-foreground'
                                        : s.n < currentStep
                                          ? 'bg-primary/15 text-primary'
                                          : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ s.n }}
                            </span>
                            <span
                                class="text-xs font-medium"
                                :class="currentStep === s.n ? 'text-foreground' : 'text-muted-foreground'"
                            >
                                {{ s.label }}
                            </span>
                            <span class="text-muted-foreground hidden text-[11px] sm:block">{{ s.hint }}</span>
                        </button>
                    </li>
                </ol>
                <div
                    class="bg-muted mt-2 h-1.5 overflow-hidden rounded-full"
                    role="progressbar"
                    aria-label="Progress pendaftaran"
                    :aria-valuenow="currentStep"
                    aria-valuemin="1"
                    :aria-valuemax="TOTAL_STEPS"
                >
                    <div
                        class="bg-primary h-full rounded-full transition-[width]"
                        :style="{ width: `${progressPct}%` }"
                    />
                </div>
            </nav>

            <div
                class="border-border/70 bg-muted/40 mb-4 rounded-xl border border-dashed px-4 py-3 text-xs leading-relaxed"
            >
                <p class="text-foreground font-medium">Draft tersimpan otomatis di browser ini</p>
                <p class="text-muted-foreground mt-0.5">
                    File tidak ikut tersimpan — pilih ulang CV dan file portfolio sebelum submit.
                </p>
            </div>

            <template v-for="step in STEP_LIST" :key="step">
                <div v-show="currentStep === step" class="flex flex-col gap-4">
                    <template v-for="field in stepFields(step)" :key="field.id">
                        <Card
                            v-if="isFieldVisible(field)"
                            :id="fieldCardId(field)"
                            :class="['border-border bg-card rounded-2xl border shadow-sm', cardErrorClass(field)]"
                            :tabindex="fieldHasError(field) ? -1 : undefined"
                            :role="fieldHasError(field) ? 'group' : undefined"
                            :aria-label="fieldHasError(field) ? field.label : undefined"
                            :aria-invalid="fieldHasError(field) ? 'true' : undefined"
                            :aria-describedby="clientMessageFor(field) ? fieldErrorId(field) : undefined"
                        >
                            <FormFillFieldSlotRows
                                :ctx="ctx"
                                :field="field"
                                :participation-slot="{ slotIndex: null, title: '' }"
                                :storage-key="field.name"
                                variant="linear"
                                :image-upload-fill-ready-fn="() => false"
                                @open-lightbox="openUploadLightbox"
                            />
                            <div
                                v-if="clientMessageFor(field)"
                                :id="fieldErrorId(field)"
                                role="alert"
                                class="border-destructive/20 -mt-4 border-t px-6 pt-3 pb-4"
                            >
                                <p class="text-destructive flex items-start gap-1.5 text-xs font-medium">
                                    <CircleAlert class="mt-px size-3.5 shrink-0" aria-hidden="true" />
                                    <span>{{ clientMessageFor(field) }}</span>
                                </p>
                            </div>
                        </Card>
                    </template>
                    <div v-if="step === 3" class="border-border bg-card rounded-2xl border shadow-sm">
                        <div class="border-border border-b px-4 py-3">
                            <h2 class="text-foreground text-sm font-semibold">Periksa kembali data kamu</h2>
                        </div>
                        <dl class="divide-border divide-y px-4">
                            <div
                                v-for="row in reviewRows"
                                :key="row.label"
                                class="flex items-start justify-between gap-4 py-2.5"
                            >
                                <dt class="text-muted-foreground text-xs">{{ row.label }}</dt>
                                <dd class="text-foreground max-w-[60%] truncate text-right text-xs font-medium">
                                    {{ row.value }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <Button v-if="step > 1" type="button" variant="outline" @click="goToStep(step - 1)">
                            Kembali
                        </Button>
                        <span v-else />
                        <Button v-if="step < TOTAL_STEPS" type="button" @click="goToStep(step + 1)"> Lanjut </Button>
                        <Button
                            v-else
                            type="button"
                            :disabled="isSubmitting || ctx.answerForm.processing"
                            @click="submitStep"
                        >
                            {{
                                isSubmitting || ctx.answerForm.processing
                                    ? 'Mengirim…'
                                    : 'Kirim Pendaftaran'
                            }}
                        </Button>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>
