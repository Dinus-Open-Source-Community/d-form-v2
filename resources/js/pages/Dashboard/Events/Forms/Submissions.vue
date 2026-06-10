<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import { Button } from '@/components/ui/button';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import FormSubmissionsEmptyState from '@/components/modules/dashboard/FormSubmissionsEmptyState.vue';
import FormSubmissionsCardGridView from '@/components/modules/dashboard/FormSubmissionsCardGridView.vue';
import FormBundleGroupsCardGridView from '@/components/modules/dashboard/FormBundleGroupsCardGridView.vue';
import FormBundleGroupDetailSheet from '@/components/modules/dashboard/FormBundleGroupDetailSheet.vue';
import FormSubmissionsPagination from '@/components/modules/dashboard/FormSubmissionsPagination.vue';
import FormSubmissionDetailSheet from '@/components/modules/dashboard/FormSubmissionDetailSheet.vue';
import { Download, ListFilter } from 'lucide-vue-next';
import { useFormSubmissionsPage } from '@/utils/composables/useFormSubmissionsPage';
import { routes } from '@/lib/routes';

defineOptions({ layout: DashboardFocusLayout });

const props = defineProps<{
    event: { id: string; title: string };
    form: { id: string; title: string; registration_mode?: 'single' | 'bundle' | 'team' };
    fields?: IFormField[];
    submissions?: {
        data?: IFormSubmission[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links?: { url: string | null; label: string; active: boolean }[];
    };
    bundleGroups?: {
        data?: IBundleSubmissionGroup[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links?: { url: string | null; label: string; active: boolean }[];
    };
    exportSubmissionsCsvUrl?: string;
}>();

const {
    selectedSubmission,
    isDetailOpen,
    selectedGroup,
    isGroupDetailOpen,
    allAnswerKeys,
    formatDate,
    humanizeKey,
    openDetail,
    openGroupDetail,
    isSubmissionReviewing,
    submitSubmissionReview,
} = useFormSubmissionsPage(props);

const isBundleMode = computed(() => props.form.registration_mode === 'bundle');
const bundleGroupList = computed(() => props.bundleGroups?.data ?? []);
const submissionList = computed(() => props.submissions?.data ?? []);
const formFields = computed(() => props.fields ?? []);

function onReview(payload: { action: 'accept' | 'reject'; submission: IFormSubmission | IBundleSubmissionMember }) {
    submitSubmissionReview(payload.action, payload.submission);
}
</script>

<template>
    <Head :title="`Pengiriman — ${form.title}`" />

    <div class="app-page">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 lg:gap-8">
            <PageHeader
                :title="form.title"
                :subtitle="event.title"
                :back-href="routes.admin.events.forms.show(event.id, form.id)"
            >
                <template #actions>
                    <Button
                        v-if="exportSubmissionsCsvUrl"
                        variant="outline"
                        size="sm"
                        class="h-10 w-full gap-2 rounded-xl md:w-auto"
                        as-child
                    >
                        <a :href="exportSubmissionsCsvUrl" class="inline-flex items-center justify-center" download>
                            <Download class="size-4 shrink-0 opacity-90" aria-hidden="true" />
                            Ekspor CSV
                        </a>
                    </Button>
                </template>
            </PageHeader>

            <!-- Registration Mode: Bundle -->
            <template v-if="isBundleMode">
                <FormSubmissionsEmptyState v-if="bundleGroupList.length === 0" />

                <template v-else>
                    <div class="flex flex-col gap-6">
                        <FormBundleGroupsCardGridView
                            :bundle-groups="bundleGroupList"
                            :format-date="formatDate"
                            @open-group="openGroupDetail"
                            @open-member-detail="openDetail"
                        />

                        <FormSubmissionsPagination
                            v-if="bundleGroups"
                            :links="bundleGroups.links"
                            :current-page="bundleGroups.current_page"
                            :last-page="bundleGroups.last_page"
                            :total="bundleGroups.total"
                            total-label="group"
                        />
                    </div>
                </template>
            </template>

            <!-- Registration Mode: Single / Team -->
            <template v-else-if="submissions">
                <FormSubmissionsEmptyState v-if="submissionList.length === 0" />

                <template v-else>
                    <div class="flex flex-col gap-6">
                        <div class="flex items-center gap-2.5">
                            <div class="bg-primary/10 text-primary grid size-9 place-items-center rounded-xl">
                                <ListFilter class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-foreground text-base font-semibold">Daftar pengiriman</h2>
                                <p class="text-muted-foreground text-xs">
                                    Total {{ submissions.total }} pengiriman masuk.
                                </p>
                            </div>
                        </div>

                        <FormSubmissionsCardGridView
                            :submissions="submissionList"
                            :format-date="formatDate"
                            @open-detail="openDetail"
                        />

                        <FormSubmissionsPagination
                            :links="submissions.links"
                            :current-page="submissions.current_page"
                            :last-page="submissions.last_page"
                            :total="submissions.total"
                        />
                    </div>
                </template>
            </template>
        </div>
    </div>

    <FormBundleGroupDetailSheet
        v-if="!isDetailOpen"
        v-model:open="isGroupDetailOpen"
        :group="selectedGroup"
        :format-date="formatDate"
        @open-detail="openDetail"
    />

    <FormSubmissionDetailSheet
        v-if="!isGroupDetailOpen"
        v-model:open="isDetailOpen"
        :submission="selectedSubmission"
        :all-answer-keys="allAnswerKeys"
        :fields="formFields"
        :format-date="formatDate"
        :humanize-key="humanizeKey"
        :is-submission-reviewing="isSubmissionReviewing"
        @review="onReview"
    />
</template>
