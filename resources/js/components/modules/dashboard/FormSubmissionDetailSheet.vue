<script setup lang="ts">
import { computed } from 'vue'
import { Dialog, DialogContent } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Clock, CheckCircle2, XCircle, Info } from 'lucide-vue-next'
import { formSubmissionReviewIsPending, submissionAdminAcceptBlocked, submissionReviewBadge } from '@/lib/formSubmissionsUi'
import { userAvatarSeed } from '@/lib/userAvatarFallback'
import { cn } from '@/lib/utils'
import FormFieldAnswerDisplay from '@/components/modules/dashboard/FormFieldAnswerDisplay.vue'
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue'

const props = defineProps<{
    submission: IFormSubmission | IBundleSubmissionMember | null
    allAnswerKeys: string[]
    fields: IFormField[]
    formatDate: (v: string) => string
    humanizeKey: (v: string) => string
    isSubmissionReviewing: (submissionId: string) => boolean
}>()

function fieldForKey(key: string): IFormField | null {
    return props.fields.find((f) => f.name === key) ?? null
}

/** Urutan field mengikuti builder; key ekstra (legacy) di akhir. */
const orderedAnswerKeys = computed(() => {
    const keys = [...(props.allAnswerKeys ?? [])]
    const orderMap = new Map<string, number>()
    ;(props.fields ?? []).forEach((f) => {
        orderMap.set(f.name, f.order)
    })
    return keys.sort((a, b) => {
        const hasA = orderMap.has(a)
        const hasB = orderMap.has(b)
        if (hasA && hasB) {
            return orderMap.get(a)! - orderMap.get(b)!
        }
        if (hasA) {
            return -1
        }
        if (hasB) {
            return 1
        }
        return a.localeCompare(b)
    })
})

const open = defineModel<boolean>('open', { required: true })

const emit = defineEmits<{
    review: [payload: { action: 'accept' | 'reject'; submission: IFormSubmission | IBundleSubmissionMember }]
}>()

const canReview = computed(() => {
    if (!props.submission) return false
    
    // Check if this is a bundle member with can_review flag
    if ('can_review' in props.submission) {
        return props.submission.can_review
    }
    
    // Default: can review if not blocked
    return !submissionAdminAcceptBlocked(props.submission)
})
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            :class="
                cn(
                    '!flex flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl',
                    'max-md:top-0 max-md:right-0 max-md:bottom-0 max-md:left-0 max-md:h-[100dvh] max-md:max-h-[100dvh] max-md:w-full max-md:max-w-none max-md:translate-x-0 max-md:translate-y-0 max-md:rounded-none max-md:border-0',
                    'md:top-[50%] md:left-[50%] md:h-auto md:max-h-[min(90vh,56rem)] md:w-full md:translate-x-[-50%] md:translate-y-[-50%] md:rounded-xl md:border',
                )
            "
        >
            <div
                class="shrink-0 border-b border-border/80 bg-gradient-to-b from-muted/25 via-muted/[0.07] to-background px-5 pb-6 pt-6 pr-14 md:px-7 md:pb-7"
            >
                <div class="flex items-start gap-4 md:gap-5">
                    <UserAvatarFallback
                        :src="submission?.user?.avatar ?? null"
                        :seed="userAvatarSeed(submission?.user ?? null)"
                        avatar-class="size-14 shrink-0 rounded-2xl border border-border/80 bg-muted/30 shadow-[0_1px_2px_rgba(0,0,0,0.04)] ring-1 ring-border/40 md:size-16"
                        fallback-round-class="rounded-2xl"
                    />

                    <div class="min-w-0 flex-1 space-y-2.5">
                        <p class="text-[0.6875rem] font-medium text-muted-foreground md:text-xs">
                            Detail pengiriman
                        </p>

                        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
                            <h2
                                class="min-w-0 max-w-[min(100%,28rem)] text-[1.25rem] font-semibold leading-[1.2] tracking-[-0.02em] text-foreground sm:text-[1.375rem] md:text-2xl"
                            >
                                {{ submission?.user?.name ?? 'Tanpa nama' }}
                            </h2>
                            <Badge
                                v-if="submission"
                                variant="outline"
                                class="shrink-0 rounded-full border px-3 py-1 text-[11px] font-semibold leading-none md:text-xs"
                                :class="submissionReviewBadge(submission.review_status).class"
                            >
                                {{ submissionReviewBadge(submission.review_status).label }}
                            </Badge>
                        </div>

                        <p class="max-w-prose break-all text-[0.8125rem] leading-relaxed text-muted-foreground md:text-sm">
                            {{ submission?.user?.email ?? 'Email tidak tercatat' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="min-h-0 flex-1 space-y-10 overflow-y-auto px-5 py-7 pb-28 md:px-7 md:pb-24">
                <section class="space-y-4">
                    <div>
                        <h3 class="text-[0.9375rem] font-semibold leading-snug text-foreground">Informasi peserta</h3>
                        <p class="mt-1 max-w-prose text-[0.8125rem] leading-relaxed text-muted-foreground">
                            Identitas akun yang mengirim formulir.
                        </p>
                    </div>
                    <div
                        class="grid gap-x-6 gap-y-5 rounded-2xl border border-border/65 bg-muted/[0.08] p-5 md:grid-cols-2  md:p-5"
                    >
                       
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <span class="text-[11px] font-medium text-muted-foreground">Email</span>
                            <span class="break-all text-[0.875rem] font-medium leading-relaxed text-foreground">{{
                                submission?.user?.email ?? '—'
                            }}</span>
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5 sm:col-span-2 md:col-span-1">
                            <span class="text-[11px] font-medium text-muted-foreground">Dikirim</span>
                            <div class="flex items-center gap-2 text-[0.875rem] font-medium tabular-nums leading-relaxed text-foreground">
                                <Clock class="size-4 shrink-0 text-muted-foreground opacity-80" aria-hidden="true" />
                                <span>{{ submission ? formatDate(submission.submitted_at) : '—' }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <h3 class="text-[0.9375rem] font-semibold leading-snug text-foreground">Isian formulir</h3>
                    </div>
                    <div class="grid grid-cols-1 gap-3.5">
                        <div
                            v-for="key in orderedAnswerKeys"
                            :key="key"
                            class="rounded-2xl border border-border/70 bg-card/50 p-5 shadow-[0_1px_0_rgba(0,0,0,0.03)]"
                        >
                            <p class="mb-4 border-b border-border/60 pb-3 text-[0.875rem] font-semibold leading-snug text-foreground">
                                {{ humanizeKey(key) }}
                            </p>
                            <FormFieldAnswerDisplay
                                :field="fieldForKey(key)"
                                :value="submission?.answers?.[key]"
                            />
                        </div>
                    </div>
                </section>

                <section v-if="submission" class="space-y-5 border-t border-border/60 pt-8">
                    <div>
                        <h3 class="text-base font-semibold leading-snug tracking-tight text-foreground">
                            Keputusan peninjauan
                        </h3>
                        
                    </div>

                    <template v-if="formSubmissionReviewIsPending(submission)">
                        <div
                            v-if="!canReview && 'locked_reason' in submission && submission.locked_reason"
                            class="overflow-hidden rounded-2xl border border-border/70 bg-card/45 shadow-[0_1px_0_rgba(0,0,0,0.03)]"
                        >
                            <div class="space-y-5 p-4 md:p-6">
                                <div class="flex gap-3 rounded-xl border border-dashed border-border/80 bg-muted/30 px-3.5 py-3">
                                    <Info
                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <div class="space-y-1.5">
                                        <p class="text-[0.8125rem] font-medium leading-relaxed text-foreground">
                                            Tidak dapat direview
                                        </p>
                                        <p class="text-[0.8125rem] leading-relaxed text-muted-foreground">
                                            {{ submission.locked_reason }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="overflow-hidden rounded-2xl border border-border/70 bg-card/45 shadow-[0_1px_0_rgba(0,0,0,0.03)]"
                        >
                            <div
                                class="flex items-center gap-3 border-b border-border/60 bg-warning/[0.07] px-4 py-3.5 md:px-5"
                            >
                                <span
                                    class="relative flex size-2.5 shrink-0"
                                    aria-hidden="true"
                                >
                                    <span
                                        class="absolute inline-flex size-full animate-ping rounded-full bg-warning/40 opacity-60"
                                    />
                                    <span class="relative inline-flex size-2.5 rounded-full bg-warning" />
                                </span>
                                <p class="text-[0.8125rem] font-medium leading-snug text-foreground">
                                    Menunggu keputusan Anda
                                </p>
                            </div>
                            <div class="space-y-5 p-4 md:p-6">
                                <p class="text-[0.8125rem] leading-[1.65] text-muted-foreground md:text-sm">
                                    <span class="font-medium text-foreground">Terima</span> jika pengajuan memenuhi syarat acara.
                                    <span class="font-medium text-foreground">Tolak</span> jika tidak dapat dilanjutkan. Tindakan
                                    ini memperbarui status review secara permanen untuk entri ini.
                                </p>
                                <div class="flex flex-col gap-3 sm:flex-row sm:gap-3">
                                    <Button
                                        type="button"
                                        class="h-11 min-h-11 flex-1 gap-2 rounded-xl border-success/35 text-[0.8125rem] font-medium text-success hover:bg-success/10"
                                        variant="outline"
                                        :disabled="isSubmissionReviewing(submission.id) || !canReview"
                                        @click="emit('review', { action: 'accept', submission })"
                                    >
                                        <CheckCircle2 class="size-4 shrink-0" />
                                        Terima pengajuan
                                    </Button>
                                    <Button
                                        type="button"
                                        class="h-11 min-h-11 flex-1 gap-2 rounded-xl border-destructive/35 text-[0.8125rem] font-medium text-destructive hover:bg-destructive/10"
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
                                    class="flex gap-3 rounded-xl border border-dashed border-border/80 bg-muted/30 px-3.5 py-3"
                                >
                                    <Info
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                    <p class="text-[0.8125rem] leading-relaxed text-muted-foreground">
                                        Untuk pendaftaran tim atau bundel: setiap anggota harus
                                        <span class="font-medium text-foreground">menerima undangan</span> terlebih dahulu. Setelah
                                        semua mengonfirmasi, tombol terima akan dapat digunakan.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div
                        v-else
                        class="overflow-hidden rounded-2xl border border-border/70 bg-card/45 shadow-[0_1px_0_rgba(0,0,0,0.03)]"
                        :class="
                            submission.review_status === 'accepted'
                                ? 'border-l-[3px] border-l-emerald-600/85'
                                : 'border-l-[3px] border-l-destructive/85'
                        "
                    >
                        <div class="space-y-5 p-4 md:p-6">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-[11px] font-medium text-muted-foreground">Status akhir</p>
                                <Badge
                                    variant="outline"
                                    class="rounded-full px-3 py-1 text-[11px] font-semibold"
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
                                    <dt class="text-[11px] font-medium text-muted-foreground">Waktu keputusan</dt>
                                    <dd class="text-sm font-medium tabular-nums leading-snug text-foreground">
                                        {{ formatDate(submission.reviewed_at) }}
                                    </dd>
                                </div>
                                <div v-if="submission.reviewer" class="space-y-1">
                                    <dt class="text-[11px] font-medium text-muted-foreground">Peninjau</dt>
                                    <dd class="break-words text-sm font-medium leading-snug text-foreground">
                                        {{ submission.reviewer.name }}
                                    </dd>
                                </div>
                            </dl>
                            <p
                                v-else
                                class="text-sm leading-relaxed text-muted-foreground"
                            >
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
