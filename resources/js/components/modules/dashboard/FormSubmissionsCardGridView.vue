<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Clock, Inbox } from 'lucide-vue-next';
import { submissionReviewBadge } from '@/lib/formSubmissionsUi';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import { formatDateTime } from '@/lib/dateTime';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';

defineProps<{
    submissions: IFormSubmission[];
}>();

const emit = defineEmits<{
    openDetail: [submission: IFormSubmission];
}>();

function onCardKeydown(e: KeyboardEvent, submission: IFormSubmission) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        emit('openDetail', submission);
    }
}
</script>

<template>
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <div
            v-for="(submission, idx) in submissions"
            :key="submission.id"
            role="button"
            tabindex="0"
            class="app-surface animate-app-fade-in border-border/80 hover:border-border hover:bg-muted/[0.12] focus-visible:ring-ring focus-visible:ring-offset-background flex cursor-pointer flex-col rounded-2xl border p-5 shadow-sm transition-[box-shadow,background-color,border-color] outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
            :style="{ animationDelay: `${idx * 100}ms` }"
            :aria-label="`Buka detail pengiriman: ${submission.user?.name ?? 'Tanpa nama'}`"
            @click="emit('openDetail', submission)"
            @keydown="onCardKeydown($event, submission)"
        >
            <div class="border-border/70 flex items-start justify-between gap-3 border-b pb-4">
                <div class="flex min-w-0 flex-1 items-start gap-3.5">
                    <UserAvatarFallback
                        :src="submission.user?.avatar ?? null"
                        :seed="userAvatarSeed(submission.user)"
                        avatar-class="size-12 shrink-0 rounded-2xl border border-border/80 shadow-sm"
                        fallback-round-class="rounded-2xl"
                    />
                    <div class="min-w-0 flex-1 pt-0.5">
                        <h4
                            class="text-foreground truncate text-[0.9375rem] leading-tight font-semibold tracking-tight"
                        >
                            {{ submission.user?.name ?? 'Tanpa nama' }}
                        </h4>
                        <p class="text-muted-foreground mt-1.5 truncate text-[0.8125rem] leading-snug">
                            {{ submission.user?.email ?? 'Tidak ada email' }}
                        </p>
                    </div>
                </div>
                <Badge
                    variant="outline"
                    class="shrink-0 px-2.5 py-0.5 text-[0.6875rem] font-medium"
                    :class="submissionReviewBadge(submission.review_status).class"
                >
                    {{ submissionReviewBadge(submission.review_status).label }}
                </Badge>
            </div>

            <div class="mt-4 flex flex-1 flex-col">
                <div
                    class="border-border/90 bg-muted/25 text-muted-foreground flex gap-3 rounded-xl border border-dashed px-3.5 py-3 text-[0.8125rem] leading-snug"
                >
                    <Inbox class="text-muted-foreground/80 mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>
                        <span class="text-foreground/90 font-medium">Ringkasan & lampiran</span>
                        ada di jendela detail. Klik kartu ini atau tekan Enter untuk membuka — Terima/Tolak dari situ
                        bila masih menunggu review.
                    </p>
                </div>

                <div
                    class="bg-muted/15 text-muted-foreground mt-4 flex items-start justify-between gap-3 rounded-lg px-1 py-2 text-[0.8125rem] tabular-nums"
                >
                    <span class="text-muted-foreground/90 flex items-center gap-2 font-medium">
                        <Clock class="size-3.5 shrink-0 opacity-70" aria-hidden="true" />
                        Dikirim
                    </span>
                    <time
                        class="text-foreground text-right leading-snug font-medium"
                        :datetime="submission.submitted_at"
                    >
                        {{ formatDateTime({ value: submission.submitted_at, variant: 'date-time-medium' }) }}
                    </time>
                </div>
            </div>
        </div>
    </div>
</template>
