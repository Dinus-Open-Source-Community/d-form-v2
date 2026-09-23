<script setup lang="ts">
import { Check, Clock, FileText, X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import FormFieldAnswerDisplay from '@/components/modules/dashboard/FormFieldAnswerDisplay.vue';
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue';
import { formSubmissionReviewIsPending, submissionReviewBadge } from '@/lib/formSubmissionsUi';
import { userAvatarSeed } from '@/lib/userAvatarFallback';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    submission: IFormSubmission | null;
    answerKeys: string[];
    fields: IFormField[];
    formatDate: (value: string) => string;
    humanizeKey: (value: string) => string;
    isSubmissionReviewing: (submissionId: string) => boolean;
}>();

const emit = defineEmits<{
    review: [payload: { action: 'accept' | 'reject'; submission: IFormSubmission }];
}>();

function fieldForKey(key: string): IFormField | null {
    return props.fields.find((field) => field.name === key) ?? null;
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            side="right"
            class="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-md lg:max-w-lg"
        >
            <SheetHeader class="shrink-0 gap-0 border-b border-border/70 px-5 pt-5 pb-4 text-left sm:px-6">
                <div class="flex items-start gap-3.5 pr-8">
                    <UserAvatarFallback
                        :src="submission?.user?.avatar ?? null"
                        :seed="userAvatarSeed(submission?.user ?? null)"
                        avatar-class="size-11 shrink-0 rounded-xl border border-border"
                        fallback-round-class="rounded-xl"
                    />
                    <div class="min-w-0 flex-1">
                        <SheetTitle
                            class="truncate text-base font-semibold tracking-[-0.01em] text-foreground"
                        >
                            {{ submission?.user?.name ?? 'Tanpa nama' }}
                        </SheetTitle>
                        <SheetDescription class="mt-0.5 truncate text-xs">
                            {{ submission?.user?.email ?? 'Email tidak tercatat' }}
                        </SheetDescription>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <Badge
                                v-if="submission"
                                variant="outline"
                                :class="['font-medium', submissionReviewBadge(submission.review_status).class]"
                            >
                                {{ submissionReviewBadge(submission.review_status).label }}
                            </Badge>
                            <span
                                v-if="submission"
                                class="inline-flex items-center gap-1 text-[11px] text-muted-foreground tabular-nums"
                            >
                                <Clock class="size-3 shrink-0" aria-hidden="true" />
                                {{ formatDate(submission.submitted_at) }}
                            </span>
                        </div>
                    </div>
                </div>
            </SheetHeader>

            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
                <section v-if="submission" class="space-y-3">
                    <h3 class="text-xs font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                        Jawaban formulir
                    </h3>
                    <div class="space-y-3">
                        <div
                            v-for="key in answerKeys"
                            :key="key"
                            class="rounded-xl border border-border/70 bg-card/50 p-4"
                        >
                            <p
                                class="mb-3 border-b border-border/60 pb-2 text-sm font-semibold text-foreground"
                            >
                                {{ humanizeKey(key) }}
                            </p>
                            <FormFieldAnswerDisplay
                                :field="fieldForKey(key)"
                                :value="submission.answers?.[key]"
                            />
                        </div>
                    </div>
                    <p class="flex items-start gap-1.5 text-[11px] leading-relaxed text-muted-foreground">
                        <FileText class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                        Lampiran hanya berupa pratinjau di sini. Buka atau unduh berkas dari tombol yang
                        tersedia pada setiap jawaban bila perlu memeriksa isinya.
                    </p>
                </section>
                <p v-else class="py-10 text-center text-sm text-muted-foreground">
                    Pilih salah satu jawaban untuk melihat detailnya.
                </p>
            </div>

            <SheetFooter
                v-if="submission"
                class="shrink-0 flex-col gap-2 border-t border-border/70 bg-muted/20 px-5 py-4 sm:flex-col sm:px-6"
            >
                <template v-if="formSubmissionReviewIsPending(submission)">
                    <p class="w-full text-center text-xs text-muted-foreground sm:text-left">
                        Terima jika pengajuan memenuhi syarat, tolak jika tidak dapat dilanjutkan.
                    </p>
                    <div class="flex w-full flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 flex-1 gap-1.5 border-success/35 text-sm font-medium text-success hover:bg-success/10 hover:text-success"
                            :disabled="isSubmissionReviewing(submission.id)"
                            @click="emit('review', { action: 'accept', submission })"
                        >
                            <Check class="size-4" aria-hidden="true" />
                            Terima
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 flex-1 gap-1.5 border-destructive/35 text-sm font-medium text-destructive hover:bg-destructive/10 hover:text-destructive"
                            :disabled="isSubmissionReviewing(submission.id)"
                            @click="emit('review', { action: 'reject', submission })"
                        >
                            <X class="size-4" aria-hidden="true" />
                            Tolak
                        </Button>
                    </div>
                </template>
                <p v-else class="w-full text-center text-xs leading-relaxed text-muted-foreground">
                    <template v-if="submission.review_status === 'accepted'">
                        Jawaban ini sudah diterima dan tidak dapat direview ulang.
                    </template>
                    <template v-else>
                        Jawaban ini sudah ditolak dan tidak dapat direview ulang.
                    </template>
                    <template v-if="submission.reviewed_at">
                        Direview pada {{ formatDate(submission.reviewed_at) }}.
                    </template>
                </p>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
