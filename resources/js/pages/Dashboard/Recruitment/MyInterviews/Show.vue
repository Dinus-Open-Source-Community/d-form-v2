<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

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
        result_label: string
    }
    documents: {
        has_cv: boolean
        has_portfolio: boolean
        portfolio_is_url: boolean
        portfolio_url: string | null
        cv_download_url: string | null
        portfolio_download_url: string | null
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
        evaluated_at?: string
        evaluated_by?: string
        locked_at?: string
        is_locked?: boolean
        can_edit?: boolean
    }
    final: Record<string, unknown> | null
}

const props = defineProps<{
    detail: DetailPayload
    evaluateUrl: string
    recommendationOptions: { value: string; label: string }[]
}>()

const canEdit = computed(() => props.detail.evaluation.can_edit !== false)

const form = useForm({
    speaking_score: props.detail.evaluation.speaking_score ?? 5,
    technical_score: props.detail.evaluation.technical_score ?? 5,
    attitude_score: props.detail.evaluation.attitude_score ?? 5,
    recommendation: props.detail.evaluation.recommendation ?? 'recommended',
    notes: props.detail.evaluation.notes ?? '',
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

const interviewSchedule = computed(() => {
    if (!props.detail.interview?.scheduled_at) return null
    return new Date(props.detail.interview.scheduled_at).toLocaleString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
})
</script>

<template>
    <Head :title="`Interview — ${detail.application.full_name}`" />

    <PageHeader
        :title="detail.application.full_name"
        :description="detail.application.registration_number"
        :back-href="routes.admin.recruitment.myInterviews.index"
    />

    <div class="grid gap-6 lg:grid-cols-[1fr_1fr]">
        <Card class="rounded-2xl border-border/70">
            <CardHeader>
                <CardTitle class="text-base">Profil applicant</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <p><span class="text-muted-foreground">NIM:</span> {{ detail.application.nim }}</p>
                <p><span class="text-muted-foreground">Semester:</span> {{ detail.application.semester }}</p>
                <p>
                    <span class="text-muted-foreground">Divisi:</span>
                    {{ detail.application.primary_division }}
                    <span v-if="detail.application.secondary_division">
                        / {{ detail.application.secondary_division }}
                    </span>
                </p>
                <p v-if="interviewSchedule">
                    <span class="text-muted-foreground">Jadwal:</span> {{ interviewSchedule }}
                </p>
                <p v-if="detail.interview">
                    <span class="text-muted-foreground">Lokasi:</span>
                    {{ detail.interview.location }} · {{ detail.interview.room }}
                </p>
                <p v-if="detail.queue">
                    <span class="text-muted-foreground">Antrean:</span>
                    #{{ String(detail.queue.queue_number).padStart(2, '0') }} ({{ detail.queue.status_label }})
                </p>
                <div class="flex flex-wrap gap-2 pt-2">
                    <Button v-if="detail.documents.cv_download_url" as-child size="sm" variant="outline">
                        <a :href="detail.documents.cv_download_url" target="_blank" rel="noopener">Unduh CV</a>
                    </Button>
                    <Button
                        v-if="detail.documents.portfolio_download_url"
                        as-child
                        size="sm"
                        variant="outline"
                    >
                        <a :href="detail.documents.portfolio_download_url" target="_blank" rel="noopener">
                            Unduh Portfolio
                        </a>
                    </Button>
                    <Button
                        v-else-if="detail.documents.portfolio_url"
                        as-child
                        size="sm"
                        variant="outline"
                    >
                        <a :href="detail.documents.portfolio_url" target="_blank" rel="noopener">Portfolio</a>
                    </Button>
                    <Button
                        v-if="detail.interview?.session"
                        as-child
                        size="sm"
                        variant="secondary"
                    >
                        <Link :href="routes.admin.recruitment.myInterviews.queue(detail.interview.session.id)">
                            Lihat antrean sesi
                        </Link>
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border-border/70">
            <CardHeader>
                <CardTitle class="text-base">Penilaian interview</CardTitle>
            </CardHeader>
            <CardContent>
                <p
                    v-if="detail.evaluation.is_locked"
                    class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                >
                    Penilaian terkunci. Hubungi staff jika perlu koreksi.
                </p>

                <form v-if="canEdit" class="space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="space-y-2">
                            <Label for="speaking_score">Speaking (1–10)</Label>
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
                            <Label for="technical_score">Technical (1–10)</Label>
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
                            <Label for="attitude_score">Attitude (1–10)</Label>
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

                    <div class="space-y-2">
                        <Label>Recommendation</Label>
                        <Select v-model="form.recommendation">
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih recommendation" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in recommendationOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="notes">Catatan (opsional)</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="4"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Observasi, kelebihan, kekurangan..."
                        />
                    </div>

                    <Button type="submit" :disabled="form.processing">Simpan penilaian</Button>
                </form>

                <div v-else-if="detail.evaluation.speaking_score" class="space-y-2 text-sm">
                    <p>Speaking: {{ detail.evaluation.speaking_score }}/10</p>
                    <p>Technical: {{ detail.evaluation.technical_score }}/10</p>
                    <p>Attitude: {{ detail.evaluation.attitude_score }}/10</p>
                    <p>Recommendation: {{ detail.evaluation.recommendation_label }}</p>
                    <p v-if="detail.evaluation.notes" class="text-muted-foreground whitespace-pre-wrap">
                        {{ detail.evaluation.notes }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
