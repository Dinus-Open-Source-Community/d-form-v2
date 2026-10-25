<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { Clock, Inbox } from 'lucide-vue-next'
import { submissionReviewBadge } from '@/lib/formSubmissionsUi'
import { userAvatarSeed } from '@/lib/userAvatarFallback'
import { formatDateTime } from '@/lib/dateTime'
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue'

defineProps<{
    submissions: IFormSubmission[]
}>()

const emit = defineEmits<{
    openDetail: [submission: IFormSubmission]
}>()

function onCardKeydown(e: KeyboardEvent, submission: IFormSubmission) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault()
        emit('openDetail', submission)
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
            class="app-surface animate-app-fade-in flex cursor-pointer flex-col rounded-2xl border border-border/80 p-5 shadow-sm transition-[box-shadow,background-color,border-color] outline-none hover:border-border hover:bg-muted/[0.12] focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            :style="{ animationDelay: `${idx * 100}ms` }"
            :aria-label="`Buka detail pengiriman: ${submission.user?.name ?? 'Tanpa nama'}`"
            @click="emit('openDetail', submission)"
            @keydown="onCardKeydown($event, submission)"
        >
            <div class="flex items-start justify-between gap-3 border-b border-border/70 pb-4">
                <div class="flex min-w-0 flex-1 items-start gap-3.5">
                    <UserAvatarFallback
                        :src="submission.user?.avatar ?? null"
                        :seed="userAvatarSeed(submission.user)"
                        avatar-class="size-12 shrink-0 rounded-2xl border border-border/80 shadow-sm"
                        fallback-round-class="rounded-2xl"
                    />
                    <div class="min-w-0 flex-1 pt-0.5">
                        <h4 class="truncate text-[0.9375rem] font-semibold leading-tight tracking-tight text-foreground">
                            {{ submission.user?.name ?? 'Tanpa nama' }}
                        </h4>
                        <p class="mt-1.5 truncate text-[0.8125rem] leading-snug text-muted-foreground">
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
                    class="flex gap-3 rounded-xl border border-dashed border-border/90 bg-muted/25 px-3.5 py-3 text-[0.8125rem] leading-snug text-muted-foreground"
                >
                    <Inbox class="mt-0.5 size-4 shrink-0 text-muted-foreground/80" aria-hidden="true" />
                    <p>
                        <span class="font-medium text-foreground/90">Ringkasan & lampiran</span>
                        ada di jendela detail. Klik kartu ini atau tekan Enter untuk membuka — Terima/Tolak dari situ bila
                        masih menunggu review.
                    </p>
                </div>

                <div
                    class="mt-4 flex items-start justify-between gap-3 rounded-lg bg-muted/15 px-1 py-2 text-[0.8125rem] tabular-nums text-muted-foreground"
                >
                    <span class="flex items-center gap-2 font-medium text-muted-foreground/90">
                        <Clock class="size-3.5 shrink-0 opacity-70" aria-hidden="true" />
                        Dikirim
                    </span>
                    <time class="text-right font-medium leading-snug text-foreground" :datetime="submission.submitted_at">
                        {{ formatDateTime({ value: submission.submitted_at, variant: 'date-time-medium' }) }}
                    </time>
                </div>
            </div>
        </div>
    </div>
</template>
