<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { FileText, Eye, CheckCircle2, XCircle } from 'lucide-vue-next'
import { submissionUserInitials } from '@/lib/formSubmissionsUi'

defineProps<{
    submissions: IFormSubmission[]
    answerKeys: string[]
    formatDate: (v: string) => string
    humanizeKey: (v: string) => string
    answerPreview: (v: unknown) => string
    fileUrl: (v: unknown) => string | null
}>()

defineEmits<{
    openDetail: [submission: IFormSubmission]
    review: [payload: { action: 'accept' | 'reject'; submission: IFormSubmission }]
}>()
</script>

<template>
    <div class="app-surface overflow-hidden p-0">
        <div class="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow class="hover:bg-transparent">
                        <TableHead
                            class="sticky left-0 z-20 h-12 bg-muted/40 px-6 text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                        >
                            Member
                        </TableHead>
                        <TableHead
                            v-for="key in answerKeys"
                            :key="key"
                            class="h-12 min-w-[180px] bg-muted/30 px-6 text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                        >
                            {{ humanizeKey(key) }}
                        </TableHead>
                        <TableHead class="h-12 bg-muted/30 px-6 text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                            Submitted
                        </TableHead>
                        <TableHead class="h-12 bg-muted/30 px-6 text-center text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                            Actions
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
                                <Avatar class="size-9 rounded-lg border border-border">
                                    <AvatarImage :src="''" :alt="submission.user?.name ?? 'U'" />
                                    <AvatarFallback class="rounded-lg bg-primary/10 text-xs font-semibold text-primary">
                                        {{ submissionUserInitials(submission.user?.name ?? 'An') }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold tracking-[-0.005em] text-foreground">
                                        {{ submission.user?.name ?? 'Anonymous' }}
                                    </p>
                                    <p class="truncate text-[10px] text-muted-foreground">
                                        {{ submission.user?.email ?? '-' }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell
                            v-for="key in answerKeys"
                            :key="key"
                            class="max-w-[240px] px-6 py-4 text-xs leading-relaxed"
                        >
                            <div v-if="fileUrl(submission.answers[key])" class="flex items-center gap-1.5 text-primary">
                                <FileText class="size-3.5" />
                                <span class="font-medium underline underline-offset-4">Attachment</span>
                            </div>
                            <span v-else class="line-clamp-2 text-foreground/85">
                                {{ answerPreview(submission.answers[key]) }}
                            </span>
                        </TableCell>
                        <TableCell class="whitespace-nowrap px-6 py-4 text-[11px] text-muted-foreground">
                            {{ formatDate(submission.submitted_at) }}
                        </TableCell>
                        <TableCell class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Accept (UI only)"
                                    class="text-success hover:bg-success/10 hover:text-success"
                                    @click="$emit('review', { action: 'accept', submission })"
                                >
                                    <CheckCircle2 class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Reject (UI only)"
                                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    @click="$emit('review', { action: 'reject', submission })"
                                >
                                    <XCircle class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
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
