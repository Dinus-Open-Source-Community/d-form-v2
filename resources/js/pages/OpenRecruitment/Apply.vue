<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, useTemplateRef, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import FormFillLayout from '@/layouts/FormFillLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent } from '@/components/ui/card'
import { routes } from '@/lib/routes'

defineOptions({ layout: FormFillLayout })

interface PeriodSummary {
    id: string
    name: string
    status_label: string
}

interface DivisionOption {
    id: string
    name: string
}

const props = defineProps<{
    period: PeriodSummary | null
    registration: {
        is_open: boolean
        message: string
    }
    divisions: DivisionOption[]
    submitUrl: string
}>()

const DRAFT_KEY = 'oprec-apply-draft-v1'

const form = useForm({
    full_name: '',
    nim: '',
    semester: '',
    phone: '',
    personal_email: '',
    student_email: '',
    instagram_username: '',
    primary_division_id: '',
    secondary_division_id: '',
    portfolio_type: 'url' as 'url' | 'file',
    portfolio_url: '',
    portfolio_file: null as File | null,
    cv: null as File | null,
})

const isBlocked = computed((): boolean => !props.registration.is_open)

const STEPS = [
    { n: 1, label: 'Data diri', hint: 'Nama, NIM, kontak' },
    { n: 2, label: 'Divisi', hint: 'Utama dan cadangan' },
    { n: 3, label: 'Berkas', hint: 'CV, portfolio, review' },
] as const

const currentStep = ref<number>(1)
const maxReached = ref<number>(1)
const localErrors = ref<Record<string, string>>({})
const draftRestored = ref<boolean>(false)
const draftSavedAt = ref<string | null>(null)

const stepHeadingRef = useTemplateRef<HTMLElement>('stepHeading')
let saveTimer: ReturnType<typeof setTimeout> | null = null

function clampStep(n: number): number {
    if (Number.isNaN(n)) return 1
    return Math.min(3, Math.max(1, n))
}

function stepFromUrl(): number {
    if (typeof window === 'undefined') return 1
    const raw = new URLSearchParams(window.location.search).get('step')
    const parsed = raw === null ? NaN : Number.parseInt(raw, 10)
    return clampStep(parsed)
}

function syncStepToUrl(step: number, push: boolean): void {
    if (typeof window === 'undefined') return
    const url = new URL(window.location.href)
    url.searchParams.set('step', String(step))
    if (push) {
        window.history.pushState({ oprecStep: step }, '', url)
    } else {
        window.history.replaceState({ oprecStep: step }, '', url)
    }
}

function onPopState(event: PopStateEvent): void {
    const fromState =
        typeof event.state === 'object' && event.state !== null && 'oprecStep' in event.state
            ? Number((event.state as { oprecStep: unknown }).oprecStep)
            : NaN
    const next = Number.isNaN(fromState) ? stepFromUrl() : clampStep(fromState)
    currentStep.value = next
    maxReached.value = Math.max(maxReached.value, next)
    nextTick(() => stepHeadingRef.value?.focus())
}

function divisionName(id: string): string {
    if (!id) return '—'
    return props.divisions.find((d) => d.id === id)?.name ?? '—'
}

function isEmail(value: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
}

function isUrl(value: string): boolean {
    try {
        const u = new URL(value.trim())
        return u.protocol === 'http:' || u.protocol === 'https:'
    } catch {
        return false
    }
}

function validateStep(step: number): boolean {
    const errs: Record<string, string> = {}
    if (step === 1) {
        if (form.full_name.trim().length < 3) errs.full_name = 'Isi nama lengkap.'
        if (form.nim.trim().length < 3) errs.nim = 'Isi NIM dengan benar.'
        if (!['1', '2', '3'].includes(form.semester)) errs.semester = 'Pilih semester 1–3.'
        if (form.phone.replace(/\D/g, '').length < 9) errs.phone = 'Nomor telepon minimal 9 digit angka.'
        if (form.instagram_username.trim().length === 0)
            errs.instagram_username = 'Isi username Instagram tanpa @.'
        if (!isEmail(form.personal_email)) errs.personal_email = 'Email pribadi tidak valid.'
        if (!isEmail(form.student_email)) errs.student_email = 'Email kampus tidak valid.'
    }
    if (step === 2) {
        if (!form.primary_division_id) errs.primary_division_id = 'Pilih divisi utama.'
        if (
            form.secondary_division_id &&
            form.secondary_division_id === form.primary_division_id
        ) {
            errs.secondary_division_id = 'Divisi cadangan harus beda dengan divisi utama.'
        }
    }
    if (step === 3) {
        if (!(form.cv instanceof File)) errs.cv = 'Unggah CV dalam format PDF.'
        if (form.portfolio_type === 'url') {
            if (!isUrl(form.portfolio_url)) errs.portfolio_url = 'Isi URL portfolio diawali https://.'
        } else if (!(form.portfolio_file instanceof File)) {
            errs.portfolio_file = 'Unggah file portfolio dalam format PDF.'
        }
    }
    localErrors.value = { ...localErrors.value, ...errs }
    for (const key of Object.keys(localErrors.value)) {
        if (!(key in errs) && stepOwnsKey(step, key)) delete localErrors.value[key]
    }
    return Object.keys(errs).length === 0
}

function stepOwnsKey(step: number, key: string): boolean {
    if (step === 1)
        return ['full_name', 'nim', 'semester', 'phone', 'instagram_username', 'personal_email', 'student_email'].includes(key)
    if (step === 2) return ['primary_division_id', 'secondary_division_id'].includes(key)
    return ['cv', 'portfolio_url', 'portfolio_file'].includes(key)
}

function clearLocalError(key: string): void {
    if (localErrors.value[key]) delete localErrors.value[key]
}

function fieldError(key: string): string {
    const server = (form.errors as Record<string, string>)[key]
    return localErrors.value[key] ?? server ?? ''
}

function goToStep(n: number, push = true): void {
    const next = clampStep(n)
    currentStep.value = next
    maxReached.value = Math.max(maxReached.value, next)
    syncStepToUrl(next, push)
    nextTick(() => stepHeadingRef.value?.focus())
}

function handleNext(): void {
    if (!validateStep(currentStep.value)) {
        nextTick(() => stepHeadingRef.value?.focus())
        return
    }
    goToStep(currentStep.value + 1)
}

function handleBack(): void {
    goToStep(currentStep.value - 1)
}

function handleStepperClick(target: number): void {
    if (target <= maxReached.value) {
        goToStep(target)
        return
    }
    let cursor = currentStep.value
    while (cursor < target) {
        if (!validateStep(cursor)) {
            goToStep(cursor, false)
            return
        }
        cursor += 1
    }
    goToStep(target)
}

function onCvChange(event: Event): void {
    const target = event.target as HTMLInputElement
    form.cv = target.files?.[0] ?? null
    clearLocalError('cv')
}

function onPortfolioFileChange(event: Event): void {
    const target = event.target as HTMLInputElement
    form.portfolio_file = target.files?.[0] ?? null
    clearLocalError('portfolio_file')
}

function submit(): void {
    for (const s of [1, 2, 3]) {
        if (!validateStep(s)) {
            goToStep(s, false)
            return
        }
    }
    form.post(props.submitUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => clearDraftStorage(),
    })
}

interface DraftPayload {
    v: number
    savedAt: string
    step: number
    fields: {
        full_name: string
        nim: string
        semester: string
        phone: string
        personal_email: string
        student_email: string
        instagram_username: string
        primary_division_id: string
        secondary_division_id: string
        portfolio_type: 'url' | 'file'
        portfolio_url: string
    }
}

function readDraft(): DraftPayload | null {
    if (typeof window === 'undefined') return null
    try {
        const raw = window.localStorage.getItem(DRAFT_KEY)
        if (!raw) return null
        const parsed: unknown = JSON.parse(raw)
        if (typeof parsed !== 'object' || parsed === null) return null
        const p = parsed as Partial<DraftPayload>
        if (p.v !== 1 || typeof p.fields !== 'object' || p.fields === null) return null
        return p as DraftPayload
    } catch {
        return null
    }
}

function saveDraftNow(): void {
    if (typeof window === 'undefined' || isBlocked.value) return
    const payload: DraftPayload = {
        v: 1,
        savedAt: new Date().toISOString(),
        step: currentStep.value,
        fields: {
            full_name: form.full_name,
            phone: form.phone,
            nim: form.nim,
            semester: form.semester,
            personal_email: form.personal_email,
            student_email: form.student_email,
            instagram_username: form.instagram_username,
            primary_division_id: form.primary_division_id,
            secondary_division_id: form.secondary_division_id,
            portfolio_type: form.portfolio_type,
            portfolio_url: form.portfolio_url,
        },
    }
    try {
        window.localStorage.setItem(DRAFT_KEY, JSON.stringify(payload))
        draftSavedAt.value = payload.savedAt
    } catch {
        // storage penuh / private mode — abaikan, form tetap jalan
    }
}

function scheduleDraftSave(): void {
    if (typeof window === 'undefined' || isBlocked.value) return
    if (saveTimer) clearTimeout(saveTimer)
    saveTimer = setTimeout(saveDraftNow, 400)
}

function clearDraftStorage(): void {
    if (typeof window === 'undefined') return
    try {
        window.localStorage.removeItem(DRAFT_KEY)
    } catch {
        // abaikan
    }
    draftRestored.value = false
    draftSavedAt.value = null
}

function restoreDraft(): void {
    const draft = readDraft()
    if (!draft) return
    const f = draft.fields
    if (typeof f.full_name === 'string') form.full_name = f.full_name
    if (typeof f.nim === 'string') form.nim = f.nim
    if (typeof f.semester === 'string') form.semester = f.semester
    if (typeof f.phone === 'string') form.phone = f.phone
    if (typeof f.personal_email === 'string') form.personal_email = f.personal_email
    if (typeof f.student_email === 'string') form.student_email = f.student_email
    if (typeof f.instagram_username === 'string') form.instagram_username = f.instagram_username
    if (typeof f.primary_division_id === 'string') form.primary_division_id = f.primary_division_id
    if (typeof f.secondary_division_id === 'string') form.secondary_division_id = f.secondary_division_id
    if (f.portfolio_type === 'url' || f.portfolio_type === 'file') form.portfolio_type = f.portfolio_type
    if (typeof f.portfolio_url === 'string') form.portfolio_url = f.portfolio_url
    draftRestored.value = true
    draftSavedAt.value = draft.savedAt
}

const progressPct = computed((): number => (currentStep.value / 3) * 100)
const cvFileName = computed((): string | null => (form.cv instanceof File ? form.cv.name : null))
const portfolioFileName = computed((): string | null =>
    form.portfolio_file instanceof File ? form.portfolio_file.name : null,
)
const draftTimeLabel = computed((): string | null => {
    if (!draftSavedAt.value) return null
    try {
        return new Date(draftSavedAt.value).toLocaleString('id-ID', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        })
    } catch {
        return null
    }
})

watch(
    [
        () => form.full_name,
        () => form.nim,
        () => form.semester,
        () => form.phone,
        () => form.personal_email,
        () => form.student_email,
        () => form.instagram_username,
        () => form.primary_division_id,
        () => form.secondary_division_id,
        () => form.portfolio_type,
        () => form.portfolio_url,
        currentStep,
    ],
    scheduleDraftSave,
)

onMounted(() => {
    if (!isBlocked.value) restoreDraft()
    const initial = stepFromUrl()
    if (draftRestored.value) {
        const draft = readDraft()
        const draftStep = draft ? clampStep(draft.step) : 1
        currentStep.value = Number.isNaN(initial) || !new URLSearchParams(window.location.search).has('step') ? draftStep : initial
        maxReached.value = Math.max(draftStep, currentStep.value)
    } else {
        currentStep.value = Number.isNaN(initial) ? 1 : initial
        maxReached.value = currentStep.value
    }
    syncStepToUrl(currentStep.value, false)
    window.addEventListener('popstate', onPopState)
})

onUnmounted(() => {
    if (typeof window !== 'undefined') window.removeEventListener('popstate', onPopState)
    if (saveTimer) clearTimeout(saveTimer)
})
</script>

<template>
    <Head :title="`Daftar — ${period?.name ?? 'OpenRecruitment'}`" />

    <div class="mx-auto max-w-2xl px-2 selection:bg-primary/15">
        <div class="mb-6 space-y-2 text-center">
            <h1 class="text-2xl font-bold tracking-tight">Formulir Pendaftaran</h1>
            <p v-if="period" class="text-muted-foreground text-sm">
                Periode: {{ period.name }} · {{ period.status_label }}
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
                            :disabled="s.n > maxReached"
                            @click="handleStepperClick(s.n)"
                        >
                            <span
                                class="flex size-7 items-center justify-center rounded-full text-xs font-semibold tabular-nums"
                                :class="
                                    currentStep === s.n
                                        ? 'bg-primary text-primary-foreground'
                                        : s.n < currentStep || s.n <= maxReached
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
                    aria-valuemax="3"
                >
                    <div class="bg-primary h-full rounded-full transition-[width]" :style="{ width: `${progressPct}%` }" />
                </div>
            </nav>

            <div
                v-if="draftRestored"
                class="mb-4 rounded-xl border border-dashed border-border/70 bg-muted/40 px-4 py-3 text-xs leading-relaxed"
            >
                <p class="text-foreground font-medium">Draft dipulihkan<span v-if="draftTimeLabel"> · {{ draftTimeLabel }}</span></p>
                <p class="text-muted-foreground mt-0.5">
                    File tidak ikut tersimpan browser — pilih ulang CV dan file portfolio sebelum submit.
                </p>
                <button
                    type="button"
                    class="text-primary mt-1 font-medium underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="clearDraftStorage"
                >
                    Hapus draft
                </button>
            </div>

            <Card class="rounded-2xl border-border/70">
                <CardContent class="p-5 sm:p-6">
                    <form class="space-y-5" novalidate @submit.prevent="submit">
                        <p v-if="form.errors.period" role="alert" class="text-destructive text-sm">{{ form.errors.period }}</p>

                        <div :key="currentStep" class="animate-fade-in">
                            <h2
                                ref="stepHeading"
                                tabindex="-1"
                                class="text-lg font-semibold focus:outline-none"
                            >
                                <span v-if="currentStep === 1">Langkah 1 — Data diri</span>
                                <span v-else-if="currentStep === 2">Langkah 2 — Divisi</span>
                                <span v-else>Langkah 3 — Berkas dan review</span>
                            </h2>
                            <p class="text-muted-foreground mt-0.5 text-sm">
                                <span v-if="currentStep === 1">Isi kontak yang aktif. Email dipakai untuk token tracking.</span>
                                <span v-else-if="currentStep === 2">Pilih divisi utama. Cadangan opsional tapi harus beda.</span>
                                <span v-else>Unggah berkas, periksa ringkasan, baru kirim.</span>
                            </p>

                            <div aria-live="polite" class="sr-only">
                                <span v-if="Object.keys(localErrors).length > 0">Ada isian yang perlu diperbaiki.</span>
                            </div>

                            <div v-if="currentStep === 1" class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="full_name">Nama lengkap</Label>
                                    <Input
                                        id="full_name"
                                        v-model="form.full_name"
                                        autocomplete="name"
                                        @input="clearLocalError('full_name')"
                                    />
                                    <p v-if="fieldError('full_name')" role="alert" class="text-destructive text-xs">
                                        {{ fieldError('full_name') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="nim">NIM</Label>
                                    <Input id="nim" v-model="form.nim" autocomplete="off" @input="clearLocalError('nim')" />
                                    <p v-if="fieldError('nim')" role="alert" class="text-destructive text-xs">
                                        {{ fieldError('nim') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="semester">Semester</Label>
                                    <select
                                        id="semester"
                                        v-model="form.semester"
                                        class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        @change="clearLocalError('semester')"
                                    >
                                        <option disabled value="">Pilih semester</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                    </select>
                                    <p v-if="fieldError('semester')" role="alert" class="text-destructive text-xs">
                                        {{ fieldError('semester') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="phone">Nomor telepon</Label>
                                    <Input
                                        id="phone"
                                        v-model="form.phone"
                                        inputmode="tel"
                                        autocomplete="tel"
                                        placeholder="081234567890"
                                        @input="clearLocalError('phone')"
                                    />
                                    <p v-if="fieldError('phone')" role="alert" class="text-destructive text-xs">
                                        {{ fieldError('phone') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="instagram_username">Instagram</Label>
                                    <Input
                                        id="instagram_username"
                                        v-model="form.instagram_username"
                                        placeholder="username"
                                        autocomplete="off"
                                        @input="clearLocalError('instagram_username')"
                                    />
                                    <p v-if="fieldError('instagram_username')" role="alert" class="text-destructive text-xs">
                                        {{ fieldError('instagram_username') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="personal_email">Email pribadi</Label>
                                    <Input
                                        id="personal_email"
                                        v-model="form.personal_email"
                                        type="email"
                                        autocomplete="email"
                                        @input="clearLocalError('personal_email')"
                                    />
                                    <p v-if="fieldError('personal_email')" role="alert" class="text-destructive text-xs">
                                        {{ fieldError('personal_email') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="student_email">Email kampus</Label>
                                    <Input
                                        id="student_email"
                                        v-model="form.student_email"
                                        type="email"
                                        autocomplete="email"
                                        @input="clearLocalError('student_email')"
                                    />
                                    <p v-if="fieldError('student_email')" role="alert" class="text-destructive text-xs">
                                        {{ fieldError('student_email') }}
                                    </p>
                                </div>
                            </div>

                            <div v-else-if="currentStep === 2" class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="primary_division_id">Divisi utama</Label>
                                    <select
                                        id="primary_division_id"
                                        v-model="form.primary_division_id"
                                        class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        @change="clearLocalError('primary_division_id'); clearLocalError('secondary_division_id')"
                                    >
                                        <option disabled value="">Pilih divisi</option>
                                        <option v-for="division in divisions" :key="division.id" :value="division.id">
                                            {{ division.name }}
                                        </option>
                                    </select>
                                    <p v-if="fieldError('primary_division_id')" role="alert" class="text-destructive text-xs">
                                        {{ fieldError('primary_division_id') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="secondary_division_id">Divisi cadangan (opsional)</Label>
                                    <select
                                        id="secondary_division_id"
                                        v-model="form.secondary_division_id"
                                        class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        @change="clearLocalError('secondary_division_id')"
                                    >
                                        <option value="">— Tidak ada —</option>
                                        <option v-for="division in divisions" :key="division.id" :value="division.id">
                                            {{ division.name }}
                                        </option>
                                    </select>
                                    <p v-if="fieldError('secondary_division_id')" role="alert" class="text-destructive text-xs">
                                        {{ fieldError('secondary_division_id') }}
                                    </p>
                                </div>
                                <p class="text-muted-foreground text-xs sm:col-span-2">
                                    Pilihan bisa diubah lagi lewat portal tracking selama revisi masih dibuka panitia.
                                </p>
                            </div>

                            <div v-else class="mt-5 space-y-5">
                                <div class="space-y-2">
                                    <Label for="cv">CV (PDF, maks. 5 MB)</Label>
                                    <Input id="cv" type="file" accept="application/pdf,.pdf" @change="onCvChange" />
                                    <p v-if="cvFileName" class="text-muted-foreground text-xs">Terpilih: {{ cvFileName }}</p>
                                    <p v-else-if="draftRestored" class="text-muted-foreground text-xs">
                                        Draft tidak menyimpan file — pilih ulang CV.
                                    </p>
                                    <p v-if="fieldError('cv')" role="alert" class="text-destructive text-xs">
                                        {{ fieldError('cv') }}
                                    </p>
                                </div>

                                <fieldset class="space-y-3">
                                    <legend class="text-sm font-medium leading-none">Portfolio</legend>
                                    <div class="flex flex-wrap gap-4 text-sm">
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="form.portfolio_type"
                                                type="radio"
                                                value="url"
                                                name="portfolio_type"
                                                class="size-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            />
                                            URL
                                        </label>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="form.portfolio_type"
                                                type="radio"
                                                value="file"
                                                name="portfolio_type"
                                                class="size-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            />
                                            File PDF
                                        </label>
                                    </div>
                                    <div v-if="form.portfolio_type === 'url'" class="space-y-2">
                                        <Input
                                            v-model="form.portfolio_url"
                                            type="url"
                                            placeholder="https://..."
                                            aria-label="URL portfolio"
                                            @input="clearLocalError('portfolio_url')"
                                        />
                                        <p v-if="fieldError('portfolio_url')" role="alert" class="text-destructive text-xs">
                                            {{ fieldError('portfolio_url') }}
                                        </p>
                                    </div>
                                    <div v-else class="space-y-2">
                                        <Input type="file" accept="application/pdf,.pdf" aria-label="File portfolio" @change="onPortfolioFileChange" />
                                        <p v-if="portfolioFileName" class="text-muted-foreground text-xs">
                                            Terpilih: {{ portfolioFileName }}
                                        </p>
                                        <p v-else-if="draftRestored" class="text-muted-foreground text-xs">
                                            Draft tidak menyimpan file — pilih ulang file portfolio.
                                        </p>
                                        <p v-if="fieldError('portfolio_file')" role="alert" class="text-destructive text-xs">
                                            {{ fieldError('portfolio_file') }}
                                        </p>
                                    </div>
                                </fieldset>

                                <div class="rounded-xl border border-border/70 bg-muted/30 p-4">
                                    <p class="text-sm font-medium">Ringkasan sebelum dikirim</p>
                                    <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                                        <div><dt class="text-muted-foreground text-xs">Nama</dt><dd class="font-medium">{{ form.full_name || '—' }}</dd></div>
                                        <div><dt class="text-muted-foreground text-xs">NIM · Semester</dt><dd class="font-medium">{{ form.nim || '—' }} · {{ form.semester || '—' }}</dd></div>
                                        <div><dt class="text-muted-foreground text-xs">Telepon</dt><dd class="font-medium">{{ form.phone || '—' }}</dd></div>
                                        <div><dt class="text-muted-foreground text-xs">Instagram</dt><dd class="font-medium">{{ form.instagram_username || '—' }}</dd></div>
                                        <div><dt class="text-muted-foreground text-xs">Email pribadi</dt><dd class="font-medium break-all">{{ form.personal_email || '—' }}</dd></div>
                                        <div><dt class="text-muted-foreground text-xs">Email kampus</dt><dd class="font-medium break-all">{{ form.student_email || '—' }}</dd></div>
                                        <div><dt class="text-muted-foreground text-xs">Divisi utama</dt><dd class="font-medium">{{ divisionName(form.primary_division_id) }}</dd></div>
                                        <div><dt class="text-muted-foreground text-xs">Divisi cadangan</dt><dd class="font-medium">{{ divisionName(form.secondary_division_id) }}</dd></div>
                                    </dl>
                                    <button
                                        type="button"
                                        class="text-primary mt-3 text-xs font-medium underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        @click="goToStep(1)"
                                    >
                                        Periksa lagi data diri dan divisi
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            <Button v-if="currentStep > 1" type="button" variant="outline" @click="handleBack">
                                Kembali
                            </Button>
                            <Button v-if="currentStep === 1" as-child variant="outline" type="button">
                                <Link :href="routes.openRecruitment.landing">Batal</Link>
                            </Button>
                            <Button v-if="currentStep < 3" type="button" @click="handleNext">
                                Lanjut
                            </Button>
                            <Button v-else type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Mengirim...' : 'Kirim pendaftaran' }}
                            </Button>
                            <p class="text-muted-foreground w-full text-xs">
                                Draft tersimpan otomatis di browser ini<span v-if="draftTimeLabel"> · {{ draftTimeLabel }}</span>.
                            </p>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
