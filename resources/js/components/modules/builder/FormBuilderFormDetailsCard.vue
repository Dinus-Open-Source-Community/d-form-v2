<script setup lang="ts">
import { computed } from 'vue'
import { Input } from '@/components/ui/input'
import { DateTimePicker } from '@/components/ui/date-picker'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { ChevronDown } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import TipTapEditor from '@/components/modules/dashboard/events/TipTapEditor.vue'
import type { FormPurpose, FormRegistrationMetadata, FormSiblingOption } from '@/types/form'

const formTitle = defineModel<string>('formTitle', { required: true })
const formDescription = defineModel<string>('formDescription', { required: true })
const successContent = defineModel<string>('successContent', { required: true })
const closedAt = defineModel<string>('closedAt', { required: true })
const visibleFor = defineModel<string[]>('visibleFor', { required: true })
const formMetadata = defineModel<FormRegistrationMetadata>('formMetadata', { required: true })

withDefaults(
    defineProps<{
        idPrefix: string
        fieldErrors: Partial<Record<'title' | 'description' | 'closed_at' | 'visible_for', string>>
        visibilityOptions: readonly { value: string; label: string }[]
        siblingForms?: FormSiblingOption[]
    }>(),
    {
        siblingForms: () => [],
    },
)

defineEmits<{
    toggleVisibility: [value: string, checked: boolean]
}>()

const isRegistrationPurpose = computed(() => formMetadata.value.purpose !== 'other')

const isTeamStyleRegistration = computed(() => {
    if (!isRegistrationPurpose.value) {
        return false
    }
    const m = formMetadata.value.registration_mode
    return m === 'team' || m === 'bundle'
})

const registrationModeSelectSentinel = '__none__' as const
const requiresFormSelectSentinel = '__none__' as const

const registrationModeSelectClass = cn(
    'h-10 min-h-10 w-full appearance-none rounded-xl border border-input bg-card px-3 py-2 pr-10 text-sm font-medium text-foreground shadow-xs ring-offset-background',
    'transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]',
    'hover:border-primary/30',
    'focus:border-ring focus:outline-none focus:ring-[3px] focus:ring-ring/30',
    'disabled:cursor-not-allowed disabled:opacity-50',
)

function onPurposeChange(ev: Event): void {
    const raw = (ev.target as HTMLSelectElement).value
    const purpose: FormPurpose = raw === 'other' ? 'other' : 'registration'
    formMetadata.value = {
        ...formMetadata.value,
        purpose,
        ...(purpose === 'other'
            ? { registration_mode: null, max_team_size: null, team_size: null }
            : {}),
    }
}

function onRequiresFormChange(ev: Event): void {
    const raw = (ev.target as HTMLSelectElement).value
    formMetadata.value = {
        ...formMetadata.value,
        requires_form_id: raw === requiresFormSelectSentinel || raw === '' ? null : raw,
    }
}

function onRegistrationModeChange(ev: Event): void {
    const raw = (ev.target as HTMLSelectElement).value
    onRegistrationModeSelect(raw)
}

function onRegistrationModeSelect(raw: string): void {
    const mode: FormRegistrationMetadata['registration_mode'] =
        raw === registrationModeSelectSentinel || raw === ''
            ? null
            : (raw as FormRegistrationMetadata['registration_mode'])
    const leaveSizes = mode === 'team' || mode === 'bundle'
    formMetadata.value = {
        ...formMetadata.value,
        registration_mode: mode,
        ...(leaveSizes ? {} : { max_team_size: null, team_size: null }),
    }
}

function setMaxTeamSize(v: string | number): void {
    const s = typeof v === 'number' ? String(v) : vString(v)
    const n = s === '' ? null : Number(s)
    formMetadata.value = {
        ...formMetadata.value,
        max_team_size: n === null || Number.isNaN(n) ? null : n,
    }
}

function setTeamSize(v: string | number): void {
    const s = typeof v === 'number' ? String(v) : vString(v)
    const n = s === '' ? null : Number(s)
    formMetadata.value = {
        ...formMetadata.value,
        team_size: n === null || Number.isNaN(n) ? null : n,
    }
}

function vString(v: unknown): string {
    return v == null ? '' : String(v)
}
</script>

<template>
    <section class="border-border bg-card space-y-5 rounded-2xl border p-6 shadow-xs">
        <h3 class="font-display text-foreground text-base font-semibold tracking-tight">Form details</h3>
        <div class="space-y-2">
            <Label :for="`${idPrefix}-title`" class="text-sm font-medium"
                >Title <span class="text-destructive">*</span></Label
            >
            <Input
                :id="`${idPrefix}-title`"
                v-model="formTitle"
                placeholder="e.g. Speaker Registration"
                class="min-h-12 !py-3.5 px-4 text-sm leading-normal"
            />
            <p v-if="fieldErrors.title" class="text-destructive text-xs">{{ fieldErrors.title }}</p>
        </div>
        <div class="space-y-2">
            <Label :for="`${idPrefix}-desc`" class="text-sm font-medium"
                >Description <span class="text-destructive">*</span></Label
            >
            <Textarea
                :id="`${idPrefix}-desc`"
                v-model="formDescription"
                rows="3"
                placeholder="Tell registrants what this form is for..."
                class="min-h-[6rem] !py-3.5 px-4 text-sm leading-relaxed"
            />
            <p v-if="fieldErrors.description" class="text-destructive text-xs">{{ fieldErrors.description }}</p>
        </div>
        <div class="space-y-2">
            <Label :for="`${idPrefix}-success`" class="text-sm font-medium">Informasi setelah submit</Label>
            <p class="text-muted-foreground text-xs leading-snug">
                Ditampilkan ke peserta setelah berhasil mengirim form (mis. link grup, informasi).
            </p>
            <TipTapEditor v-model="successContent" />
        </div>
        <div class="space-y-2">
            <Label :for="`${idPrefix}-closed`" class="text-sm font-medium"
                >Closes at <span class="text-destructive">*</span></Label
            >
            <DateTimePicker
                :id="`${idPrefix}-closed`"
                v-model="closedAt"
                placeholder="Pilih tanggal & jam tutup"
                class="min-h-12 text-sm"
            />
            <p v-if="fieldErrors.closed_at" class="text-destructive text-xs">{{ fieldErrors.closed_at }}</p>
        </div>
        <div class="space-y-3">
            <Label class="text-sm font-medium">Visibility <span class="text-destructive">*</span></Label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="opt in visibilityOptions"
                    :key="opt.value"
                    type="button"
                    @click="$emit('toggleVisibility', opt.value, !visibleFor.includes(opt.value))"
                    :class="[
                        'rounded-full border px-4 py-2 text-sm font-medium transition-[border-color,background-color,color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]',
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
                <div class="relative w-full">
                    <select
                        :id="`${idPrefix}-purpose`"
                        :value="formMetadata.purpose"
                        :class="registrationModeSelectClass"
                        @change="onPurposeChange"
                    >
                        <option value="registration">Pendaftaran</option>
                        <option value="other">Lainnya (feedback, survei, …)</option>
                    </select>
                    <ChevronDown
                        class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 opacity-50"
                        aria-hidden="true"
                    />
                </div>
                <p class="text-muted-foreground text-xs leading-snug">
                    Form pendaftaran memakai kuota & jendela daftar acara. Form lainnya tidak.
                </p>
            </div>

            <div class="space-y-2">
                <Label :for="`${idPrefix}-requires`" class="text-sm font-medium">Memerlukan form</Label>
                <div class="relative w-full">
                    <select
                        :id="`${idPrefix}-requires`"
                        :value="formMetadata.requires_form_id ?? requiresFormSelectSentinel"
                        :class="registrationModeSelectClass"
                        @change="onRequiresFormChange"
                    >
                        <option :value="requiresFormSelectSentinel">Tidak ada</option>
                        <option v-for="sibling in siblingForms" :key="sibling.id" :value="sibling.id">
                            {{ sibling.title }}
                        </option>
                    </select>
                    <ChevronDown
                        class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 opacity-50"
                        aria-hidden="true"
                    />
                </div>
                <p class="text-muted-foreground text-xs leading-snug">
                    Peserta harus sudah diterima pada form yang dipilih sebelum mengisi form ini.
                </p>
            </div>

            <div v-if="isRegistrationPurpose" class="space-y-2">
                <Label :for="`${idPrefix}-reg-mode`" class="text-sm font-medium">Registration mode</Label>
                <div class="relative w-full">
                    <select
                        :id="`${idPrefix}-reg-mode`"
                        :value="formMetadata.registration_mode ?? registrationModeSelectSentinel"
                        :class="registrationModeSelectClass"
                        @change="onRegistrationModeChange"
                    >
                        <option :value="registrationModeSelectSentinel">Not set (individual)</option>
                        <option value="single">Single</option>
                        <option value="bundle">Bundle</option>
                        <option value="team">Team</option>
                    </select>
                    <ChevronDown
                        class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 opacity-50"
                        aria-hidden="true"
                    />
                </div>
            </div>
            <div v-if="isTeamStyleRegistration" class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label :for="`${idPrefix}-max-team`" class="text-sm font-medium">Max team size</Label>
                    <p class="text-muted-foreground text-xs leading-snug">
                        Maks. anggota per tim (≥2). Dipakai jika <span class="font-medium text-foreground/90">Team size</span> kosong.
                    </p>
                    <Input
                        :id="`${idPrefix}-max-team`"
                        type="number"
                        min="2"
                        placeholder="—"
                        class="min-h-12 !py-3.5 px-4 text-sm"
                        :model-value="formMetadata.max_team_size == null ? '' : String(formMetadata.max_team_size)"
                        @update:modelValue="setMaxTeamSize"
                    />
                </div>
                <div class="space-y-2">
                    <Label :for="`${idPrefix}-team-size`" class="text-sm font-medium">Team size</Label>
                    <p class="text-muted-foreground text-xs leading-snug">
                        Ukuran tim (≥2). Menggantikan <span class="font-medium text-foreground/90">Max team size</span> bila diisi.
                    </p>
                    <Input
                        :id="`${idPrefix}-team-size`"
                        type="number"
                        min="2"
                        placeholder="—"
                        class="min-h-12 !py-3.5 px-4 text-sm"
                        :model-value="formMetadata.team_size == null ? '' : String(formMetadata.team_size)"
                        @update:modelValue="setTeamSize"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
