<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { SplitDateTimeField } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select';
import { Switch } from '@/components/ui/switch';
import DeferredRender from '@/components/core/DeferredRender.vue';
import { LazyTipTapEditor } from '@/components/core/lazyTipTapEditor';
import type { TFormPurpose, IFormRegistrationMetadata, IFormSiblingOption } from '@/types/form';
import {
    filterFormSubmissionLimitInput,
    hasFormSubmissionLimitFormatError,
    validateFormSubmissionLimitInput,
} from '@/lib/formSubmissionLimitInput';

const closedAt = defineModel<string>('closedAt', { required: true });
const visibleFor = defineModel<string[]>('visibleFor', { required: true });
const formMetadata = defineModel<IFormRegistrationMetadata>('formMetadata', { required: true });
const successContent = defineModel<string>('successContent', { required: true });

/** Teks bermakna = ada isi di luar tag HTML dan `&nbsp;`. */
function hasMeaningfulHtmlText(html: string): boolean {
    return (
        html
            .replace(/<[^>]*>/g, '')
            .replace(/&nbsp;/gi, ' ')
            .trim() !== ''
    );
}

/** Toggle lokal: hidup bila sudah ada pesan tersimpan, dan mati berarti konten dikosongkan. */
const successEnabled = ref<boolean>(hasMeaningfulHtmlText(successContent.value));

function onSuccessToggle(value: boolean): void {
    successEnabled.value = value;

    if (!value) {
        successContent.value = '';
    }
}

const props = withDefaults(
    defineProps<{
        idPrefix: string;
        fieldErrors: Partial<Record<'closed_at' | 'visible_for', string>>;
        visibilityOptions: readonly { value: string; label: string }[];
        siblingForms?: IFormSiblingOption[];
    }>(),
    {
        siblingForms: () => [],
    }
);

defineEmits<{
    toggleVisibility: [value: string, checked: boolean];
}>();

const isRegistrationPurpose = computed(() => formMetadata.value.purpose === 'registration');
const isGuestSubmissionEligible = computed(() => !isRegistrationPurpose.value && !isDKelasPurpose.value);

const isTeamStyleRegistration = computed(() => {
    if (!isRegistrationPurpose.value && formMetadata.value.purpose !== 'd_kelas') {
        return false;
    }
    const m = formMetadata.value.registration_mode;
    return m === 'team' || m === 'bundle';
});

const registrationModeSelectSentinel = '__none__' as const;
const requiresFormSelectSentinel = '__none__' as const;

const purposeOptions: SimpleSelectOption[] = [
    { value: 'registration', label: 'Pendaftaran' },
    { value: 'answer_collection', label: 'Pengumpulan Jawaban' },
    { value: 'feedback', label: 'Feedback' },
    { value: 'd_kelas', label: 'D-Kelas (terkunci)' },
];

/** D-Kelas permanen: tujuan tidak boleh diubah dari pengaturan. */
const isDKelasPurpose = computed<boolean>(() => formMetadata.value.purpose === 'd_kelas');

const requiresFormOptions = computed<SimpleSelectOption[]>(() => [
    { value: requiresFormSelectSentinel, label: 'Tidak ada' },
    ...props.siblingForms.map((sibling) => ({ value: sibling.id, label: sibling.title })),
]);

const registrationModeOptions: SimpleSelectOption[] = [
    { value: registrationModeSelectSentinel, label: 'Not set (individual)' },
    { value: 'single', label: 'Single' },
    { value: 'bundle', label: 'Bundle' },
    { value: 'team', label: 'Team' },
];

function onPurposeChange(value: string): void {
    const allowed: readonly TFormPurpose[] = ['registration', 'answer_collection', 'feedback', 'd_kelas'];
    const purpose = allowed.find((entry) => entry === value);

    if (purpose === undefined) return;

    // Tujuan selain pendaftaran selalu single; server menegakkan aturan yang sama.
    formMetadata.value =
        purpose === 'registration'
            ? { ...formMetadata.value, purpose }
            : { ...formMetadata.value, purpose, registration_mode: 'single', max_team_size: null, team_size: null };
}

function onRequiresFormChange(value: string): void {
    formMetadata.value = {
        ...formMetadata.value,
        requires_form_id: value === requiresFormSelectSentinel || value === '' ? null : value,
    };
}

function onRegistrationModeSelect(value: string): void {
    const mode: IFormRegistrationMetadata['registration_mode'] =
        value === registrationModeSelectSentinel || value === ''
            ? null
            : (value as IFormRegistrationMetadata['registration_mode']);
    const leaveSizes = mode === 'team' || mode === 'bundle';
    formMetadata.value = {
        ...formMetadata.value,
        registration_mode: mode,
        ...(leaveSizes ? {} : { max_team_size: null, team_size: null }),
    };
}

function setMaxTeamSize(v: string | number): void {
    const s = typeof v === 'number' ? String(v) : vString(v);
    const n = s === '' ? null : Number(s);
    formMetadata.value = {
        ...formMetadata.value,
        max_team_size: n === null || Number.isNaN(n) ? null : n,
    };
}

function setTeamSize(v: string | number): void {
    const s = typeof v === 'number' ? String(v) : vString(v);
    const n = s === '' ? null : Number(s);
    formMetadata.value = {
        ...formMetadata.value,
        team_size: n === null || Number.isNaN(n) ? null : n,
    };
}

const maxSubmissionsInput = ref<string>(String(formMetadata.value.max_submissions || ''));
const maxSubmissionsError = ref<string | null>(null);
const hasMalformedMaxSubmissionsInput = ref<boolean>(false);

watch(
    () => formMetadata.value.max_submissions,
    (value: number): void => {
        const nextValue: string = value > 0 ? String(value) : '';
        if (nextValue !== maxSubmissionsInput.value) maxSubmissionsInput.value = nextValue;
    }
);

function setMaxSubmissions(v: string | number): void {
    const rawValue: string = typeof v === 'number' ? String(v) : vString(v);
    const filteredValue: string = filterFormSubmissionLimitInput(rawValue);
    const rawValidation = validateFormSubmissionLimitInput(rawValue);
    hasMalformedMaxSubmissionsInput.value = hasFormSubmissionLimitFormatError(rawValue);
    maxSubmissionsInput.value = filteredValue;
    maxSubmissionsError.value = rawValidation.message;
    if (rawValidation.valid && rawValidation.value !== null) {
        formMetadata.value = { ...formMetadata.value, max_submissions: rawValidation.value };
    }
}

function validateMaxSubmissions(): void {
    const validation = validateFormSubmissionLimitInput(maxSubmissionsInput.value);
    maxSubmissionsError.value = hasMalformedMaxSubmissionsInput.value
        ? 'Gunakan angka bulat tanpa tanda atau desimal.'
        : validation.message;
}

function onMultipleSubmissionsToggle(value: boolean): void {
    formMetadata.value = {
        ...formMetadata.value,
        allow_multiple_submissions: value,
        max_submissions: value
            ? Math.max(2, formMetadata.value.max_submissions || 2)
            : formMetadata.value.max_submissions,
    };
}

function vString(v: unknown): string {
    return v == null ? '' : String(v);
}
</script>

<template>
    <section class="border-border bg-card space-y-5 rounded-2xl border p-6 shadow-xs">
        <SplitDateTimeField
            :id-prefix="`${idPrefix}-closed`"
            v-model="closedAt"
            label="Tanggal tutup"
            required
            layout="col"
            picker-class="bg-card"
            :invalid="!!fieldErrors.closed_at"
            :error="fieldErrors.closed_at"
        />
        <div class="space-y-3">
            <Label class="text-sm font-medium">Visibility <span class="text-destructive">*</span></Label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="opt in visibilityOptions"
                    :key="opt.value"
                    type="button"
                    :aria-pressed="visibleFor.includes(opt.value)"
                    @click="$emit('toggleVisibility', opt.value, !visibleFor.includes(opt.value))"
                    :class="[
                        'border px-4 py-2 text-sm font-medium transition-[border-color,background-color,color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        visibleFor.includes(opt.value)
                            ? 'border-primary/40 bg-primary/10 text-primary'
                            : 'border-border bg-background text-muted-foreground hover:border-primary/25 hover:text-foreground',
                    ]"
                >
                    {{ opt.label }}
                </button>
            </div>
            <p v-if="fieldErrors.visible_for" class="text-destructive text-xs">{{ fieldErrors.visible_for }}</p>
        </div>

        <div class="border-border space-y-4 border-t pt-5">
            <div class="space-y-2">
                <Label :for="`${idPrefix}-purpose`" class="text-sm font-medium">Tujuan form</Label>
                <SimpleSelect
                    :model-value="formMetadata.purpose"
                    :options="purposeOptions"
                    :id="`${idPrefix}-purpose`"
                    class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                    aria-label="Tujuan form"
                    :disabled="isDKelasPurpose"
                    @update:model-value="onPurposeChange"
                />
                <p class="text-muted-foreground text-xs leading-snug">
                    Form pendaftaran memakai kuota & jendela daftar acara. Form lainnya tidak.
                </p>
            </div>

            <div class="space-y-2">
                <Label :for="`${idPrefix}-requires`" class="text-sm font-medium">Memerlukan form</Label>
                <SimpleSelect
                    :model-value="formMetadata.requires_form_id ?? requiresFormSelectSentinel"
                    :options="requiresFormOptions"
                    :id="`${idPrefix}-requires`"
                    class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                    aria-label="Memerlukan form"
                    @update:model-value="onRequiresFormChange"
                />
                <p class="text-muted-foreground text-xs leading-snug">
                    Peserta harus sudah diterima pada form yang dipilih sebelum mengisi form ini.
                </p>
            </div>

            <div v-if="isRegistrationPurpose || isDKelasPurpose" class="space-y-2">
                <Label :for="`${idPrefix}-reg-mode`" class="text-sm font-medium">Registration mode</Label>
                <SimpleSelect
                    :model-value="formMetadata.registration_mode ?? registrationModeSelectSentinel"
                    :options="registrationModeOptions"
                    :id="`${idPrefix}-reg-mode`"
                    class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                    aria-label="Registration mode"
                    @update:model-value="onRegistrationModeSelect"
                />
            </div>

            <div v-if="isGuestSubmissionEligible" class="border-border space-y-3 border-t pt-5">
                <h3 class="text-sm font-semibold">Akses pengisian</h3>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <Label :for="`${idPrefix}-guest-submissions-toggle`" class="text-sm font-medium"
                            >Izinkan pengisian tanpa login</Label
                        >
                        <p
                            :id="`${idPrefix}-guest-submissions-description`"
                            class="text-muted-foreground text-xs leading-snug"
                        >
                            {{
                                formMetadata.allow_guest_submissions
                                    ? 'Siapa pun yang memiliki tautan dapat mengisi form ini tanpa masuk ke akun.'
                                    : 'Peserta harus masuk ke akun sebelum mengisi form ini.'
                            }}
                        </p>
                    </div>
                    <Switch
                        :id="`${idPrefix}-guest-submissions-toggle`"
                        :model-value="formMetadata.allow_guest_submissions"
                        :aria-describedby="`${idPrefix}-guest-submissions-description`"
                        aria-label="Izinkan pengisian tanpa login"
                        @update:model-value="
                            (value) => (formMetadata = { ...formMetadata, allow_guest_submissions: value })
                        "
                    />
                </div>
            </div>

            <div v-if="isGuestSubmissionEligible" class="border-border space-y-3 border-t pt-5">
                <h3 class="text-sm font-semibold">Pengiriman ulang</h3>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <Label :for="`${idPrefix}-multiple-submissions-toggle`" class="text-sm font-medium"
                            >Izinkan peserta mengirim jawaban lebih dari sekali</Label
                        >
                        <p
                            :id="`${idPrefix}-multiple-submissions-description`"
                            class="text-muted-foreground text-xs leading-snug"
                        >
                            {{
                                formMetadata.allow_multiple_submissions
                                    ? 'Peserta dapat mengirim jawaban ulang sampai batas yang ditentukan.'
                                    : 'Setiap peserta hanya dapat mengirim jawaban satu kali.'
                            }}
                        </p>
                    </div>
                    <Switch
                        :id="`${idPrefix}-multiple-submissions-toggle`"
                        :model-value="formMetadata.allow_multiple_submissions"
                        :aria-describedby="`${idPrefix}-multiple-submissions-description`"
                        aria-label="Izinkan peserta mengirim jawaban lebih dari sekali"
                        @update:model-value="onMultipleSubmissionsToggle"
                    />
                </div>
                <div v-if="formMetadata.allow_multiple_submissions" class="space-y-2">
                    <Label :for="`${idPrefix}-max-submissions`" class="text-sm font-medium"
                        >Jumlah pengiriman maksimum</Label
                    >
                    <Input
                        :id="`${idPrefix}-max-submissions`"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]+"
                        autocomplete="off"
                        :model-value="maxSubmissionsInput"
                        :class="[
                            'min-h-12 px-4 !py-3.5 text-sm',
                            maxSubmissionsError ? 'border-destructive focus-visible:ring-destructive' : '',
                        ]"
                        :aria-describedby="`${idPrefix}-max-submissions-help ${idPrefix}-max-submissions-error`"
                        :aria-invalid="maxSubmissionsError !== null"
                        @update:modelValue="setMaxSubmissions"
                        @blur="validateMaxSubmissions"
                    />
                    <p :id="`${idPrefix}-max-submissions-help`" class="text-muted-foreground text-xs leading-snug">
                        Termasuk pengiriman pertama. Contoh: 2 berarti peserta dapat mengirim sekali lagi.
                    </p>
                    <p
                        v-if="maxSubmissionsError"
                        :id="`${idPrefix}-max-submissions-error`"
                        class="text-destructive text-xs"
                    >
                        {{ maxSubmissionsError }}
                    </p>
                </div>
            </div>

            <div class="border-border space-y-3 border-t pt-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <Label :for="`${idPrefix}-success-toggle`" class="text-sm font-medium">
                            Pesan setelah submit
                        </Label>
                        <p class="text-muted-foreground text-xs leading-snug">
                            Tampil ke peserta setelah form terkirim. Bila nonaktif, peserta melihat pesan bawaan.
                        </p>
                    </div>
                    <Switch
                        :id="`${idPrefix}-success-toggle`"
                        :model-value="successEnabled"
                        aria-label="Aktifkan pesan setelah submit"
                        @update:model-value="onSuccessToggle"
                    />
                </div>
                <div v-if="successEnabled" class="border-border bg-background rounded-xl border">
                    <DeferredRender min-height="12rem">
                        <LazyTipTapEditor v-model="successContent" />
                    </DeferredRender>
                </div>
            </div>

            <div v-if="isTeamStyleRegistration" class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label :for="`${idPrefix}-max-team`" class="text-sm font-medium">Max team size</Label>
                    <p class="text-muted-foreground text-xs leading-snug">
                        Maks. anggota per tim (≥2). Dipakai jika
                        <span class="text-foreground/90 font-medium">Team size</span> kosong.
                    </p>
                    <Input
                        :id="`${idPrefix}-max-team`"
                        type="number"
                        min="2"
                        placeholder="—"
                        class="min-h-12 px-4 !py-3.5 text-sm"
                        :model-value="formMetadata.max_team_size == null ? '' : String(formMetadata.max_team_size)"
                        @update:modelValue="setMaxTeamSize"
                    />
                </div>
                <div class="space-y-2">
                    <Label :for="`${idPrefix}-team-size`" class="text-sm font-medium">Team size</Label>
                    <p class="text-muted-foreground text-xs leading-snug">
                        Ukuran tim (≥2). Menggantikan
                        <span class="text-foreground/90 font-medium">Max team size</span> bila diisi.
                    </p>
                    <Input
                        :id="`${idPrefix}-team-size`"
                        type="number"
                        min="2"
                        placeholder="—"
                        class="min-h-12 px-4 !py-3.5 text-sm"
                        :model-value="formMetadata.team_size == null ? '' : String(formMetadata.team_size)"
                        @update:modelValue="setTeamSize"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
