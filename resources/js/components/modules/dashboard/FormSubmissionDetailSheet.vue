<script setup lang="ts">
import { computed } from 'vue';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Clock, CheckCircle2, XCircle, Info } from 'lucide-vue-next';
import {
    formSubmissionReviewIsPending,
    submissionAdminAcceptBlocked,
    submissionReviewBadge,
} from '@/lib/formSubmissionsUi';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import { formatDateTime } from '@/lib/dateTime';
import { cn } from '@/lib/utils';
import FormFieldAnswerDisplay from '@/components/modules/dashboard/FormFieldAnswerDisplay.vue';
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue';

const props = defineProps<{
    submission: IFormSubmission | IBundleSubmissionMember | null;
    allAnswerKeys: string[];
    fields: IFormField[];
    humanizeKey: (v: string) => string;
    isSubmissionReviewing: (submissionId: string) => boolean;
}>();

function fieldForKey(key: string): IFormField | null {
    return props.fields.find((f) => f.name === key) ?? null;
}

/** Urutan field mengikuti builder; key ekstra (legacy) di akhir. */
const orderedAnswerKeys = computed(() => {
    const keys = [...(props.allAnswerKeys ?? [])];
    const orderMap = new Map<string, number>();
    (props.fields ?? []).forEach((f) => {
        orderMap.set(f.name, f.order);
    });
    return keys.sort((a, b) => {
        const hasA = orderMap.has(a);
        const hasB = orderMap.has(b);
        if (hasA && hasB) {
            return (orderMap.get(a) ?? 0) - (orderMap.get(b) ?? 0);
        }
        if (hasA) {
            return -1;
        }
        if (hasB) {
            return 1;
        }
        return a.localeCompare(b);
    });
});

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    review: [payload: { action: 'accept' | 'reject'; submission: IFormSubmission | IBundleSubmissionMember }];
}>();

const canReview = computed(() => {
    if (!props.submission) return false;

    // Check if this is a bundle member with can_review flag
    if ('can_review' in props.submission) {
        return props.submission.can_review;
    }

    // Default: can review if not blocked
    return !submissionAdminAcceptBlocked(props.submission);
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            :class="
                cn(
                    '!flex flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl',
                    'max-md:top-0 max-md:right-0 max-md:bottom-0 max-md:left-0 max-md:h-[100dvh] max-md:max-h-[100dvh] max-md:w-full max-md:max-w-none max-md:translate-x-0 max-md:translate-y-0 max-md:rounded-none max-md:border-0',
                    'md:top-[50%] md:left-[50%] md:h-auto md:max-h-[min(90vh,56rem)] md:w-full md:translate-x-[-50%] md:translate-y-[-50%] md:rounded-xl md:border'
                )
            "
        >
            <div
                class="border-border/80 from-muted/25 via-muted/[0.07] to-background shrink-0 border-b bg-gradient-to-b px-5 pt-6 pr-14 pb-6 md:px-7 md:pb-7"
            >
                <div class="flex items-start gap-4 md:gap-5">
                    <UserAvatarFallback
                        :src="submission?.user?.avatar ?? null"
                        :seed="userAvatarSeed(submission?.user ?? null)"
                        avatar-class="size-14 shrink-0 rounded-2xl border border-border/80 bg-muted/30 shadow-[0_1px_2px_rgba(0,0,0,0.04)] ring-1 ring-border/40 md:size-16"
                        fallback-round-class="rounded-2xl"
                    />

                    <div class="min-w-0 flex-1 space-y-2.5">
                        <p class="text-muted-foreground text-[0.6875rem] font-medium md:text-xs">Detail pengiriman</p>

                        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
                            <h2
                                class="text-foreground max-w-[min(100%,28rem)] min-w-0 text-[1.25rem] leading-[1.2] font-semibold tracking-[-0.02em] sm:text-[1.375rem] md:text-2xl"
                            >
                                {{ submission?.user?.name ?? 'Tanpa nama' }}
                            </h2>
                            <Badge
                                v-if="submission"
                                variant="outline"
                                class="shrink-0 border px-3 py-1 text-[11px] leading-none font-semibold md:text-xs"
                                :class="submissionReviewBadge(submission.review_status).class"
                            >
                                {{ submissionReviewBadge(submission.review_status).label }}
                            </Badge>
                        </div>

                        <p
                            class="text-muted-foreground max-w-prose text-[0.8125rem] leading-relaxed break-all md:text-sm"
                        >
                            {{ submission?.user?.email ?? 'Email tidak tercatat' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="min-h-0 flex-1 space-y-10 overflow-y-auto px-5 py-7 pb-28 md:px-7 md:pb-24">
                <section class="space-y-4">
                    <div>
                        <h3 class="text-foreground text-[0.9375rem] leading-snug font-semibold">Informasi peserta</h3>
                        <p class="text-muted-foreground mt-1 max-w-prose text-[0.8125rem] leading-relaxed">
                            Identitas akun yang mengirim formulir.
                        </p>
                    </div>
                    <div
                        class="border-border/65 bg-muted/[0.08] grid gap-x-6 gap-y-5 rounded-2xl border p-5 md:grid-cols-2 md:p-5"
                    >
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <span class="text-muted-foreground text-[11px] font-medium">Email</span>
                            <span class="text-foreground text-[0.875rem] leading-relaxed font-medium break-all">{{
                                submission?.user?.email ?? '—'
                            }}</span>
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5 sm:col-span-2 md:col-span-1">
                            <span class="text-muted-foreground text-[11px] font-medium">Dikirim</span>
                            <div
                                class="text-foreground flex items-center gap-2 text-[0.875rem] leading-relaxed font-medium tabular-nums"
                            >
                                <Clock class="text-muted-foreground size-4 shrink-0 opacity-80" aria-hidden="true" />
                                <span>{{
                                    submission
                                        ? formatDateTime({
                                              value: submission.submitted_at,
                                              variant: 'date-time-medium',
                                          })
                                        : '—'
                                }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <h3 class="text-foreground text-[0.9375rem] leading-snug font-semibold">Isian formulir</h3>
                    </div>
                    <div class="grid grid-cols-1 gap-3.5">
                        <div
                            v-for="key in orderedAnswerKeys"
                            :key="key"
                            class="border-border/70 bg-card/50 rounded-2xl border p-5 shadow-[0_1px_0_rgba(0,0,0,0.03)]"
                        >
                            <p
                                class="border-border/60 text-foreground mb-4 border-b pb-3 text-[0.875rem] leading-snug font-semibold"
                            >
                                {{ humanizeKey(key) }}
                            </p>
                            <FormFieldAnswerDisplay :field="fieldForKey(key)" :value="submission?.answers?.[key]" />
                        </div>
                    </div>
                </section>

                <section v-if="submission" class="border-border/60 space-y-5 border-t pt-8">
                    <div>
                        <h3 class="text-foreground text-base leading-snug font-semibold tracking-tight">
                            Keputusan peninjauan
                        </h3>
                    </div>

                    <template v-if="formSubmissionReviewIsPending(submission)">
                        <div
                            v-if="!canReview && 'locked_reason' in submission && submission.locked_reason"
                            class="border-border/70 bg-card/45 overflow-hidden rounded-2xl border shadow-[0_1px_0_rgba(0,0,0,0.03)]"
                        >
                            <div class="space-y-5 p-4 md:p-6">
                                <div
                                    class="border-border/80 bg-muted/30 flex gap-3 rounded-xl border border-dashed px-3.5 py-3"
                                >
                                    <Info class="text-muted-foreground mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                    <div class="space-y-1.5">
                                        <p class="text-foreground text-[0.8125rem] leading-relaxed font-medium">
                                            Tidak dapat direview
                                        </p>
                                        <p class="text-muted-foreground text-[0.8125rem] leading-relaxed">
                                            {{ submission.locked_reason }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="border-border/70 bg-card/45 overflow-hidden rounded-2xl border shadow-[0_1px_0_rgba(0,0,0,0.03)]"
                        >
                            <div
                                class="border-border/60 bg-warning/[0.07] flex items-center gap-3 border-b px-4 py-3.5 md:px-5"
                            >
                                <span class="relative flex size-2.5 shrink-0" aria-hidden="true">
                                    <span
                                        class="bg-warning/40 absolute inline-flex size-full animate-ping rounded-full opacity-60"
                                    />
                                    <span class="bg-warning relative inline-flex size-2.5 rounded-full" />
                                </span>
                                <p class="text-foreground text-[0.8125rem] leading-snug font-medium">
                                    Menunggu keputusan Anda
                                </p>
                            </div>
                            <div class="space-y-5 p-4 md:p-6">
                                <p class="text-muted-foreground text-[0.8125rem] leading-[1.65] md:text-sm">
                                    <span class="text-foreground font-medium">Terima</span> jika pengajuan memenuhi
                                    syarat acara. <span class="text-foreground font-medium">Tolak</span> jika tidak
                                    dapat dilanjutkan. Tindakan ini memperbarui status review secara permanen untuk
                                    entri ini.
                                </p>
                                <div class="flex flex-col gap-3 sm:flex-row sm:gap-3">
                                    <Button
                                        type="button"
                                        class="border-success/35 text-success hover:bg-success/10 h-11 min-h-11 flex-1 gap-2 text-[0.8125rem] font-medium"
                                        variant="outline"
                                        :disabled="isSubmissionReviewing(submission.id) || !canReview"
                                        @click="emit('review', { action: 'accept', submission })"
                                    >
                                        <CheckCircle2 class="size-4 shrink-0" />
                                        Terima pengajuan
                                    </Button>
                                    <Button
                                        type="button"
                                        class="border-destructive/35 text-destructive hover:bg-destructive/10 h-11 min-h-11 flex-1 gap-2 text-[0.8125rem] font-medium"
                                        variant="outline"
                                        :disabled="isSubmissionReviewing(submission.id) || !canReview"
                                        @click="emit('review', { action: 'reject', submission })"
                                    >
                                        <XCircle class="size-4 shrink-0" />
                                        Tolak pengajuan
                                    </Button>
                                </div>
                                <div
                                    v-if="submissionAdminAcceptBlocked(submission) && canReview"
                                    class="border-border/80 bg-muted/30 flex gap-3 rounded-xl border border-dashed px-3.5 py-3"
                                >
                                    <Info class="text-primary mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                    <p class="text-muted-foreground text-[0.8125rem] leading-relaxed">
                                        Untuk pendaftaran tim atau bundel: setiap anggota harus
                                        <span class="text-foreground font-medium">menerima undangan</span> terlebih
                                        dahulu. Setelah semua mengonfirmasi, tombol terima akan dapat digunakan.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div
                        v-else
                        class="border-border/70 bg-card/45 overflow-hidden rounded-2xl border shadow-[0_1px_0_rgba(0,0,0,0.03)]"
                        :class="
                            submission.review_status === 'accepted'
                                ? 'border-l-[3px] border-l-emerald-600/85'
                                : 'border-l-destructive/85 border-l-[3px]'
                        "
                    >
                        <div class="space-y-5 p-4 md:p-6">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-muted-foreground text-[11px] font-medium">Status akhir</p>
                                <Badge
                                    variant="outline"
                                    class="px-3 py-1 text-[11px] font-semibold"
                                    :class="submissionReviewBadge(submission.review_status).class"
                                >
                                    {{ submissionReviewBadge(submission.review_status).label }}
                                </Badge>
                            </div>

                            <dl
                                v-if="submission.reviewed_at || submission.reviewer"
                                class="grid gap-5 sm:grid-cols-2 sm:gap-x-8"
                            >
                                <div v-if="submission.reviewed_at" class="space-y-1">
                                    <dt class="text-muted-foreground text-[11px] font-medium">Waktu keputusan</dt>
                                    <dd class="text-foreground text-sm leading-snug font-medium tabular-nums">
                                        {{
                                            formatDateTime({
                                                value: submission.reviewed_at,
                                                variant: 'date-time-medium',
                                            })
                                        }}
                                    </dd>
                                </div>
                                <div v-if="submission.reviewer" class="space-y-1">
                                    <dt class="text-muted-foreground text-[11px] font-medium">Peninjau</dt>
                                    <dd class="text-foreground text-sm leading-snug font-medium break-words">
                                        {{ submission.reviewer.name }}
                                    </dd>
                                </div>
                            </dl>
                            <p v-else class="text-muted-foreground text-sm leading-relaxed">
                                <template v-if="submission.review_status === 'accepted'">
                                    Pengajuan ini telah ditandai diterima.
                                </template>
                                <template v-else-if="submission.review_status === 'rejected'">
                                    Pengajuan ini telah ditandai ditolak.
                                </template>
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </DialogContent>
    </Dialog>
</template>
