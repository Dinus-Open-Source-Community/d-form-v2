<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import SessionQueueDrawer from '@/components/modules/dashboard/recruitment/SessionQueueDrawer.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Sheet, SheetContent } from '@/components/ui/sheet'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import useAuth from '@/utils/composables/useAuth'
import {
    Check,
    CheckCircle2,
    Circle,
    Download,
    ExternalLink,
    FileText,
    ListOrdered,
    Minus,
    Plus,
    XCircle,
} from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

interface DetailPayload {
    application: {
        id: string
        registration_number: string
        full_name: string
        nim: string
        semester: number
        primary_division: string | null
        secondary_division: string | null
    }
    documents: {
        has_cv?: boolean
        has_portfolio?: boolean
        portfolio_is_url?: boolean
        cv_download_url: string | null
        portfolio_download_url: string | null
        portfolio_url: string | null
        cv_original_name?: string | null
        cv_size_bytes?: number | null
        cv_preview_url?: string | null
        portfolio_original_name?: string | null
        portfolio_size_bytes?: number | null
        portfolio_preview_url?: string | null
    }
    interview: {
        scheduled_at: string
        location: string
        room: string
        status_label: string
        session: { id: string; session_date: string; division: string | null } | null
    } | null
    queue: { queue_number: number; status_label: string } | null
    evaluation: {
        speaking_score?: number
        technical_score?: number
        attitude_score?: number
        recommendation?: string
        recommendation_label?: string
        notes?: string | null
        is_locked?: boolean
        can_edit?: boolean
    }
}

interface QueuePermission {
    can_view_recruitment_queue?: boolean
}

const props = defineProps<{
    detail: DetailPayload
    evaluateUrl: string
    recommendationOptions: { value: string; label: string }[]
    flashMessage: string | null
}>()

const page = usePage()
const authUser = useAuth(page.props)

const canViewQueue = computed<boolean>((): boolean => {
    const candidate: QueuePermission | null = authUser.value
    return candidate?.can_view_recruitment_queue === true
})

const queueDrawerOpen = ref<boolean>(false)

const canEdit = computed(() => props.detail.evaluation.can_edit !== false)

const isLocked = computed<boolean>((): boolean => props.detail.evaluation.is_locked === true)

const interviewStartsInFuture = computed<boolean>((): boolean => {
    const iso: string | null = props.detail.interview?.scheduled_at ?? null
    if (!iso) return false
    const starts: Date = new Date(iso)
    if (Number.isNaN(starts.getTime())) return false
    return starts.getTime() > Date.now()
})

const blockReason = computed<string | null>((): string | null => {
    if (isLocked.value) return 'Penilaian sudah terkunci. Hubungi staff jika perlu koreksi.'
    if (!props.detail.interview) {
        return 'Jadwal interview belum tersedia. Penilaian bisa disimpan setelah jadwal ditentukan.'
    }
    if (interviewStartsInFuture.value) {
        const schedule: string = interviewSchedule.value ?? 'jadwal yang tercantum'
        return `Interview dijadwalkan ${schedule}. Penilaian bisa disimpan setelah jadwal dimulai.`
    }
    return null
})

const recommendationChoices = computed(() =>
    props.recommendationOptions.length > 0
        ? props.recommendationOptions
        : [
              { value: 'recommended', label: 'Direkomendasikan' },
              { value: 'not_recommended', label: 'Tidak direkomendasikan' },
          ],
)

interface RecommendationStyle {
    card: string
    tile: string
    indicator: string
}

const RECOMMENDATION_STYLES: Partial<Record<string, RecommendationStyle>> = {
    recommended: {
        card: 'border-success/40 bg-success/5',
        tile: 'border-success/30 bg-success/10 text-success',
        indicator: 'text-success',
    },
    not_recommended: {
        card: 'border-destructive/40 bg-destructive/5',
        tile: 'border-destructive/30 bg-destructive/10 text-destructive',
        indicator: 'text-destructive',
    },
}

const RECOMMENDATION_FALLBACK_STYLE: RecommendationStyle = {
    card: 'border-primary/40 bg-primary/5',
    tile: 'border-primary/30 bg-primary/10 text-primary',
    indicator: 'text-primary',
}

const RECOMMENDATION_CARD_IDLE: string = 'border-border bg-card hover:bg-muted/40'
const RECOMMENDATION_TILE_IDLE: string =
    'border-border/70 bg-muted/40 text-muted-foreground group-hover:text-foreground'

function recommendationStyle(value: string): RecommendationStyle {
    return RECOMMENDATION_STYLES[value] ?? RECOMMENDATION_FALLBACK_STYLE
}

function isRecommendationSelected(value: string): boolean {
    return form.recommendation === value
}

type ScoreField = 'speaking_score' | 'technical_score' | 'attitude_score'

const SCORE_MIN: number = 1
const SCORE_MAX: number = 10
const SCORE_DEFAULT: number = 5

function clampScore(value: number): number {
    return Math.min(SCORE_MAX, Math.max(SCORE_MIN, Math.round(value)))
}

const form = useForm({
    speaking_score: clampScore(props.detail.evaluation.speaking_score ?? SCORE_DEFAULT),
    technical_score: clampScore(props.detail.evaluation.technical_score ?? SCORE_DEFAULT),
    attitude_score: clampScore(props.detail.evaluation.attitude_score ?? SCORE_DEFAULT),
    recommendation: props.detail.evaluation.recommendation ?? 'recommended',
    notes: props.detail.evaluation.notes ?? '',
})

function scoreValue(field: ScoreField): number {
    const raw: unknown = form[field]
    const parsed: number = typeof raw === 'number' ? raw : Number.parseInt(String(raw ?? ''), 10)
    return Number.isFinite(parsed) ? clampScore(parsed) : SCORE_DEFAULT
}

function canDecrease(field: ScoreField): boolean {
    return !form.processing && !isLocked.value && scoreValue(field) > SCORE_MIN
}

function canIncrease(field: ScoreField): boolean {
    return !form.processing && !isLocked.value && scoreValue(field) < SCORE_MAX
}

function adjustScore(field: ScoreField, delta: number): void {
    form[field] = clampScore(scoreValue(field) + delta)
}

function commitScore(field: ScoreField): void {
    form[field] = scoreValue(field)
}

function onScoreInput(field: ScoreField, event: Event): void {
    const target: EventTarget | null = event.target
    if (!(target instanceof HTMLInputElement)) return

    const digits: string = target.value.replace(/\D+/g, '').slice(0, 2)
    const next: string = digits === '' ? '' : String(clampScore(Number.parseInt(digits, 10)))

    if (next !== target.value) target.value = next
    if (next !== '') form[field] = Number.parseInt(next, 10)
}

function onScoreKeydown(field: ScoreField, event: KeyboardEvent): void {
    if (event.key === 'Enter') {
        commitScore(field)
        return
    }

    if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return

    event.preventDefault()
    adjustScore(field, event.key === 'ArrowUp' ? 1 : -1)
}

const interviewSchedule = computed(() => {
    if (!props.detail.interview?.scheduled_at) return null
    return new Date(props.detail.interview.scheduled_at).toLocaleString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    })
})

const queuePollUrl = computed<string>((): string => {
    const sessionId: string | null = props.detail.interview?.session?.id ?? null
    return sessionId !== null ? routes.admin.recruitment.queue.poll(sessionId) : ''
})

function isFilled(value: string | null | undefined): value is string {
    return typeof value === 'string' && value.trim() !== ''
}

function formatBytes(bytes: number | null | undefined): string | null {
    if (typeof bytes !== 'number' || !Number.isFinite(bytes) || bytes <= 0) return null
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const cvPreviewUrl = computed<string | null>(() => {
    const url = props.detail.documents.cv_preview_url
    return isFilled(url) ? url : null
})

const cvDownloadUrl = computed<string | null>(() => {
    const url = props.detail.documents.cv_download_url
    return isFilled(url) ? url : null
})

const cvOriginalName = computed<string>(() => {
    const name = props.detail.documents.cv_original_name
    return isFilled(name) ? name : 'Berkas CV'
})

const cvMetaLabel = computed<string>(() => {
    const size: string | null = formatBytes(props.detail.documents.cv_size_bytes)
    return size !== null ? `CV · ${size}` : 'CV'
})

const cvAvailable = computed<boolean>(() => cvPreviewUrl.value !== null || cvDownloadUrl.value !== null)

const portfolioExternalUrl = computed<string | null>(() => {
    const documents = props.detail.documents
    if (documents.portfolio_is_url === false) return null
    return isFilled(documents.portfolio_url) ? documents.portfolio_url : null
})

const portfolioPreviewUrl = computed<string | null>(() => {
    const url = props.detail.documents.portfolio_preview_url
    return isFilled(url) ? url : null
})

const portfolioDownloadUrl = computed<string | null>(() => {
    const url = props.detail.documents.portfolio_download_url
    return isFilled(url) ? url : null
})

const portfolioOriginalName = computed<string>(() => {
    const name = props.detail.documents.portfolio_original_name
    return isFilled(name) ? name : 'Berkas portfolio'
})

const portfolioMetaLabel = computed<string>(() => {
    const size: string | null = formatBytes(props.detail.documents.portfolio_size_bytes)
    return size !== null ? `Portfolio · ${size}` : 'Portfolio'
})

const portfolioFileAvailable = computed<boolean>(
    () =>
        portfolioExternalUrl.value === null &&
        (portfolioPreviewUrl.value !== null || portfolioDownloadUrl.value !== null),
)

const hasAnyDocument = computed<boolean>(
    () => cvAvailable.value || portfolioExternalUrl.value !== null || portfolioFileAvailable.value,
)

const cvPreviewLoading = ref<boolean>(true)
const cvPreviewFailed = ref<boolean>(false)
const portfolioPreviewLoading = ref<boolean>(true)
const portfolioPreviewFailed = ref<boolean>(false)

watch(
    () => props.detail.application.id,
    () => {
        cvPreviewLoading.value = true
        cvPreviewFailed.value = false
        portfolioPreviewLoading.value = true
        portfolioPreviewFailed.value = false
    },
)

onMounted(() => {
    setTopbar({
        title: props.detail.application.full_name,
        subtitle: props.detail.application.registration_number,
    })
})

function submit(): void {
    if (blockReason.value !== null || form.processing) return
    form.post(props.evaluateUrl, { preserveScroll: true })
}
</script>

<template>
    <Head :title="`Interview — ${detail.application.full_name}`" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="grid items-start gap-6 lg:grid-cols-12">
            <div class="flex min-w-0 flex-col gap-6 lg:col-span-7">
                <Card class="rounded-2xl border-border/70">
                    <CardContent class="p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="text-sm font-semibold">Sesi interview &amp; antrean</h2>
                            <Button
                                v-if="detail.interview?.session && canViewQueue"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="shrink-0"
                                @click="queueDrawerOpen = true"
                            >
                                <ListOrdered class="mr-2 size-4" aria-hidden="true" />
                                Antrean sesi
                            </Button>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <p class="text-muted-foreground text-xs uppercase">Jadwal</p>
                                <p v-if="interviewSchedule" class="mt-0.5 font-medium">
                                    {{ interviewSchedule }}
                                </p>
                                <p v-else class="mt-0.5 text-muted-foreground">Jadwal belum ditetapkan</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs uppercase">Lokasi</p>
                                <p v-if="detail.interview" class="mt-0.5 font-medium">
                                    {{ detail.interview.location }} · {{ detail.interview.room }}
                                </p>
                                <p v-else class="mt-0.5 text-muted-foreground">—</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs uppercase">Antrean</p>
                                <p v-if="detail.queue" class="mt-0.5 font-medium">
                                    <span class="font-mono tabular-nums">
                                        #{{ String(detail.queue.queue_number).padStart(2, '0') }}
                                    </span>
                                    <span class="text-muted-foreground"> · {{ detail.queue.status_label }}</span>
                                </p>
                                <p v-else class="mt-0.5 text-muted-foreground">—</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border-border/70">
                    <CardContent class="p-6">
                        <h2 class="text-sm font-semibold">Profil applicant</h2>

                        <div class="mt-4 flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                            <div class="min-w-0">
                                <p class="text-xl font-semibold tracking-tight">
                                    {{ detail.application.full_name }}
                                </p>
                                <p class="mt-1 font-mono text-xs tabular-nums text-muted-foreground">
                                    {{ detail.application.registration_number }}
                                </p>
                            </div>
                            <Badge v-if="detail.interview?.status_label" variant="outline" class="shrink-0">
                                {{ detail.interview?.status_label }}
                            </Badge>
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-3">
                            <div>
                                <p class="text-muted-foreground text-xs uppercase">NIM</p>
                                <p class="mt-0.5 font-medium">{{ detail.application.nim }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs uppercase">Semester</p>
                                <p class="mt-0.5 font-medium tabular-nums">{{ detail.application.semester }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs uppercase">Divisi</p>
                                <p class="mt-0.5 font-medium">
                                    {{ detail.application.primary_division }}
                                    <span v-if="detail.application.secondary_division">
                                        / {{ detail.application.secondary_division }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <Separator class="my-6" />

                        <h3 class="text-sm font-semibold">Berkas</h3>

                        <div v-if="hasAnyDocument" class="mt-4 space-y-5">
                            <div v-if="cvAvailable" class="space-y-3">
                                <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <FileText class="text-muted-foreground size-5 shrink-0" aria-hidden="true" />
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">{{ cvOriginalName }}</p>
                                            <p class="text-muted-foreground text-xs">{{ cvMetaLabel }}</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Button v-if="cvDownloadUrl" as-child variant="outline" size="sm">
                                            <a :href="cvDownloadUrl">
                                                <Download class="mr-2 size-4" aria-hidden="true" />
                                                Unduh CV
                                            </a>
                                        </Button>
                                        <Button
                                            v-if="cvPreviewUrl && !cvPreviewFailed"
                                            as-child
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <a :href="cvPreviewUrl" target="_blank" rel="noopener">
                                                <ExternalLink class="mr-2 size-4" aria-hidden="true" />
                                                Buka di tab baru
                                            </a>
                                        </Button>
                                    </div>
                                </div>

                                <div
                                    v-if="cvPreviewUrl"
                                    class="relative overflow-hidden rounded-xl border border-border/70 bg-muted/30"
                                >
                                    <iframe
                                        v-show="!cvPreviewFailed"
                                        :src="cvPreviewUrl"
                                        title="Pratinjau CV"
                                        class="h-80 w-full bg-white"
                                        loading="lazy"
                                        @load="cvPreviewLoading = false"
                                        @error="cvPreviewFailed = true; cvPreviewLoading = false"
                                    />
                                    <div
                                        v-if="cvPreviewLoading && !cvPreviewFailed"
                                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-muted/30 p-6 text-center"
                                        aria-live="polite"
                                    >
                                        <div
                                            class="border-muted-foreground/30 border-t-foreground size-8 animate-spin rounded-full border-2"
                                            aria-hidden="true"
                                        />
                                        <p class="text-muted-foreground text-sm">Memuat pratinjau CV…</p>
                                    </div>
                                    <div
                                        v-if="cvPreviewFailed"
                                        class="flex flex-col items-center justify-center gap-3 p-6 text-center"
                                    >
                                        <p class="text-muted-foreground text-sm">
                                            Pratinjau tidak dapat dimuat. Gunakan tombol unduh untuk membuka berkas.
                                        </p>
                                        <Button v-if="cvDownloadUrl" as-child variant="outline" size="sm">
                                            <a :href="cvDownloadUrl">
                                                <Download class="mr-2 size-4" aria-hidden="true" />
                                                Unduh CV
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                                <p v-else class="text-muted-foreground text-sm">
                                    Pratinjau CV tidak tersedia. Gunakan tombol unduh untuk membuka berkas.
                                </p>
                            </div>

                            <div
                                v-if="portfolioExternalUrl"
                                class="space-y-3"
                                :class="cvAvailable ? 'border-t border-border/60 pt-5' : ''"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <a
                                        :href="portfolioExternalUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-primary inline-flex min-w-0 max-w-full items-center gap-2 text-sm underline-offset-4 hover:underline"
                                    >
                                        <ExternalLink class="size-4 shrink-0" aria-hidden="true" />
                                        <span class="truncate">{{ portfolioExternalUrl }}</span>
                                    </a>
                                    <Button as-child variant="outline" size="sm">
                                        <a :href="portfolioExternalUrl" target="_blank" rel="noopener noreferrer">
                                            <ExternalLink class="mr-2 size-4" aria-hidden="true" />
                                            Buka tautan
                                        </a>
                                    </Button>
                                </div>
                                <p class="text-muted-foreground text-xs">Portfolio · tautan eksternal</p>
                            </div>

                            <div
                                v-else-if="portfolioFileAvailable"
                                class="space-y-3"
                                :class="cvAvailable ? 'border-t border-border/60 pt-5' : ''"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <FileText class="text-muted-foreground size-5 shrink-0" aria-hidden="true" />
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">{{ portfolioOriginalName }}</p>
                                            <p class="text-muted-foreground text-xs">{{ portfolioMetaLabel }}</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Button v-if="portfolioDownloadUrl" as-child variant="outline" size="sm">
                                            <a :href="portfolioDownloadUrl">
                                                <Download class="mr-2 size-4" aria-hidden="true" />
                                                Unduh portfolio
                                            </a>
                                        </Button>
                                        <Button
                                            v-if="portfolioPreviewUrl && !portfolioPreviewFailed"
                                            as-child
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <a :href="portfolioPreviewUrl" target="_blank" rel="noopener">
                                                <ExternalLink class="mr-2 size-4" aria-hidden="true" />
                                                Buka di tab baru
                                            </a>
                                        </Button>
                                    </div>
                                </div>

                                <div
                                    v-if="portfolioPreviewUrl"
                                    class="relative overflow-hidden rounded-xl border border-border/70 bg-muted/30"
                                >
                                    <iframe
                                        v-show="!portfolioPreviewFailed"
                                        :src="portfolioPreviewUrl"
                                        title="Pratinjau portfolio"
                                        class="h-80 w-full bg-white"
                                        loading="lazy"
                                        @load="portfolioPreviewLoading = false"
                                        @error="
                                            portfolioPreviewFailed = true; portfolioPreviewLoading = false
                                        "
                                    />
                                    <div
                                        v-if="portfolioPreviewLoading && !portfolioPreviewFailed"
                                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-muted/30 p-6 text-center"
                                        aria-live="polite"
                                    >
                                        <div
                                            class="border-muted-foreground/30 border-t-foreground size-8 animate-spin rounded-full border-2"
                                            aria-hidden="true"
                                        />
                                        <p class="text-muted-foreground text-sm">Memuat pratinjau portfolio…</p>
                                    </div>
                                    <div
                                        v-if="portfolioPreviewFailed"
                                        class="flex flex-col items-center justify-center gap-3 p-6 text-center"
                                    >
                                        <p class="text-muted-foreground text-sm">
                                            Pratinjau tidak dapat dimuat. Gunakan tombol unduh untuk membuka berkas.
                                        </p>
                                        <Button v-if="portfolioDownloadUrl" as-child variant="outline" size="sm">
                                            <a :href="portfolioDownloadUrl">
                                                <Download class="mr-2 size-4" aria-hidden="true" />
                                                Unduh portfolio
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                                <p v-else class="text-muted-foreground text-sm">
                                    Pratinjau portfolio tidak tersedia. Gunakan tombol unduh untuk membuka berkas.
                                </p>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm text-muted-foreground">CV dan portfolio belum diunggah.</p>
                    </CardContent>
                </Card>
            </div>

            <Card class="rounded-2xl border-border/70 lg:col-span-5">
                <CardContent class="p-6">
                    <h2 class="text-sm font-semibold">Penilaian interview</h2>

                    <p
                        v-if="flashMessage"
                        role="status"
                        class="mt-4 rounded-xl border border-primary/25 bg-primary/10 px-3 py-2 text-sm"
                    >
                        {{ flashMessage }}
                    </p>

                    <form v-if="canEdit" class="mt-4 space-y-4" @submit.prevent="submit">
                        <p
                            v-if="blockReason"
                            role="alert"
                            class="rounded-xl border border-warning/25 bg-warning/10 px-4 py-3 text-sm"
                        >
                            {{ blockReason }}
                        </p>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <Label for="speaking_score">Speaking</Label>
                                <div
                                    class="flex items-center justify-between gap-2 rounded-2xl border border-border/70 bg-muted/30 p-1.5"
                                >
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        radius="xl"
                                        class="text-muted-foreground shrink-0"
                                        aria-label="Kurangi nilai Speaking"
                                        :disabled="!canDecrease('speaking_score')"
                                        @click="adjustScore('speaking_score', -1)"
                                    >
                                        <Minus class="size-5" aria-hidden="true" />
                                    </Button>
                                    <div class="flex min-w-0 flex-1 items-baseline justify-center gap-1">
                                        <input
                                            id="speaking_score"
                                            v-model.number="form.speaking_score"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            maxlength="2"
                                            required
                                            role="spinbutton"
                                            :aria-valuemin="SCORE_MIN"
                                            :aria-valuemax="SCORE_MAX"
                                            :aria-valuenow="scoreValue('speaking_score')"
                                            :aria-invalid="form.errors.speaking_score ? true : undefined"
                                            :disabled="form.processing || isLocked"
                                            class="text-foreground focus-visible:bg-background focus-visible:ring-ring/30 h-11 w-10 shrink-0 rounded-lg bg-transparent p-0 text-center text-2xl font-semibold tabular-nums outline-none transition-colors duration-150 focus-visible:ring-[3px] disabled:opacity-50 motion-reduce:transition-none"
                                            @input="onScoreInput('speaking_score', $event)"
                                            @keydown="onScoreKeydown('speaking_score', $event)"
                                            @blur="commitScore('speaking_score')"
                                        />
                                        <span class="text-muted-foreground text-sm" aria-hidden="true">/10</span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        radius="xl"
                                        class="text-muted-foreground shrink-0"
                                        aria-label="Tambah nilai Speaking"
                                        :disabled="!canIncrease('speaking_score')"
                                        @click="adjustScore('speaking_score', 1)"
                                    >
                                        <Plus class="size-5" aria-hidden="true" />
                                    </Button>
                                </div>
                                <div class="flex gap-1 px-1.5" aria-hidden="true">
                                    <span
                                        v-for="tick in SCORE_MAX"
                                        :key="tick"
                                        class="h-1.5 flex-1 rounded-full transition-colors duration-150 motion-reduce:transition-none"
                                        :class="tick <= scoreValue('speaking_score') ? 'bg-primary/70' : 'bg-muted'"
                                    />
                                </div>
                                <p v-if="form.errors.speaking_score" class="text-destructive text-xs">
                                    {{ form.errors.speaking_score }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="technical_score">Technical</Label>
                                <div
                                    class="flex items-center justify-between gap-2 rounded-2xl border border-border/70 bg-muted/30 p-1.5"
                                >
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        radius="xl"
                                        class="text-muted-foreground shrink-0"
                                        aria-label="Kurangi nilai Technical"
                                        :disabled="!canDecrease('technical_score')"
                                        @click="adjustScore('technical_score', -1)"
                                    >
                                        <Minus class="size-5" aria-hidden="true" />
                                    </Button>
                                    <div class="flex min-w-0 flex-1 items-baseline justify-center gap-1">
                                        <input
                                            id="technical_score"
                                            v-model.number="form.technical_score"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            maxlength="2"
                                            required
                                            role="spinbutton"
                                            :aria-valuemin="SCORE_MIN"
                                            :aria-valuemax="SCORE_MAX"
                                            :aria-valuenow="scoreValue('technical_score')"
                                            :aria-invalid="form.errors.technical_score ? true : undefined"
                                            :disabled="form.processing || isLocked"
                                            class="text-foreground focus-visible:bg-background focus-visible:ring-ring/30 h-11 w-10 shrink-0 rounded-lg bg-transparent p-0 text-center text-2xl font-semibold tabular-nums outline-none transition-colors duration-150 focus-visible:ring-[3px] disabled:opacity-50 motion-reduce:transition-none"
                                            @input="onScoreInput('technical_score', $event)"
                                            @keydown="onScoreKeydown('technical_score', $event)"
                                            @blur="commitScore('technical_score')"
                                        />
                                        <span class="text-muted-foreground text-sm" aria-hidden="true">/10</span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        radius="xl"
                                        class="text-muted-foreground shrink-0"
                                        aria-label="Tambah nilai Technical"
                                        :disabled="!canIncrease('technical_score')"
                                        @click="adjustScore('technical_score', 1)"
                                    >
                                        <Plus class="size-5" aria-hidden="true" />
                                    </Button>
                                </div>
                                <div class="flex gap-1 px-1.5" aria-hidden="true">
                                    <span
                                        v-for="tick in SCORE_MAX"
                                        :key="tick"
                                        class="h-1.5 flex-1 rounded-full transition-colors duration-150 motion-reduce:transition-none"
                                        :class="tick <= scoreValue('technical_score') ? 'bg-primary/70' : 'bg-muted'"
                                    />
                                </div>
                                <p v-if="form.errors.technical_score" class="text-destructive text-xs">
                                    {{ form.errors.technical_score }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="attitude_score">Attitude</Label>
                                <div
                                    class="flex items-center justify-between gap-2 rounded-2xl border border-border/70 bg-muted/30 p-1.5"
                                >
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        radius="xl"
                                        class="text-muted-foreground shrink-0"
                                        aria-label="Kurangi nilai Attitude"
                                        :disabled="!canDecrease('attitude_score')"
                                        @click="adjustScore('attitude_score', -1)"
                                    >
                                        <Minus class="size-5" aria-hidden="true" />
                                    </Button>
                                    <div class="flex min-w-0 flex-1 items-baseline justify-center gap-1">
                                        <input
                                            id="attitude_score"
                                            v-model.number="form.attitude_score"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            maxlength="2"
                                            required
                                            role="spinbutton"
                                            :aria-valuemin="SCORE_MIN"
                                            :aria-valuemax="SCORE_MAX"
                                            :aria-valuenow="scoreValue('attitude_score')"
                                            :aria-invalid="form.errors.attitude_score ? true : undefined"
                                            :disabled="form.processing || isLocked"
                                            class="text-foreground focus-visible:bg-background focus-visible:ring-ring/30 h-11 w-10 shrink-0 rounded-lg bg-transparent p-0 text-center text-2xl font-semibold tabular-nums outline-none transition-colors duration-150 focus-visible:ring-[3px] disabled:opacity-50 motion-reduce:transition-none"
                                            @input="onScoreInput('attitude_score', $event)"
                                            @keydown="onScoreKeydown('attitude_score', $event)"
                                            @blur="commitScore('attitude_score')"
                                        />
                                        <span class="text-muted-foreground text-sm" aria-hidden="true">/10</span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        radius="xl"
                                        class="text-muted-foreground shrink-0"
                                        aria-label="Tambah nilai Attitude"
                                        :disabled="!canIncrease('attitude_score')"
                                        @click="adjustScore('attitude_score', 1)"
                                    >
                                        <Plus class="size-5" aria-hidden="true" />
                                    </Button>
                                </div>
                                <div class="flex gap-1 px-1.5" aria-hidden="true">
                                    <span
                                        v-for="tick in SCORE_MAX"
                                        :key="tick"
                                        class="h-1.5 flex-1 rounded-full transition-colors duration-150 motion-reduce:transition-none"
                                        :class="tick <= scoreValue('attitude_score') ? 'bg-primary/70' : 'bg-muted'"
                                    />
                                </div>
                                <p v-if="form.errors.attitude_score" class="text-destructive text-xs">
                                    {{ form.errors.attitude_score }}
                                </p>
                            </div>
                        </div>

                        <fieldset class="space-y-2" :disabled="form.processing || isLocked">
                            <legend class="text-sm font-medium leading-none">Rekomendasi</legend>
                            <div class="flex flex-col gap-2">
                                <label
                                    v-for="opt in recommendationChoices"
                                    :key="opt.value"
                                    class="group has-[:disabled]:cursor-not-allowed has-[:focus-visible]:border-ring has-[:focus-visible]:ring-ring/30 relative flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-colors duration-150 has-[:focus-visible]:ring-[3px] motion-reduce:transition-none"
                                    :class="
                                        isRecommendationSelected(opt.value)
                                            ? recommendationStyle(opt.value).card
                                            : RECOMMENDATION_CARD_IDLE
                                    "
                                >
                                    <input
                                        v-model="form.recommendation"
                                        type="radio"
                                        name="recommendation"
                                        :value="opt.value"
                                        class="sr-only"
                                        required
                                    />

                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg border transition-colors duration-150 motion-reduce:transition-none"
                                        :class="
                                            isRecommendationSelected(opt.value)
                                                ? recommendationStyle(opt.value).tile
                                                : RECOMMENDATION_TILE_IDLE
                                        "
                                    >
                                        <CheckCircle2
                                            v-if="opt.value === 'recommended'"
                                            class="size-5"
                                            aria-hidden="true"
                                        />
                                        <XCircle
                                            v-else-if="opt.value === 'not_recommended'"
                                            class="size-5"
                                            aria-hidden="true"
                                        />
                                        <Circle v-else class="size-5" aria-hidden="true" />
                                    </span>

                                    <span
                                        class="min-w-0 flex-1 text-sm"
                                        :class="
                                            isRecommendationSelected(opt.value)
                                                ? 'font-semibold'
                                                : 'font-medium'
                                        "
                                    >
                                        {{ opt.label }}
                                    </span>

                                    <Check
                                        class="size-4 shrink-0 transition-opacity duration-150 motion-reduce:transition-none"
                                        :class="
                                            isRecommendationSelected(opt.value)
                                                ? `opacity-100 ${recommendationStyle(opt.value).indicator}`
                                                : 'opacity-0'
                                        "
                                        aria-hidden="true"
                                    />
                                </label>
                            </div>
                            <p v-if="form.errors.recommendation" class="text-xs text-destructive">
                                {{ form.errors.recommendation }}
                            </p>
                        </fieldset>

                        <div class="space-y-2">
                            <Label for="notes">Catatan</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="4"
                                class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                placeholder="Observasi singkat..."
                                :disabled="form.processing || isLocked"
                                :aria-invalid="form.errors.notes ? true : undefined"
                            />
                            <p v-if="form.errors.notes" class="text-xs text-destructive">
                                {{ form.errors.notes }}
                            </p>
                        </div>

                        <Button type="submit" :disabled="form.processing || blockReason !== null">
                            Simpan penilaian
                        </Button>
                    </form>

                    <div v-else class="mt-4 space-y-4">
                        <p
                            v-if="isLocked"
                            role="status"
                            class="rounded-xl border border-warning/25 bg-warning/10 px-4 py-3 text-sm"
                        >
                            Penilaian sudah terkunci. Hubungi staff jika perlu koreksi.
                        </p>
                        <dl class="divide-y divide-border/60 rounded-xl border border-border/70 text-sm">
                            <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                <dt class="text-muted-foreground">Speaking</dt>
                                <dd class="font-semibold tabular-nums">
                                    {{ detail.evaluation.speaking_score }}/10
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                <dt class="text-muted-foreground">Technical</dt>
                                <dd class="font-semibold tabular-nums">
                                    {{ detail.evaluation.technical_score }}/10
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                <dt class="text-muted-foreground">Attitude</dt>
                                <dd class="font-semibold tabular-nums">
                                    {{ detail.evaluation.attitude_score }}/10
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                <dt class="text-muted-foreground">Rekomendasi</dt>
                                <dd class="font-medium">{{ detail.evaluation.recommendation_label }}</dd>
                            </div>
                        </dl>
                        <div v-if="detail.evaluation.notes">
                            <p class="text-muted-foreground text-xs uppercase">Catatan</p>
                            <p class="mt-1 whitespace-pre-wrap text-sm">{{ detail.evaluation.notes }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Sheet v-model:open="queueDrawerOpen">
            <SheetContent
                side="right"
                overlay-class="bg-black/60 backdrop-blur-sm"
                class="inset-y-0 right-0 h-full w-full gap-0 p-0 sm:inset-y-3 sm:right-3 sm:h-[calc(100%-1.5rem)] sm:w-[27rem] sm:max-w-[calc(100vw-1.5rem)] sm:rounded-2xl sm:border sm:shadow-xl"
            >
                <SessionQueueDrawer
                    v-if="queuePollUrl !== ''"
                    :poll-url="queuePollUrl"
                    :session-date="detail.interview?.session?.session_date ?? null"
                    :division="detail.interview?.session?.division ?? null"
                />
            </SheetContent>
        </Sheet>
    </div>
</template>
