<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';
import { submissionReviewBadge } from '@/lib/formSubmissionsUi';
import { duIssuanceBadge } from '@/lib/duSubmissionUi';
import { formatDateTime } from '@/lib/dateTime';
import { userAvatarSeed } from '@/lib/userAvatarFallback';

const props = defineProps<{
    submissions: IFormSubmission[];
    selectedSubmissionIds: ReadonlySet<string>;
    /** Baris bisa dipilih untuk bulk review; D-Kelas mematikan kontrol ini. */
    selectable: boolean;
}>();
const emit = defineEmits<{
    detail: [submission: IFormSubmission];
    select: [submissionId: string, checked: boolean | 'indeterminate'];
}>();

function isInteractiveTarget(target: EventTarget | null): boolean {
    return target instanceof Element && target.closest('button, a, input, [role="checkbox"]') !== null;
}

function openRowFromClick(event: MouseEvent, submission: IFormSubmission): void {
    if (isInteractiveTarget(event.target)) return;
    emit('detail', submission);
}

function openRowFromKeyboard(event: KeyboardEvent, submission: IFormSubmission): void {
    if (event.target !== event.currentTarget || (event.key !== 'Enter' && event.key !== ' ')) return;
    event.preventDefault();
    emit('detail', submission);
}
</script>

<template>
    <ul
        data-testid="form-answer-mobile-list"
        class="divide-border/60 border-border/70 bg-card divide-y overflow-hidden rounded-2xl border"
    >
        <li
            v-for="(submission, index) in submissions"
            :key="submission.id"
            tabindex="0"
            :aria-label="`Lihat detail jawaban nomor ${index + 1} dari ${submission.user?.name ?? 'pengirim'}`"
            class="hover:bg-muted/30 focus-visible:ring-ring relative flex min-h-[72px] items-center gap-2.5 px-3 py-3 text-left transition-colors focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
            @click="openRowFromClick($event, submission)"
            @keydown="openRowFromKeyboard($event, submission)"
        >
            <Checkbox
                v-if="props.selectable"
                :model-value="props.selectedSubmissionIds.has(submission.id)"
                :aria-label="`Pilih jawaban nomor ${index + 1} dari ${submission.user?.name ?? 'pengirim'}`"
                @update:model-value="emit('select', submission.id, $event)"
            />
            <UserAvatarFallback
                :src="submission.user?.avatar ?? null"
                :seed="userAvatarSeed(submission.user)"
                avatar-class="size-9 shrink-0 rounded-xl border border-border"
                fallback-round-class="rounded-xl"
            />
            <!-- Dua baris: badge ikut baris kedua agar nama tidak terpotong di layar sempit. -->
            <span class="flex min-w-0 flex-1 flex-col gap-1">
                <span class="text-foreground block truncate text-sm font-semibold">{{
                    submission.user?.name ?? 'Tanpa nama'
                }}</span>
                <span class="flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-1">
                    <span class="text-muted-foreground text-[11px] whitespace-nowrap">{{
                        formatDateTime({ value: submission.submitted_at, variant: 'date-weekday' })
                    }}</span>
                    <Badge
                        variant="outline"
                        :class="[
                            'shrink-0 px-1.5 text-[10px] font-medium',
                            submissionReviewBadge(submission.review_status).class,
                        ]"
                    >
                        {{ submissionReviewBadge(submission.review_status).label }}
                    </Badge>
                    <span
                        v-if="submission.du?.issuance?.status === 'issued' && submission.du.issuance.code"
                        :title="submission.du.issuance.code"
                        class="border-border/70 bg-muted/50 text-foreground max-w-[8rem] shrink truncate rounded border px-1 font-mono text-[10px] font-semibold"
                    >
                        {{ submission.du.issuance.code }}
                    </span>
                    <Badge
                        v-if="submission.du && duIssuanceBadge(submission.du)"
                        variant="outline"
                        :class="['shrink-0 px-1.5 text-[10px] font-medium', duIssuanceBadge(submission.du)?.className]"
                    >
                        {{ duIssuanceBadge(submission.du)?.label }}
                    </Badge>
                </span>
            </span>
            <ChevronRight class="text-muted-foreground size-4 shrink-0" aria-hidden="true" />
        </li>
    </ul>
</template>
