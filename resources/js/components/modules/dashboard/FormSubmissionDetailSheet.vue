<script setup lang="ts">
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Clock, FileText, CheckCircle2, XCircle } from 'lucide-vue-next'
import { submissionUserInitials } from '@/lib/formSubmissionsUi'

defineProps<{
    submission: IFormSubmission | null
    allAnswerKeys: string[]
    formatDate: (v: string) => string
    humanizeKey: (v: string) => string
    answerPreview: (v: unknown) => string
    fileUrl: (v: unknown) => string | null
}>()

const open = defineModel<boolean>('open', { required: true })

defineEmits<{
    review: [payload: { action: 'accept' | 'reject'; submission: IFormSubmission }]
}>()
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="sm:max-w-md md:max-w-lg">
            <SheetHeader class="border-b border-border pb-6">
                <div class="flex items-center gap-4">
                    <Avatar class="size-14 rounded-2xl border border-border">
                        <AvatarFallback class="rounded-2xl bg-primary/10 text-lg font-semibold text-primary">
                            {{ submissionUserInitials(submission?.user?.name ?? 'An') }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <SheetTitle class="font-display text-2xl font-bold tracking-[-0.025em] text-foreground">
                            Submission Details
                        </SheetTitle>
                        <SheetDescription class="mt-1.5 truncate text-sm text-muted-foreground">
                            From {{ submission?.user?.name ?? 'Anonymous' }}
                        </SheetDescription>
                    </div>
                </div>
            </SheetHeader>

            <div class="mt-8 space-y-8 overflow-y-auto px-1 pb-20 pr-3">
                <section class="space-y-4">
                    <h5 class="text-[10px] font-semibold uppercase tracking-[0.14em] text-primary">Member Information</h5>
                    <div class="app-surface-soft space-y-4 p-5">
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Email Address</span>
                            <span class="text-sm font-semibold text-foreground">{{ submission?.user?.email ?? 'No email provided' }}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Submitted At</span>
                            <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                                <Clock class="size-4 text-muted-foreground" />
                                <span>{{ submission ? formatDate(submission.submitted_at) : '-' }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <h5 class="text-[10px] font-semibold uppercase tracking-[0.14em] text-primary">Form Responses</h5>
                    <div class="grid grid-cols-1 gap-3">
                        <div
                            v-for="key in allAnswerKeys"
                            :key="key"
                            class="app-surface flex flex-col gap-2 p-5"
                        >
                            <span class="app-kicker">
                                {{ humanizeKey(key) }}
                            </span>
                            <div class="mt-1">
                                <a
                                    v-if="fileUrl(submission?.answers[key])"
                                    :href="fileUrl(submission?.answers[key]) ?? undefined"
                                    target="_blank"
                                    class="inline-flex items-center gap-2.5 font-semibold text-primary decoration-primary/30 underline underline-offset-4 transition-colors hover:decoration-primary"
                                >
                                    <div class="grid size-10 place-items-center rounded-lg border border-primary/20 bg-primary/10">
                                        <FileText class="size-5" />
                                    </div>
                                    <span>Download Attachment</span>
                                </a>
                                <p v-else class="whitespace-pre-wrap text-sm leading-relaxed text-foreground">
                                    {{ answerPreview(submission?.answers[key]) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="submission" class="space-y-3 border-t border-border pt-6">
                    <h5 class="text-[10px] font-semibold uppercase tracking-[0.14em] text-primary">Review (preview)</h5>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        These actions only trigger a notification until the backend adds status columns and approve/reject routes.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            class="min-w-[120px] flex-1 gap-2 border-success/30 text-success hover:bg-success/10"
                            variant="outline"
                            @click="$emit('review', { action: 'accept', submission })"
                        >
                            <CheckCircle2 class="size-4" />
                            Accept
                        </Button>
                        <Button
                            type="button"
                            class="min-w-[120px] flex-1 gap-2 border-destructive/30 text-destructive hover:bg-destructive/10"
                            variant="outline"
                            @click="$emit('review', { action: 'reject', submission })"
                        >
                            <XCircle class="size-4" />
                            Reject
                        </Button>
                    </div>
                </section>
            </div>
        </SheetContent>
    </Sheet>
</template>
