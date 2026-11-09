<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { SplitDateTimeField } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select';
import type { TFormPurpose, IFormRegistrationMetadata, IFormSiblingOption } from '@/types/form';

const closedAt = defineModel<string>('closedAt', { required: true });
const visibleFor = defineModel<string[]>('visibleFor', { required: true });
const formMetadata = defineModel<IFormRegistrationMetadata>('formMetadata', { required: true });

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

const isRegistrationPurpose = computed(() => formMetadata.value.purpose !== 'other');

const isTeamStyleRegistration = computed(() => {
    if (!isRegistrationPurpose.value) {
        return false;
    }
    const m = formMetadata.value.registration_mode;
    return m === 'team' || m === 'bundle';
});

const registrationModeSelectSentinel = '__none__' as const;
const requiresFormSelectSentinel = '__none__' as const;

const purposeOptions: SimpleSelectOption[] = [
    { value: 'registration', label: 'Pendaftaran' },
    { value: 'other', label: 'Lainnya (feedback, survei, …)' },
];

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
    const purpose: TFormPurpose = value === 'other' ? 'other' : 'registration';
    formMetadata.value = {
        ...formMetadata.value,
        purpose,
        ...(purpose === 'other' ? { registration_mode: null, max_team_size: null, team_size: null } : {}),
    };
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

            <div v-if="isRegistrationPurpose" class="space-y-2">
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
