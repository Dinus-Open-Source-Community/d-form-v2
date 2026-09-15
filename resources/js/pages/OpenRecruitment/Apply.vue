<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import FormFillLayout from '@/layouts/FormFillLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import FormFillFieldSlotRows from '@/components/modules/dashboard/FormFillFieldSlotRows.vue'
import { useFormFillPage } from '@/utils/composables/useFormFillPage'
import { useAutosaveSync, type AutosaveStatus } from '@/utils/composables/useAutosaveSync'
import { readFieldRules } from '@/lib/formFieldMetadata'
import type { FormFillPageEvent, FormFillPageForm } from '@/types/form'
import { routes } from '@/lib/routes'

defineOptions({ layout: FormFillLayout })

interface ApplyPageProps {
    period: Record<string, unknown> | null
    registration: { is_open: boolean; message: string | null }
    divisions: Array<Record<string, unknown>>
    oprecForm: FormFillPageForm
    oprecEvent: FormFillPageEvent
    fields: IFormField[]
    submitUrl: string
}

const props = defineProps<ApplyPageProps>()

const ctx = useFormFillPage({
    event: props.oprecEvent,
    form: props.oprecForm,
    fields: props.fields,
    submitUrl: props.submitUrl,
    accessStatus: 'allowed',
    accessMessage: '',
    memberSlots: 0,
    registrationMode: 'single',
})

const DRAFT_KEY = 'oprec-apply-draft-v1'
const TOTAL_STEPS = 3
const STEP_LIST = [1, 2, 3]
const currentStep = ref<number>(1)
const stepAttempted = ref<boolean>(false)
const stepBannerErrors = ref<string[]>([])
const savedAt = ref<Date | null>(null)

function stepOf(field: IFormField): number {
    return typeof field.metadata.step === 'number' ? field.metadata.step : 0
}

function stepFields(step: number): IFormField[] {
    return props.fields.filter((field) => stepOf(field) === step)
}

function isEmptyValue(value: unknown): boolean {
    return value === null || value === undefined || value === ''
}

function validateStep(step: number): boolean {
    const missing = stepFields(step)
        .filter((field) => Boolean(readFieldRules(field).required))
        .filter((field) => isEmptyValue(ctx.answerForm[field.name]))
        .map((field) => field.label)
    stepBannerErrors.value = missing.map((label) => `${label} wajib diisi.`)
    return missing.length === 0
}

function syncStepToUrl(step: number, replace: boolean): void {
    const url = new URL(window.location.href)
    url.searchParams.set('step', String(step))
    if (replace) window.history.replaceState({}, '', url)
    else window.history.pushState({}, '', url)
}

function goToStep(step: number): void {
    if (step > currentStep.value && !validateStep(currentStep.value)) {
        stepAttempted.value = true
        return
    }
    stepAttempted.value = false
    stepBannerErrors.value = []
    currentStep.value = step
    syncStepToUrl(step, false)
}

function onPopState(): void {
    const step = Number(new URL(window.location.href).searchParams.get('step') || '1')
    if (step >= 1 && step <= TOTAL_STEPS) {
        currentStep.value = step
        stepBannerErrors.value = []
    }
}

function draftSnapshot(): string {
    const values: Record<string, unknown> = {}
    for (const [key, value] of Object.entries(ctx.answerForm.data())) {
        if (value instanceof File) continue
        values[key] = value
    }
    return JSON.stringify({ step: currentStep.value, values })
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
})

watch(draftStatus, (value: AutosaveStatus): void => {
    if (value === 'saved') savedAt.value = new Date()
})

const draftStatusText = computed((): string => {
    if (draftStatus.value === 'saving') return 'Menyimpan…'
    if (draftStatus.value === 'saved') {
        const time = savedAt.value?.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) ?? ''
        return `Draft tersimpan otomatis · ${time}`
    }
    return ''
})

function isDraftShape(value: unknown): value is { step?: unknown; values?: unknown } {
    return typeof value === 'object' && value !== null
}

function restoreDraft(): void {
    const raw = window.localStorage.getItem(DRAFT_KEY)
    if (!raw) return
    let parsed: unknown
    try {
        parsed = JSON.parse(raw)
    } catch {
        return
    }
    if (!isDraftShape(parsed)) return
    if (typeof parsed.step === 'number' && parsed.step >= 1 && parsed.step <= TOTAL_STEPS) {
        currentStep.value = parsed.step
    }
    if (typeof parsed.values === 'object' && parsed.values !== null) {
        for (const [key, value] of Object.entries(parsed.values)) {
            if (typeof value === 'string' && key in ctx.answerForm) {
                ctx.answerForm[key] = value
            }
        }
    }
}

function firstStepWithErrors(): number {
    for (let step = 1; step <= TOTAL_STEPS; step += 1) {
        if (ctx.cardErrorsForFields(stepFields(step)).length > 0) return step
    }
    return currentStep.value
}

async function submitStep(): Promise<void> {
    for (let step = 1; step <= TOTAL_STEPS; step += 1) {
        if (!validateStep(step)) {
            currentStep.value = step
            stepAttempted.value = true
            syncStepToUrl(step, false)
            return
        }
    }
    await flushDraft()
    ctx.answerForm.post(props.submitUrl, {
        forceFormData: true,
        onSuccess: () => window.localStorage.removeItem(DRAFT_KEY),
        onError: () => {
            currentStep.value = firstStepWithErrors()
        },
    })
}

function openUploadLightbox(src: string | undefined): void {
    if (src) window.open(src, '_blank', 'noopener')
}

const reviewRows = computed((): Array<{ label: string; value: string }> => {
    const get = (name: string): string => {
        const value: unknown = ctx.answerForm[name]
        if (value instanceof File) return value.name
        return typeof value === 'string' && value !== '' ? value : '—'
    }
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
    ]
})

onMounted(() => {
    const fromUrl = Number(new URL(window.location.href).searchParams.get('step') || '1')
    if (fromUrl >= 1 && fromUrl <= TOTAL_STEPS) currentStep.value = fromUrl
    restoreDraft()
    syncStepToUrl(currentStep.value, true)
    window.addEventListener('popstate', onPopState)
})

onBeforeUnmount(() => {
    window.removeEventListener('popstate', onPopState)
    cancelDraft()
})

const isBlocked = computed((): boolean => !props.registration.is_open)

const STEPS = [
    { n: 1, label: 'Data diri', hint: 'Nama, NIM, kontak' },
    { n: 2, label: 'Divisi', hint: 'Utama dan cadangan' },
    { n: 3, label: 'Berkas', hint: 'CV, portfolio, review' },
] as const

const progressPct = computed((): number => (currentStep.value / TOTAL_STEPS) * 100)

const periodName = computed((): string => {
    const raw: unknown = props.period?.name
    return typeof raw === 'string' && raw !== '' ? raw : 'Open Recruitment'
})
</script>

<template>
    <Head :title="`Daftar — ${periodName}`" />

    <div class="mx-auto max-w-2xl px-2 selection:bg-primary/15">
        <div class="mb-6 space-y-2 text-center">
            <h1 class="text-2xl font-bold tracking-tight">Formulir Pendaftaran</h1>
            <p v-if="period" class="text-muted-foreground text-sm">
                Periode: {{ periodName }}
            </p>
            <p class="text-muted-foreground mx-auto max-w-md text-xs leading-relaxed">
                Tiga langkah singkat — setelah submit, pantau progress lewat
                <Link :href="routes.openRecruitment.track.login" class="text-primary underline-offset-2 hover:underline">
                    portal tracking
                </Link>
                dengan nomor pendaftaran & token email.
            </p>
        </div>

        <Card v-if="isBlocked" class="rounded-2xl border-border/70">
            <CardContent class="space-y-4 p-6 text-center">
                <p class="font-medium">Pendaftaran belum tersedia</p>
                <p class="text-muted-foreground text-sm">{{ registration.message }}</p>
                <Button as-child variant="outline">
                    <Link :href="routes.openRecruitment.landing">Kembali ke landing</Link>
                </Button>
            </CardContent>
        </Card>

        <template v-else>
            <nav aria-label="Langkah pendaftaran" class="mb-4">
                <ol class="flex items-start gap-1 sm:gap-2">
                    <li v-for="s in STEPS" :key="s.n" class="flex-1">
                        <button
                            type="button"
                            class="flex w-full flex-col items-center gap-1 rounded-xl px-1 py-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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
                    <div class="bg-primary h-full rounded-full transition-[width]" :style="{ width: `${progressPct}%` }" />
                </div>
            </nav>

            <div
                class="mb-4 rounded-xl border border-dashed border-border/70 bg-muted/40 px-4 py-3 text-xs leading-relaxed"
            >
                <p class="text-foreground font-medium">Draft tersimpan otomatis di browser ini</p>
                <p class="text-muted-foreground mt-0.5">
                    File tidak ikut tersimpan — pilih ulang CV dan file portfolio sebelum submit.
                </p>
            </div>

            <template v-for="step in STEP_LIST" :key="step">
                <div v-show="currentStep === step" class="flex flex-col gap-4">
                    <div
                        v-if="stepAttempted && stepBannerErrors.length > 0"
                        class="rounded-2xl border border-destructive/40 bg-destructive/5 px-4 py-3"
                        role="alert"
                    >
                        <p v-for="(message, i) in stepBannerErrors" :key="i" class="text-xs font-medium text-destructive">
                            {{ message }}
                        </p>
                    </div>
                    <div
                        v-if="ctx.cardErrorsForFields(stepFields(step)).length > 0"
                        class="rounded-2xl border border-destructive/40 bg-destructive/5 px-4 py-3"
                        role="alert"
                    >
                        <p
                            v-for="(message, i) in ctx.cardErrorsForFields(stepFields(step))"
                            :key="i"
                            class="text-xs font-medium text-destructive"
                        >
                            {{ message }}
                        </p>
                    </div>
                    <Card v-for="field in stepFields(step)" :key="field.id" class="rounded-2xl border border-border bg-card shadow-sm">
                        <FormFillFieldSlotRows
                            :ctx="ctx"
                            :field="field"
                            :participation-slot="{ slotIndex: null, title: '' }"
                            :storage-key="field.name"
                            variant="linear"
                            :image-upload-fill-ready-fn="() => false"
                            @open-lightbox="openUploadLightbox"
                        />
                    </Card>
                    <div v-if="step === 3" class="rounded-2xl border border-border bg-card shadow-sm">
                        <div class="border-b border-border px-4 py-3">
                            <h2 class="text-sm font-semibold text-foreground">Periksa kembali data kamu</h2>
                        </div>
                        <dl class="divide-y divide-border px-4">
                            <div v-for="row in reviewRows" :key="row.label" class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-xs text-muted-foreground">{{ row.label }}</dt>
                                <dd class="max-w-[60%] truncate text-right text-xs font-medium text-foreground">{{ row.value }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <Button v-if="step > 1" type="button" variant="outline" @click="goToStep(step - 1)">
                            Kembali
                        </Button>
                        <span v-else />
                        <Button v-if="step < TOTAL_STEPS" type="button" @click="goToStep(step + 1)">
                            Lanjut
                        </Button>
                        <Button v-else type="button" @click="submitStep">
                            Kirim Pendaftaran
                        </Button>
                    </div>
                    <p class="text-center text-[11px] text-muted-foreground" aria-live="polite">{{ draftStatusText }}</p>
                </div>
            </template>
        </template>
    </div>
</template>
