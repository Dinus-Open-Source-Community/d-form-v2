<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import { ChevronDown, Download, ListOrdered } from 'lucide-vue-next'

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
        cv_download_url: string | null
        portfolio_download_url: string | null
        portfolio_url: string | null
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

const props = defineProps<{
    detail: DetailPayload
    evaluateUrl: string
    recommendationOptions: { value: string; label: string }[]
}>()

const profileOpen = ref(false)
const canEdit = computed(() => props.detail.evaluation.can_edit !== false)

const recommendationChoices = computed(() =>
    props.recommendationOptions.length > 0
        ? props.recommendationOptions
        : [
              { value: 'recommended', label: 'Direkomendasikan' },
              { value: 'not_recommended', label: 'Tidak direkomendasikan' },
          ],
)

const form = useForm({
    speaking_score: props.detail.evaluation.speaking_score ?? 5,
    technical_score: props.detail.evaluation.technical_score ?? 5,
    attitude_score: props.detail.evaluation.attitude_score ?? 5,
    recommendation: props.detail.evaluation.recommendation ?? 'recommended',
    notes: props.detail.evaluation.notes ?? '',
})

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

const heroTitle = computed(() => {
    if (canEdit.value && !props.detail.evaluation.is_locked) {
        return 'Isi penilaian interview'
    }
    if (props.detail.evaluation.is_locked) {
        return 'Penilaian sudah dikirim'
    }
    return 'Tinjau profil applicant'
})

const heroDescription = computed(() => {
    if (canEdit.value && !props.detail.evaluation.is_locked) {
        return 'Skor dan rekomendasi wajib diisi setelah sesi interview selesai.'
    }
    if (props.detail.evaluation.is_locked) {
        return 'Penilaian terkunci. Hubungi staff jika perlu koreksi.'
    }
    return 'Baca CV/portfolio di bawah sebelum interview dimulai.'
})

onMounted(() => {
    setTopbar({
        title: props.detail.application.full_name,
        subtitle: props.detail.application.registration_number,
    })
})

function submit() {
    form.post(props.evaluateUrl, { preserveScroll: true })
}
</script>

<template>
    <Head :title="`Interview — ${detail.application.full_name}`" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <Card class="rounded-2xl border-primary/25 bg-primary/5">
            <CardContent class="space-y-3 p-5">
                <div>
                    <p class="font-semibold">{{ heroTitle }}</p>
                    <p class="text-muted-foreground mt-1 text-sm">{{ heroDescription }}</p>
                </div>
                <div class="flex flex-wrap gap-2 text-sm">
                    <span v-if="interviewSchedule" class="text-muted-foreground">
                        {{ interviewSchedule }}
                    </span>
                    <span v-if="detail.interview" class="text-muted-foreground">
                        · {{ detail.interview.location }} · {{ detail.interview.room }}
                    </span>
                    <span v-if="detail.queue" class="font-medium">
                        · Antrean #{{ String(detail.queue.queue_number).padStart(2, '0') }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="detail.interview?.session" as-child variant="outline" size="sm">
                        <Link :href="routes.admin.recruitment.myInterviews.queue(detail.interview.session.id)">
                            <ListOrdered class="mr-2 size-4" />
                            Antrean sesi
                        </Link>
                    </Button>
                    <Button v-if="detail.documents.cv_download_url" as-child variant="secondary" size="sm">
                        <a :href="detail.documents.cv_download_url" target="_blank" rel="noopener">
                            <Download class="mr-2 size-4" />
                            CV
                        </a>
                    </Button>
                    <Button v-if="detail.documents.portfolio_download_url" as-child variant="secondary" size="sm">
                        <a :href="detail.documents.portfolio_download_url" target="_blank" rel="noopener">
                            <Download class="mr-2 size-4" />
                            Portfolio
                        </a>
                    </Button>
                    <Button v-else-if="detail.documents.portfolio_url" as-child variant="secondary" size="sm">
                        <a :href="detail.documents.portfolio_url" target="_blank" rel="noopener">
                            Portfolio
                        </a>
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border-border/70">
            <CardContent class="p-6">
                <form v-if="canEdit" class="space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="space-y-2">
                            <Label for="speaking_score">Speaking</Label>
                            <Input
                                id="speaking_score"
                                v-model.number="form.speaking_score"
                                type="number"
                                min="1"
                                max="10"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="technical_score">Technical</Label>
                            <Input
                                id="technical_score"
                                v-model.number="form.technical_score"
                                type="number"
                                min="1"
                                max="10"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="attitude_score">Attitude</Label>
                            <Input
                                id="attitude_score"
                                v-model.number="form.attitude_score"
                                type="number"
                                min="1"
                                max="10"
                                required
                            />
                        </div>
                    </div>

                    <fieldset class="space-y-2">
                        <legend class="text-sm font-medium leading-none">Rekomendasi</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="opt in recommendationChoices"
                                :key="opt.value"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm transition-colors"
                                :class="
                                    form.recommendation === opt.value
                                        ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                                        : 'border-border hover:bg-muted/40'
                                "
                            >
                                <input
                                    v-model="form.recommendation"
                                    type="radio"
                                    name="recommendation"
                                    :value="opt.value"
                                    class="size-4 shrink-0"
                                    required
                                />
                                <span class="font-medium">{{ opt.label }}</span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="space-y-2">
                        <Label for="notes">Catatan</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="4"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Observasi singkat..."
                        />
                    </div>

                    <Button type="submit" :disabled="form.processing">Simpan penilaian</Button>
                </form>

                <div v-else class="space-y-2 text-sm">
                    <p>Speaking: {{ detail.evaluation.speaking_score }}/10</p>
                    <p>Technical: {{ detail.evaluation.technical_score }}/10</p>
                    <p>Attitude: {{ detail.evaluation.attitude_score }}/10</p>
                    <p>Rekomendasi: {{ detail.evaluation.recommendation_label }}</p>
                    <p v-if="detail.evaluation.notes" class="text-muted-foreground whitespace-pre-wrap">
                        {{ detail.evaluation.notes }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border-border/70">
            <button
                type="button"
                class="flex w-full items-center justify-between p-5 text-left"
                @click="profileOpen = !profileOpen"
            >
                <span class="font-medium">Profil applicant</span>
                <ChevronDown
                    class="size-4 shrink-0 transition-transform"
                    :class="profileOpen ? 'rotate-180' : ''"
                />
            </button>
            <CardContent v-show="profileOpen" class="space-y-2 border-t px-5 pb-5 pt-4 text-sm">
                <p><span class="text-muted-foreground">NIM:</span> {{ detail.application.nim }}</p>
                <p><span class="text-muted-foreground">Semester:</span> {{ detail.application.semester }}</p>
                <p>
                    <span class="text-muted-foreground">Divisi:</span>
                    {{ detail.application.primary_division }}
                    <span v-if="detail.application.secondary_division">
                        / {{ detail.application.secondary_division }}
                    </span>
                </p>
                <p v-if="detail.interview">
                    <span class="text-muted-foreground">Status interview:</span>
                    {{ detail.interview.status_label }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
