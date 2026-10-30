<script setup lang="ts">
import { computed } from 'vue';
import DraggableItem from '@/components/modules/builder/DraggableItem.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SplitDateTimeField } from '@/components/ui/date-picker';
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select';
import { ChevronRight, ChevronDown, Search, Settings2 } from 'lucide-vue-next';
import type { FormBuilderPaletteCategory } from '@/components/modules/builder/formBuilderPalette';
import type { FormRegistrationMetadata, FormSiblingOption } from '@/types/form';

const searchQuery = defineModel<string>('searchQuery', { required: true });
const closedAt = defineModel<string>('closedAt', { required: true });
const visibleFor = defineModel<string[]>('visibleFor', { required: true });
const formMetadata = defineModel<FormRegistrationMetadata>('formMetadata', { required: true });

const props = withDefaults(
    defineProps<{
        categories: FormBuilderPaletteCategory[];
        openCategoryName: string | null;
        formSettingsOpen: boolean;
        showFormSettings: boolean;
        fieldErrors: Partial<Record<'closed_at' | 'visible_for', string>>;
        visibilityOptions: readonly { value: string; label: string }[];
        siblingForms?: FormSiblingOption[];
    }>(),
    {
        siblingForms: () => [],
    }
);

defineEmits<{
    toggleCategory: [cat: FormBuilderPaletteCategory];
    toggleFormSettings: [];
    toggleVisibility: [value: string, checked: boolean];
}>();

/** Kunci select "Memerlukan form" / "Registration mode" saat memilih opsi kosong. */
const noSelectionSentinel = '__none__' as const;

const purposeOptions: SimpleSelectOption[] = [
    { value: 'registration', label: 'Pendaftaran' },
    { value: 'other', label: 'Lainnya (feedback, survei, …)' },
];

const requiresFormOptions = computed<SimpleSelectOption[]>(() => [
    { value: noSelectionSentinel, label: 'Tidak ada' },
    ...props.siblingForms.map((sibling) => ({ value: sibling.id, label: sibling.title })),
]);

const registrationModeOptions: SimpleSelectOption[] = [
    { value: noSelectionSentinel, label: 'Not set (individual)' },
    { value: 'single', label: 'Single' },
    { value: 'bundle', label: 'Bundle' },
    { value: 'team', label: 'Team' },
];

const isRegistrationPurpose = computed(() => formMetadata.value.purpose !== 'other');

const isTeamStyleRegistration = computed(() => {
    if (!isRegistrationPurpose.value) return false;
    const mode = formMetadata.value.registration_mode;
    return mode === 'team' || mode === 'bundle';
});

function onPurposeChange(value: string): void {
    formMetadata.value = {
        ...formMetadata.value,
        purpose: value === 'other' ? 'other' : 'registration',
        ...(value === 'other' ? { registration_mode: null, max_team_size: null, team_size: null } : {}),
    };
}

function onRequiresFormChange(value: string): void {
    formMetadata.value = {
        ...formMetadata.value,
        requires_form_id: value === noSelectionSentinel || value === '' ? null : value,
    };
}

function onRegistrationModeChange(value: string): void {
    const mode: FormRegistrationMetadata['registration_mode'] =
        value === noSelectionSentinel || value === '' ? null : (value as FormRegistrationMetadata['registration_mode']);
    const keepSizes = mode === 'team' || mode === 'bundle';
    formMetadata.value = {
        ...formMetadata.value,
        registration_mode: mode,
        ...(keepSizes ? {} : { max_team_size: null, team_size: null }),
    };
}

function setTeamSizes(key: 'max_team_size' | 'team_size', value: string | number): void {
    const raw = typeof value === 'number' ? String(value) : value;
    const n = raw.trim() === '' ? null : Number(raw);
    formMetadata.value = {
        ...formMetadata.value,
        [key]: n === null || Number.isNaN(n) ? null : n,
    };
}

function displaySize(key: 'max_team_size' | 'team_size'): string {
    const v = formMetadata.value[key];
    return v == null ? '' : String(v);
}

/** Saat mencari, semua kategori hasil tampil terbuka; default ikuti single-expand. */
function isCategoryExpanded(name: string): boolean {
    const q = searchQuery.value.trim();
    return q !== '' ? true : name === props.openCategoryName;
}
</script>

<template>
    <aside
        class="border-border bg-background hidden w-[260px] shrink-0 flex-col border-r lg:flex lg:max-h-full lg:self-start"
        aria-label="Component palette"
    >
        <div class="border-border shrink-0 border-b px-4 pt-5 pb-4">
            <h2 class="font-display text-foreground text-sm font-semibold tracking-tight">Komponen</h2>
            <p class="text-muted-foreground mt-1 text-xs leading-snug">Tarik ke kanvas di tengah.</p>
            <div class="relative mt-4">
                <Search
                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari komponen…"
                    class="border-border bg-background text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-primary/20 h-11 w-full rounded-lg border py-2.5 pr-3 pl-10 text-sm shadow-sm transition-[border-color,box-shadow] focus:ring-2 focus:outline-none"
                />
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
            <div v-for="cat in categories" :key="cat.name" class="mb-2.5 last:mb-0">
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground mb-1.5 flex w-full items-center gap-2 px-1.5 py-1 text-left text-xs font-semibold tracking-wide uppercase transition-colors"
                    :aria-expanded="isCategoryExpanded(cat.name)"
                    @click="$emit('toggleCategory', cat)"
                >
                    <ChevronRight
                        class="size-3.5 shrink-0 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
                        :class="isCategoryExpanded(cat.name) ? 'rotate-90' : ''"
                    />
                    <span class="min-w-0 flex-1 truncate">{{ cat.name }}</span>
                    <span class="text-muted-foreground shrink-0 text-[11px] font-medium tabular-nums">
                        {{ cat.fields.length }}
                    </span>
                </button>
                <div
                    class="grid transition-[grid-template-rows,opacity] duration-250 ease-[cubic-bezier(0.22,1,0.36,1)]"
                    :class="isCategoryExpanded(cat.name) ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                >
                    <div class="min-h-0 overflow-hidden">
                        <div class="flex flex-col gap-1.5 pt-0.5">
                            <DraggableItem v-for="f in cat.fields" :key="f.type" v-bind="f" />
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="categories.length === 0" class="flex flex-col items-center py-10 text-center">
                <p class="text-muted-foreground text-sm">Tidak ada komponen yang cocok</p>
            </div>

            <!-- Pengaturan form tetap tersedia di Create; halaman edit menampilkannya sebagai tab tingkat atas. -->
            <div v-if="showFormSettings" class="border-border/70 mt-3 border-t pt-3">
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs font-semibold tracking-wide uppercase transition-colors"
                    :aria-expanded="formSettingsOpen"
                    @click="$emit('toggleFormSettings')"
                >
                    <Settings2 class="size-3.5 shrink-0" aria-hidden="true" />
                    <span class="min-w-0 flex-1 truncate">Pengaturan form</span>
                    <ChevronDown
                        class="size-3.5 shrink-0 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
                        :class="formSettingsOpen ? 'rotate-180' : ''"
                        aria-hidden="true"
                    />
                </button>

                <div
                    class="grid transition-[grid-template-rows,opacity] duration-250 ease-[cubic-bezier(0.22,1,0.36,1)]"
                    :class="formSettingsOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                >
                    <div class="min-h-0 overflow-hidden">
                        <div class="mt-2.5 flex flex-col gap-2.5 px-0.5">
                            <SplitDateTimeField
                                id-prefix="l-closed-at"
                                v-model="closedAt"
                                label="Tanggal tutup"
                                required
                                layout="col"
                                :invalid="!!props.fieldErrors.closed_at"
                                :error="props.fieldErrors.closed_at"
                            />

                            <div class="flex flex-col gap-1.5">
                                <Label class="text-xs font-medium"
                                    >Visibilitas <span class="text-destructive">*</span></Label
                                >
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="opt in props.visibilityOptions"
                                        :key="opt.value"
                                        type="button"
                                        class="rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-[border-color,background-color,color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]"
                                        :class="
                                            visibleFor.includes(opt.value)
                                                ? 'border-primary/40 bg-primary/10 text-primary'
                                                : 'border-border bg-background text-muted-foreground hover:border-primary/25 hover:text-foreground'
                                        "
                                        @click="$emit('toggleVisibility', opt.value, !visibleFor.includes(opt.value))"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                                <p v-if="props.fieldErrors.visible_for" class="text-destructive text-xs">
                                    {{ props.fieldErrors.visible_for }}
                                </p>
                            </div>

                            <div class="border-border/70 mt-0.5 flex flex-col gap-2.5 border-t pt-2.5">
                                <div class="flex flex-col gap-1">
                                    <Label for="l-purpose" class="text-xs font-medium">Tujuan form</Label>
                                    <SimpleSelect
                                        :model-value="formMetadata.purpose"
                                        :options="purposeOptions"
                                        id="l-purpose"
                                        class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                                        aria-label="Tujuan form"
                                        @update:model-value="onPurposeChange"
                                    />
                                    <p class="text-muted-foreground text-[11px] leading-snug">
                                        Form pendaftaran memakai kuota & jendela daftar acara. Form lainnya tidak.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <Label for="l-requires-form" class="text-xs font-medium">Memerlukan form</Label>
                                    <SimpleSelect
                                        :model-value="formMetadata.requires_form_id ?? noSelectionSentinel"
                                        :options="requiresFormOptions"
                                        id="l-requires-form"
                                        class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                                        aria-label="Memerlukan form"
                                        @update:model-value="onRequiresFormChange"
                                    />
                                    <p class="text-muted-foreground text-[11px] leading-snug">
                                        Peserta harus sudah diterima pada form yang dipilih sebelum mengisi form ini.
                                    </p>
                                </div>

                                <div v-if="isRegistrationPurpose" class="flex flex-col gap-1.5">
                                    <Label for="l-registration-mode" class="text-xs font-medium"
                                        >Mode registrasi</Label
                                    >
                                    <SimpleSelect
                                        :model-value="formMetadata.registration_mode ?? noSelectionSentinel"
                                        :options="registrationModeOptions"
                                        id="l-registration-mode"
                                        class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                                        aria-label="Mode registrasi"
                                        @update:model-value="onRegistrationModeChange"
                                    />
                                </div>

                                <div
                                    v-if="isTeamStyleRegistration"
                                    class="border-border/70 grid grid-cols-2 gap-2.5 border-t pt-2.5"
                                >
                                    <div class="flex flex-col gap-1">
                                        <Label for="l-max-team-size" class="text-xs font-medium">Max team size</Label>
                                        <Input
                                            id="l-max-team-size"
                                            type="number"
                                            min="2"
                                            placeholder="—"
                                            class="min-h-9 px-3 text-sm"
                                            :model-value="displaySize('max_team_size')"
                                            @update:model-value="(v) => setTeamSizes('max_team_size', v)"
                                        />
                                        <p class="text-muted-foreground text-[11px] leading-snug">
                                            Maks. anggota per tim (≥2). Dipakai jika Team size kosong.
                                        </p>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <Label for="l-team-size" class="text-xs font-medium">Team size</Label>
                                        <Input
                                            id="l-team-size"
                                            type="number"
                                            min="2"
                                            placeholder="—"
                                            class="min-h-9 px-3 text-sm"
                                            :model-value="displaySize('team_size')"
                                            @update:model-value="(v) => setTeamSizes('team_size', v)"
                                        />
                                        <p class="text-muted-foreground text-[11px] leading-snug">
                                            Ukuran tim (≥2). Menggantikan Max team size bila diisi.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>
