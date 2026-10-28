<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue';
import { submissionReviewBadge } from '@/lib/formSubmissionsUi';
import { formatDateTime } from '@/lib/dateTime';
import { userAvatarSeed } from '@/lib/userAvatarFallback';

defineProps<{ submissions: IFormSubmission[] }>();
const emit = defineEmits<{ detail: [submission: IFormSubmission] }>();
</script>

<template>
    <ul class="divide-y divide-border/60 overflow-hidden rounded-2xl border border-border/70 bg-card">
        <li v-for="submission in submissions" :key="submission.id">
            <button
                type="button"
                class="flex min-h-[72px] w-full items-center gap-3 px-3 py-3 text-left transition-colors hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
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
                    <span class="block truncate text-sm font-semibold text-foreground">{{ submission.user?.name ?? 'Tanpa nama' }}</span>
                    <span class="mt-1 block truncate text-[11px] text-muted-foreground">{{ formatDateTime({ value: submission.submitted_at, variant: 'date-time-medium' }) }}</span>
                </span>
                <Badge variant="outline" :class="['shrink-0 text-[10px] font-medium', submissionReviewBadge(submission.review_status).class]">
                    {{ submissionReviewBadge(submission.review_status).label }}
                </Badge>
                <ChevronRight class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
            </button>
        </li>
    </ul>
</template>
