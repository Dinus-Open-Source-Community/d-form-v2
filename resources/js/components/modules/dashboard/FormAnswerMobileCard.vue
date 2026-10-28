<script setup lang="ts">
import { computed } from 'vue';
import { Check, Eye, FileText, X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue';
import { answerPreview, formSubmissionReviewIsPending, submissionFileUrl, submissionReviewBadge } from '@/lib/formSubmissionsUi';
import { formatDateTime } from '@/lib/dateTime';
import { userAvatarSeed } from '@/lib/userAvatarFallback';

const props = defineProps<{
    submission: IFormSubmission;
    answerKeys: string[];
    fileFieldNames: string[];
    humanizeKey: (key: string) => string;
    fileNameOf: (value: string) => string;
    reviewing: boolean;
}>();
const emit = defineEmits<{
    detail: [submission: IFormSubmission];
    review: [payload: { action: 'accept' | 'reject'; submission: IFormSubmission }];
}>();

const displayAnswerKeys = computed(() => [
    ...props.answerKeys.filter((key) => !props.fileFieldNames.includes(key)).slice(0, 4),
    ...props.answerKeys.filter((key) => props.fileFieldNames.includes(key)),
]);

</script>

<template>
    <article data-testid="form-answer-mobile-card" class="rounded-2xl border border-border/70 bg-card p-4 shadow-sm">
        <header class="flex min-w-0 items-start gap-3">
            <UserAvatarFallback
                :src="submission.user?.avatar ?? null"
                :seed="userAvatarSeed(submission.user)"
                avatar-class="size-10 shrink-0 rounded-xl border border-border"
                fallback-round-class="rounded-xl"
            />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-foreground">{{ submission.user?.name ?? 'Tanpa nama' }}</p>
                <p class="truncate text-xs text-muted-foreground">{{ submission.user?.email ?? '—' }}</p>
            </div>
            <Badge variant="outline" :class="['shrink-0 font-medium', submissionReviewBadge(submission.review_status).class]">
                {{ submissionReviewBadge(submission.review_status).label }}
            </Badge>
        </header>
        <p class="mt-3 text-xs text-muted-foreground">
            Dikirim {{ formatDateTime({ value: submission.submitted_at, variant: 'date-time-medium' }) }}
        </p>
        <dl v-if="answerKeys.length > 0" class="mt-3 space-y-2 border-t border-border/60 pt-3">
            <div v-for="key in displayAnswerKeys" :key="key" class="min-w-0">
                <dt class="text-[11px] font-medium text-muted-foreground">{{ humanizeKey(key) }}</dt>
                <dd v-if="fileFieldNames.includes(key) && submissionFileUrl(submission.answers?.[key])" class="flex items-center gap-1.5 text-xs text-foreground/85">
                    <FileText class="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <span class="truncate">{{ fileNameOf(String(submission.answers?.[key] ?? '')) }}</span>
                </dd>
                <dd v-else class="line-clamp-2 break-words text-xs text-foreground/85">
                    {{ answerPreview(submission.answers?.[key]) }}
                </dd>
            </div>
        </dl>
        <footer class="mt-4 flex gap-2 border-t border-border/60 pt-3">
            <template v-if="formSubmissionReviewIsPending(submission)">
                <Button type="button" variant="outline" class="h-10 flex-1 gap-1.5 border-success/35 text-success" :disabled="reviewing" @click="emit('review', { action: 'accept', submission })">
                    <Check class="size-4" aria-hidden="true" /> Terima
                </Button>
                <Button type="button" variant="outline" class="h-10 flex-1 gap-1.5 border-destructive/35 text-destructive" :disabled="reviewing" @click="emit('review', { action: 'reject', submission })">
                    <X class="size-4" aria-hidden="true" /> Tolak
                </Button>
            </template>
            <Button type="button" variant="ghost" class="h-10 gap-1.5" @click="emit('detail', submission)">
                <Eye class="size-4" aria-hidden="true" /> Detail
            </Button>
        </footer>
    </article>
</template>
