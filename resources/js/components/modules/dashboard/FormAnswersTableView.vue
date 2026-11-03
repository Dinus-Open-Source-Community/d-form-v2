<script setup lang="ts">
import { Check, Eye, FileText, X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue';
import {
    answerPreview,
    formSubmissionReviewIsPending,
    submissionFileName,
    submissionFileUrl,
    submissionReviewBadge,
} from '@/lib/formSubmissionsUi';
import { formatDateTime } from '@/lib/dateTime';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import type { IFormAnswersColumn } from '@/types/formAnswersView';

defineProps<{
    rows: IFormSubmission[];
    columns: IFormAnswersColumn[];
    reviewing: (submissionId: string) => boolean;
}>();

const emit = defineEmits<{
    detail: [submission: IFormSubmission];
    review: [payload: { action: 'accept' | 'reject'; submission: IFormSubmission }];
}>();

function answerValue(submission: IFormSubmission, column: IFormAnswersColumn): string {
    const value = submission.answers?.[column.key];
    return column.isFile && submissionFileUrl(value) ? submissionFileName(value) : answerPreview(value);
}

function acceptLabel(submission: IFormSubmission): string {
    return `Terima jawaban dari ${submission.user?.name ?? 'pengirim'}`;
}

function rejectLabel(submission: IFormSubmission): string {
    return `Tolak jawaban dari ${submission.user?.name ?? 'pengirim'}`;
}

function detailLabel(submission: IFormSubmission): string {
    return `Lihat detail jawaban dari ${submission.user?.name ?? 'pengirim'}`;
}
</script>

<template>
    <div data-testid="form-answers-table" class="overflow-x-auto">
        <Table>
            <caption class="sr-only">
                Daftar jawaban formulir
            </caption>
            <TableHeader>
                <TableRow class="hover:bg-transparent">
                    <TableHead scope="col">Pengirim</TableHead>
                    <TableHead scope="col">Status review</TableHead>
                    <TableHead v-for="column in columns" :key="column.key" scope="col" class="min-w-[160px]">
                        {{ column.label }}
                    </TableHead>
                    <TableHead scope="col">Dikirim</TableHead>
                    <TableHead scope="col" class="text-right">Aksi</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="submission in rows" :key="submission.id">
                    <TableCell>
                        <div class="flex min-w-0 items-center gap-3">
                            <UserAvatarFallback
                                :src="submission.user?.avatar ?? null"
                                :seed="userAvatarSeed(submission.user)"
                                avatar-class="size-8 shrink-0 rounded-lg border border-border"
                                fallback-round-class="rounded-lg"
                            />
                            <div class="min-w-0">
                                <p class="text-foreground truncate text-sm font-semibold">
                                    {{ submission.user?.name ?? 'Tanpa nama' }}
                                </p>
                                <p class="text-muted-foreground truncate text-xs">
                                    {{ submission.user?.email ?? '—' }}
                                </p>
                            </div>
                        </div>
                    </TableCell>
                    <TableCell class="whitespace-nowrap">
                        <Badge
                            variant="outline"
                            :class="['font-medium', submissionReviewBadge(submission.review_status).class]"
                        >
                            {{ submissionReviewBadge(submission.review_status).label }}
                        </Badge>
                    </TableCell>
                    <TableCell v-for="column in columns" :key="column.key" class="max-w-[220px] text-xs">
                        <span
                            v-if="column.isFile && submissionFileUrl(submission.answers?.[column.key])"
                            class="flex items-center gap-1.5"
                            :title="answerValue(submission, column)"
                        >
                            <FileText class="text-muted-foreground size-3.5 shrink-0" aria-hidden="true" />
                            <span class="line-clamp-2 break-all">{{ answerValue(submission, column) }}</span>
                        </span>
                        <span v-else class="line-clamp-2 break-words" :title="answerValue(submission, column)">
                            {{ answerValue(submission, column) }}
                        </span>
                    </TableCell>
                    <TableCell class="text-muted-foreground text-xs whitespace-nowrap">
                        {{ formatDateTime({ value: submission.submitted_at, variant: 'date-time-medium' }) }}
                    </TableCell>
                    <TableCell class="text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-1">
                            <Button
                                v-if="formSubmissionReviewIsPending(submission)"
                                type="button"
                                variant="ghost"
                                radius="icon"
                                size="icon-sm"
                                class="text-success hover:bg-success/10 hover:text-success"
                                :aria-label="acceptLabel(submission)"
                                :disabled="reviewing(submission.id)"
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
                                :aria-label="rejectLabel(submission)"
                                :disabled="reviewing(submission.id)"
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
                                :aria-label="detailLabel(submission)"
                                @click="emit('detail', submission)"
                            >
                                <Eye class="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
