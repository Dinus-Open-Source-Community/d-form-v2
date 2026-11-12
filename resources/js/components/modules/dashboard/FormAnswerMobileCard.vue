<script setup lang="ts">
import { computed } from 'vue';
import { Check, Eye, X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';
import FileTypeTile from '@/components/core/FileTypeTile.vue';
import {
    answerPreview,
    formSubmissionReviewIsPending,
    submissionFileUrl,
    submissionReviewBadge,
} from '@/lib/formSubmissionsUi';
import { formatDateTime } from '@/lib/dateTime';
import { userAvatarSeed } from '@/lib/userAvatarFallback';

const props = defineProps<{
    submission: IFormSubmission;
    rowNumber: number;
    selected: boolean;
    answerKeys: string[];
    fileFieldNames: string[];
    humanizeKey: (key: string) => string;
    fileNameOf: (value: string) => string;
    reviewing: boolean;
}>();
const emit = defineEmits<{
    detail: [submission: IFormSubmission];
    review: [payload: { action: 'accept' | 'reject'; submission: IFormSubmission }];
    select: [checked: boolean | 'indeterminate'];
}>();

const displayAnswerKeys = computed(() => [
    ...props.answerKeys.filter((key) => !props.fileFieldNames.includes(key)).slice(0, 4),
    ...props.answerKeys.filter((key) => props.fileFieldNames.includes(key)),
]);

function isInteractiveTarget(target: EventTarget | null): boolean {
    return target instanceof Element && target.closest('button, a, input, [role="checkbox"]') !== null;
}

function openCardFromClick(event: MouseEvent): void {
    if (isInteractiveTarget(event.target)) return;
    emit('detail', props.submission);
}

function openCardFromKeyboard(event: KeyboardEvent): void {
    if (event.target !== event.currentTarget || (event.key !== 'Enter' && event.key !== ' ')) return;
    event.preventDefault();
    emit('detail', props.submission);
}
</script>

<template>
    <article
        data-testid="form-answer-mobile-card"
        tabindex="0"
        :aria-label="`Lihat detail jawaban nomor ${rowNumber} dari ${submission.user?.name ?? 'pengirim'}`"
        class="border-border/70 bg-card focus-visible:ring-ring/70 cursor-pointer rounded-xl border p-4 shadow-xs focus-visible:ring-2 focus-visible:outline-none"
        @click="openCardFromClick"
        @keydown="openCardFromKeyboard"
    >
        <header class="flex min-w-0 items-start gap-3">
            <div class="flex shrink-0 items-center gap-2 pt-1">
                <Checkbox
                    :model-value="selected"
                    :aria-label="`Pilih jawaban nomor ${rowNumber} dari ${submission.user?.name ?? 'pengirim'}`"
                    @update:model-value="emit('select', $event)"
                />
                <span class="text-muted-foreground text-xs tabular-nums">{{ rowNumber }}</span>
            </div>
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
        <dl v-if="answerKeys.length > 0" class="border-border/60 mt-3 space-y-2 border-t pt-3">
            <div v-for="key in displayAnswerKeys" :key="key" class="min-w-0">
                <dt class="text-muted-foreground text-[11px] font-medium">{{ humanizeKey(key) }}</dt>
                <dd
                    v-if="fileFieldNames.includes(key) && submissionFileUrl(submission.answers?.[key])"
                    class="text-foreground/85 flex min-w-0 items-center gap-1.5 text-xs"
                >
                    <FileTypeTile :file-name="fileNameOf(String(submission.answers?.[key] ?? ''))" />
                    <span class="min-w-0 truncate">{{ fileNameOf(String(submission.answers?.[key] ?? '')) }}</span>
                </dd>
                <dd v-else class="text-foreground/85 line-clamp-2 text-xs break-words">
                    {{ answerPreview(submission.answers?.[key]) }}
                </dd>
            </div>
        </dl>
        <footer class="border-border/60 mt-4 flex gap-2 border-t pt-3">
            <template v-if="formSubmissionReviewIsPending(submission)">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="text-success hover:bg-success/10 hover:text-success focus-visible:ring-success/40 rounded-md"
                    :disabled="reviewing"
                    :aria-label="`Terima jawaban dari ${submission.user?.name ?? 'pengirim'}`"
                    @click="emit('review', { action: 'accept', submission })"
                >
                    <Check class="size-4" aria-hidden="true" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive focus-visible:ring-destructive/40 rounded-md"
                    :disabled="reviewing"
                    :aria-label="`Tolak jawaban dari ${submission.user?.name ?? 'pengirim'}`"
                    @click="emit('review', { action: 'reject', submission })"
                >
                    <X class="size-4" aria-hidden="true" />
                </Button>
            </template>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="hover:bg-primary/10 hover:text-primary rounded-md"
                :aria-label="`Lihat detail jawaban dari ${submission.user?.name ?? 'pengirim'}`"
                @click="emit('detail', submission)"
            >
                <Eye class="size-4" aria-hidden="true" />
            </Button>
        </footer>
    </article>
</template>
