<script setup lang="ts">
import { reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import { Button } from '@/components/ui/button'
import KpiCard from '@/components/modules/dashboard/KpiCard.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import FormSubmissionsEmptyState from '@/components/modules/dashboard/FormSubmissionsEmptyState.vue'
import FormSubmissionsCardGridView from '@/components/modules/dashboard/FormSubmissionsCardGridView.vue'
import FormSubmissionsPagination from '@/components/modules/dashboard/FormSubmissionsPagination.vue'
import FormSubmissionDetailSheet from '@/components/modules/dashboard/FormSubmissionDetailSheet.vue'
import { Download, Users, CalendarClock, ListChecks } from 'lucide-vue-next'
import { useFormSubmissionsPage } from '@/utils/composables/useFormSubmissionsPage'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event: { id: string; title: string }
    form: { id: string; title: string }
    fields: IFormField[]
    submissions: {
        data: IFormSubmission[]
        current_page: number
        last_page: number
        per_page: number
        total: number
        links?: { url: string | null; label: string; active: boolean }[]
    }
}>()

const s = reactive(useFormSubmissionsPage(props))

function onReview(payload: { action: 'accept' | 'reject'; submission: IFormSubmission }) {
    s.submitSubmissionReview(payload.action, payload.submission)
}
</script>

<template>
    <Head :title="`Pengiriman — ${form.title}`" />

    <div class="app-page">
        <div class="flex flex-col gap-8">
            <PageHeader
                :title="`Pengiriman formulir`"
                :subtitle="`${event.title} · ${form.title}`"
                :back-href="`/admin/dashboard/events/${event.id}/forms/${form.id}`"
            >
                <template #actions>
                    <Button variant="outline" size="sm" class="h-11 w-full justify-center rounded-xl md:h-9 md:w-auto md:rounded-md">
                        <Download class="mr-1.5 size-4 shrink-0" />
                        Ekspor
                    </Button>
                </template>
            </PageHeader>

            <p v-if="submissions.total > 0" class="text-sm leading-relaxed text-muted-foreground">
                Daftar jawaban yang masuk untuk formulir ini. Setiap kartu menampilkan pengirim, status review, ringkasan isian
                (maks. 4 field), dan aksi. Buka <span class="font-medium text-foreground">Lihat detail</span> untuk semua jawaban
                dan lampiran; gunakan
                <span class="font-medium text-foreground">Terima</span> /
                <span class="font-medium text-foreground">Tolak</span> bila masih menunggu review.
            </p>

            <div v-if="submissions.total > 0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <KpiCard label="Total pengiriman" :value="submissions.total" :icon="Users" color="primary" />
                <KpiCard
                    label="Pengiriman terbaru (halaman ini)"
                    :value="s.latestSubmissionDate"
                    :icon="CalendarClock"
                    color="success"
                />
                <KpiCard
                    label="Menunggu review (halaman ini)"
                    :value="s.pendingReviewCount"
                    :icon="ListChecks"
                    color="warning"
                />
            </div>
            <p v-if="submissions.total > 0 && submissions.last_page > 1" class="-mt-4 text-xs text-muted-foreground">
                Angka &quot;menunggu review&quot; hanya menghitung baris di halaman saat ini. Gunakan navigasi di bawah untuk
                halaman lain.
            </p>

            <FormSubmissionsEmptyState v-if="submissions.data.length === 0" />

            <template v-else>
                <FormSubmissionsCardGridView
                    :submissions="submissions.data"
                    :answer-keys="s.answerKeys"
                    :format-date="s.formatDate"
                    :humanize-key="s.humanizeKey"
                    :answer-preview="s.answerPreview"
                    :file-url="s.fileUrl"
                    :is-submission-reviewing="s.isSubmissionReviewing"
                    @open-detail="s.openDetail"
                    @review="onReview"
                />

                <FormSubmissionsPagination
                    :links="submissions.links"
                    :current-page="submissions.current_page"
                    :last-page="submissions.last_page"
                    :total="submissions.total"
                />
            </template>
        </div>
    </div>

    <FormSubmissionDetailSheet
        v-model:open="s.isDetailOpen"
        :submission="s.selectedSubmission"
        :all-answer-keys="s.allAnswerKeys"
        :format-date="s.formatDate"
        :humanize-key="s.humanizeKey"
        :answer-preview="s.answerPreview"
        :file-url="s.fileUrl"
        :is-submission-reviewing="s.isSubmissionReviewing"
        @review="onReview"
    />
</template>
