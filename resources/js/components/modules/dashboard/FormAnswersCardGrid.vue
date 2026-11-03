<script setup lang="ts">
import { computed } from 'vue';
import FormAnswerMobileCard from '@/components/modules/dashboard/FormAnswerMobileCard.vue';
import { submissionFileName } from '@/lib/formSubmissionsUi';
import type { IFormAnswersColumn } from '@/types/formAnswersView';

const props = defineProps<{
    rows: IFormSubmission[];
    columns: IFormAnswersColumn[];
    reviewing: (submissionId: string) => boolean;
}>();

const emit = defineEmits<{
    detail: [submission: IFormSubmission];
    review: [payload: { action: 'accept' | 'reject'; submission: IFormSubmission }];
}>();

const answerKeys = computed(() => props.columns.map((column) => column.key));
const fileFieldNames = computed(() => props.columns.filter((column) => column.isFile).map((column) => column.key));

function humanizeKey(key: string): string {
    return props.columns.find((column) => column.key === key)?.label ?? key;
}
</script>

<template>
    <div data-testid="form-answers-card-grid" class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
        <FormAnswerMobileCard
            v-for="submission in rows"
            :key="submission.id"
            :submission="submission"
            :answer-keys="answerKeys"
            :file-field-names="fileFieldNames"
            :humanize-key="humanizeKey"
            :file-name-of="submissionFileName"
            :reviewing="reviewing(submission.id)"
            surface="desktop"
            @detail="emit('detail', $event)"
            @review="emit('review', $event)"
        />
    </div>
</template>
