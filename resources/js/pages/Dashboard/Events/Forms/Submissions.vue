<script setup lang="ts">
import { reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import { Button } from '@/components/ui/button'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import KpiCard from '@/components/modules/dashboard/KpiCard.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import FormSubmissionsEmptyState from '@/components/modules/dashboard/FormSubmissionsEmptyState.vue'
import FormSubmissionsTableView from '@/components/modules/dashboard/FormSubmissionsTableView.vue'
import FormSubmissionsCardGridView from '@/components/modules/dashboard/FormSubmissionsCardGridView.vue'
import FormSubmissionsPagination from '@/components/modules/dashboard/FormSubmissionsPagination.vue'
import FormSubmissionDetailSheet from '@/components/modules/dashboard/FormSubmissionDetailSheet.vue'
import { LayoutGrid, List, Download, Users, CalendarClock } from 'lucide-vue-next'
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
    s.submissionReviewStub(payload.action, payload.submission)
}
</script>

<template>
    <Head :title="`${form.title} Submissions`" />

    <div class="app-page">
        <div class="flex flex-col gap-8">
            <PageHeader
                :title="form.title"
                :subtitle="`Collected responses for ${event.title}`"
                :back-href="`/dashboard/events/${event.id}/forms/${form.id}`"
            >
                <template #actions>
                    <div class="flex items-center gap-2.5">
                        <Tabs v-model="s.viewType" class="w-auto">
                            <TabsList>
                                <TabsTrigger value="table">
                                    <List class="mr-1.5 size-3.5" />
                                    Table
                                </TabsTrigger>
                                <TabsTrigger value="form">
                                    <LayoutGrid class="mr-1.5 size-3.5" />
                                    Cards
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                        <Button variant="outline" size="sm">
                            <Download class="mr-1.5 size-4" />
                            Export
                        </Button>
                    </div>
                </template>
            </PageHeader>

            <div v-if="submissions.total > 0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <KpiCard label="Total Submissions" :value="submissions.total" :icon="Users" color="primary" />
                <KpiCard label="Latest Response" :value="s.latestSubmissionDate" :icon="CalendarClock" color="warning" />
            </div>

            <FormSubmissionsEmptyState v-if="submissions.data.length === 0" />

            <template v-else>
                <FormSubmissionsTableView
                    v-if="s.viewType === 'table'"
                    :submissions="submissions.data"
                    :answer-keys="s.answerKeys"
                    :format-date="s.formatDate"
                    :humanize-key="s.humanizeKey"
                    :answer-preview="s.answerPreview"
                    :file-url="s.fileUrl"
                    @open-detail="s.openDetail"
                    @review="onReview"
                />

                <FormSubmissionsCardGridView
                    v-else
                    :submissions="submissions.data"
                    :answer-keys="s.answerKeys"
                    :format-date="s.formatDate"
                    :humanize-key="s.humanizeKey"
                    :answer-preview="s.answerPreview"
                    :file-url="s.fileUrl"
                    @open-detail="s.openDetail"
                    @review="onReview"
                />

                <FormSubmissionsPagination :links="submissions.links" :last-page="submissions.last_page" />
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
        @review="onReview"
    />
</template>
