<script setup lang="ts">
import { computed } from 'vue';
import { Check, Eye, FileText, X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue';
import {
    answerPreview,
    formSubmissionReviewIsPending,
    submissionFileUrl,
    submissionReviewBadge,
} from '@/lib/formSubmissionsUi';
import { formatDateTime } from '@/lib/dateTime';
import { userAvatarSeed } from '@/lib/userAvatarFallback';

const props = withDefaults(
    defineProps<{
        submission: IFormSubmission;
        answerKeys: string[];
        fileFieldNames: string[];
        humanizeKey: (key: string) => string;
        fileNameOf: (value: unknown) => string;
        reviewing: boolean;
        surface?: 'mobile' | 'desktop';
    }>(),
    { surface: 'mobile' }
);
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
    <article data-testid="form-answer-mobile-card" class="border-border/70 bg-card rounded-2xl border p-4 shadow-sm">
        <header class="flex min-w-0 items-start gap-3">
            <UserAvatarFallback
                :src="submission.user?.avatar ?? null"
                :seed="userAvatarSeed(submission.user)"
                avatar-class="size-10 shrink-0 rounded-xl border border-border"
                fallback-round-class="rounded-xl"
            />
            <div class="min-w-0 flex-1">
                <p class="text-foreground truncate text-sm font-semibold">
                    {{ submission.user?.name ?? 'Tanpa nama' }}
                </p>
                <p class="text-muted-foreground truncate text-xs">{{ submission.user?.email ?? '—' }}</p>
            </div>
            <Badge
                variant="outline"
                :class="['shrink-0 font-medium', submissionReviewBadge(submission.review_status).class]"
            >
                {{ submissionReviewBadge(submission.review_status).label }}
            </Badge>
        </header>
        <p class="text-muted-foreground mt-3 text-xs">
            Dikirim {{ formatDateTime({ value: submission.submitted_at, variant: 'date-time-medium' }) }}
        </p>
        <dl
            v-if="answerKeys.length > 0"
            :class="props.surface === 'desktop' ? 'mt-3 space-y-2' : 'border-border/60 mt-3 space-y-2 border-t pt-3'"
        >
            <div
                v-for="key in displayAnswerKeys"
                :key="key"
                :class="props.surface === 'desktop' ? 'bg-muted/40 min-w-0 rounded-lg px-3 py-2' : 'min-w-0'"
            >
                <dt class="text-muted-foreground text-[11px] font-medium">{{ humanizeKey(key) }}</dt>
                <dd
                    v-if="fileFieldNames.includes(key) && submissionFileUrl(submission.answers?.[key])"
                    class="text-foreground/85 flex items-center gap-1.5 text-xs"
                >
                    <FileText class="text-muted-foreground size-3.5 shrink-0" aria-hidden="true" />
                    <span class="truncate">{{ fileNameOf(submission.answers?.[key]) }}</span>
                </dd>
                <dd v-else class="text-foreground/85 line-clamp-2 text-xs break-words">
                    {{ answerPreview(submission.answers?.[key]) }}
                </dd>
            </div>
        </dl>
        <footer
            :class="
                props.surface === 'desktop'
                    ? 'border-border/60 mt-4 flex items-center justify-between gap-2 border-t pt-3'
                    : 'border-border/60 mt-4 flex gap-2 border-t pt-3'
            "
        >
            <template v-if="props.surface === 'desktop'">
                <Button
                    type="button"
                    variant="ghost"
                    class="text-primary h-10 gap-1.5 px-1.5 text-xs"
                    @click="emit('detail', submission)"
                >
                    <Eye class="size-4" aria-hidden="true" /> Lihat detail
                </Button>
                <div class="flex items-center gap-1">
                    <Button
                        v-if="formSubmissionReviewIsPending(submission)"
                        type="button"
                        variant="ghost"
                        radius="icon"
                        size="icon-sm"
                        class="text-success hover:bg-success/10 hover:text-success"
                        :aria-label="`Terima jawaban dari ${submission.user?.name ?? 'pengirim'}`"
                        :disabled="reviewing"
                        @click="emit('review', { action: 'accept', submission })"
                    >
                        <Check class="size-4" aria-hidden="true" />
                    </Button>
                    <Button
                        v-if="formSubmissionReviewIsPending(submission)"
                        type="button"
                        variant="ghost"
                        radius="icon"
                        size="icon-sm"
                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                        :aria-label="`Tolak jawaban dari ${submission.user?.name ?? 'pengirim'}`"
                        :disabled="reviewing"
                        @click="emit('review', { action: 'reject', submission })"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        radius="icon"
                        size="icon-sm"
                        class="hover:bg-primary/10 hover:text-primary"
                        :aria-label="`Lihat detail jawaban dari ${submission.user?.name ?? 'pengirim'}`"
                        @click="emit('detail', submission)"
                    >
                        <Eye class="size-4" aria-hidden="true" />
                    </Button>
                </div>
            </template>
            <template v-else-if="props.surface === 'mobile' && formSubmissionReviewIsPending(submission)">
                <Button
                    type="button"
                    variant="outline"
                    class="border-success/35 text-success h-10 flex-1 gap-1.5"
                    :disabled="reviewing"
                    @click="emit('review', { action: 'accept', submission })"
                >
                    <Check class="size-4" aria-hidden="true" /> Terima
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-destructive/35 text-destructive h-10 flex-1 gap-1.5"
                    :disabled="reviewing"
                    @click="emit('review', { action: 'reject', submission })"
                >
                    <X class="size-4" aria-hidden="true" /> Tolak
                </Button>
            </template>
            <Button
                v-if="props.surface === 'mobile'"
                type="button"
                variant="ghost"
                class="h-10 gap-1.5"
                @click="emit('detail', submission)"
            >
                <Eye class="size-4" aria-hidden="true" /> Detail
            </Button>
        </footer>
    </article>
</template>
