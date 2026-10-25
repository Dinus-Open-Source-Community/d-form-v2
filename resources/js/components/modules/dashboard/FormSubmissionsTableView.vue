<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { FileText, Eye, CheckCircle2, XCircle } from 'lucide-vue-next'
import { formSubmissionReviewIsPending, submissionAdminAcceptBlocked, submissionReviewBadge } from '@/lib/formSubmissionsUi'
import { userAvatarSeed } from '@/lib/userAvatarFallback'
import { formatDateTime } from '@/lib/dateTime'
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue'

defineProps<{
    submissions: IFormSubmission[]
    answerKeys: string[]
    humanizeKey: (v: string) => string
    answerPreview: (v: unknown) => string
    fileUrl: (v: unknown) => string | null
    isSubmissionReviewing: (submissionId: string) => boolean
}>()

defineEmits<{
    openDetail: [submission: IFormSubmission]
    review: [payload: { action: 'accept' | 'reject'; submission: IFormSubmission }]
}>()
</script>

<template>
    <div class="app-surface overflow-hidden rounded-2xl p-0">
        <div class="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow class="hover:bg-transparent">
                        <TableHead
                            class="sticky left-0 z-20 h-12 bg-muted/40 px-6 text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                        >
                            Pengirim
                        </TableHead>
                        <TableHead
                            class="h-12 min-w-[140px] bg-muted/30 px-6 text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                        >
                            Status review
                        </TableHead>
                        <TableHead
                            v-for="key in answerKeys"
                            :key="key"
                            class="h-12 min-w-[180px] bg-muted/30 px-6 text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                        >
                            {{ humanizeKey(key) }}
                        </TableHead>
                        <TableHead class="h-12 bg-muted/30 px-6 text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                            Dikirim
                        </TableHead>
                        <TableHead class="h-12 bg-muted/30 px-6 text-center text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                            Aksi
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(submission, idx) in submissions"
                        :key="submission.id"
                        class="animate-app-fade-in border-b border-border/60 transition-colors hover:bg-muted/30"
                        :style="{ animationDelay: `${idx * 50}ms` }"
                    >
                        <TableCell class="sticky left-0 z-10 border-r border-border/60 bg-card px-6 py-4">
                            <div class="flex items-center gap-3">
                                <UserAvatarFallback
                                    :src="submission.user?.avatar ?? null"
                                    :seed="userAvatarSeed(submission.user)"
                                    avatar-class="size-9 rounded-lg border border-border"
                                    fallback-round-class="rounded-lg"
                                />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold tracking-[-0.005em] text-foreground">
                                        {{ submission.user?.name ?? 'Tanpa nama' }}
                                    </p>
                                    <p class="truncate text-[10px] text-muted-foreground">
                                        {{ submission.user?.email ?? '—' }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell class="whitespace-nowrap px-6 py-4">
                            <Badge
                                variant="outline"
                                :class="['font-medium', submissionReviewBadge(submission.review_status).class]"
                            >
                                {{ submissionReviewBadge(submission.review_status).label }}
                            </Badge>
                        </TableCell>
                        <TableCell
                            v-for="key in answerKeys"
                            :key="key"
                            class="max-w-[240px] px-6 py-4 text-xs leading-relaxed"
                        >
                            <div v-if="fileUrl(submission.answers?.[key])" class="flex items-center gap-1.5 text-primary">
                                <FileText class="size-3.5 shrink-0" />
                                <a
                                    :href="fileUrl(submission.answers?.[key]) ?? undefined"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium underline underline-offset-4"
                                    @click.stop
                                >
                                    Lampiran
                                </a>
                            </div>
                            <span v-else class="line-clamp-2 text-foreground/85">
                                {{ answerPreview(submission.answers?.[key]) }}
                            </span>
                        </TableCell>
                        <TableCell class="whitespace-nowrap px-6 py-4 text-[11px] text-muted-foreground">
                            {{ formatDateTime({ value: submission.submitted_at, variant: 'date-time-medium' }) }}
                        </TableCell>
                        <TableCell class="px-6 py-4 text-center">
                            <div class="flex flex-wrap items-center justify-center gap-1">
                                <template v-if="formSubmissionReviewIsPending(submission)">
                                    <Button radius="icon"
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Terima pengiriman"
                                        aria-label="Terima pengiriman"
                                        class="text-success hover:bg-success/10 hover:text-success"
                                        :disabled="isSubmissionReviewing(submission.id) || submissionAdminAcceptBlocked(submission)"
                                        @click="$emit('review', { action: 'accept', submission })"
                                    >
                                        <CheckCircle2 class="size-4" />
                                    </Button>
                                    <Button radius="icon"
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Tolak pengiriman"
                                        aria-label="Tolak pengiriman"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        :disabled="isSubmissionReviewing(submission.id)"
                                        @click="$emit('review', { action: 'reject', submission })"
                                    >
                                        <XCircle class="size-4" />
                                    </Button>
                                </template>
                                <Button radius="icon"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Lihat detail lengkap"
                                    aria-label="Lihat detail lengkap"
                                    class="hover:bg-primary/10 hover:text-primary"
                                    @click="$emit('openDetail', submission)"
                                >
                                    <Eye class="size-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
