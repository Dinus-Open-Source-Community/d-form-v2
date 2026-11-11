<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';
import { submissionReviewBadge } from '@/lib/formSubmissionsUi';
import { formatDateTime } from '@/lib/dateTime';
import { userAvatarSeed } from '@/lib/userAvatarFallback';

defineProps<{ submissions: IFormSubmission[] }>();
const emit = defineEmits<{ detail: [submission: IFormSubmission] }>();
</script>

<template>
    <ul
        data-testid="form-answer-mobile-list"
        class="divide-border/60 border-border/70 bg-card divide-y overflow-hidden rounded-2xl border"
    >
        <li v-for="submission in submissions" :key="submission.id">
            <button
                type="button"
                class="hover:bg-muted/30 focus-visible:ring-ring flex min-h-[72px] w-full items-center gap-3 px-3 py-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-inset"
                :aria-label="`Lihat detail jawaban dari ${submission.user?.name ?? 'pengirim'}`"
                @click="emit('detail', submission)"
            >
                <UserAvatarFallback
                    :src="submission.user?.avatar ?? null"
                    :seed="userAvatarSeed(submission.user)"
                    avatar-class="size-10 shrink-0 rounded-xl border border-border"
                    fallback-round-class="rounded-xl"
                />
                <span class="min-w-0 flex-1">
                    <span class="text-foreground block truncate text-sm font-semibold">{{
                        submission.user?.name ?? 'Tanpa nama'
                    }}</span>
                    <span class="text-muted-foreground mt-1 block truncate text-[11px]">{{
                        formatDateTime({ value: submission.submitted_at, variant: 'date-time-medium' })
                    }}</span>
                </span>
                <Badge
                    variant="outline"
                    :class="['shrink-0 text-[10px] font-medium', submissionReviewBadge(submission.review_status).class]"
                >
                    {{ submissionReviewBadge(submission.review_status).label }}
                </Badge>
                <ChevronRight class="text-muted-foreground size-4 shrink-0" aria-hidden="true" />
            </button>
        </li>
    </ul>
</template>
