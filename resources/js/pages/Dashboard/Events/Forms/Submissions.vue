<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { ArrowLeft, Download, FileText, Inbox } from 'lucide-vue-next'

defineOptions({ layout: DashboardFocusLayout })

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface SubmissionPaginator {
    data: IFormSubmission[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    links?: PaginationLink[]
}

const props = defineProps<{
    event: { id: string; title: string }
    form: { id: string; title: string }
    submissions: SubmissionPaginator
}>()

const answerKeys = computed(() => {
    const keys = new Set<string>()
    for (const submission of props.submissions.data) {
        Object.keys(submission.answers).forEach((key) => keys.add(key))
    }
    return [...keys].slice(0, 6)
})

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function humanizeKey(value: string): string {
    return value.replace(/^field_/, '').replace(/_/g, ' ')
}

function answerPreview(value: unknown): string {
    if (Array.isArray(value)) return value.map(String).join(', ')
    if (typeof value === 'string') return value
    if (value === null || value === undefined) return 'Empty'
    if (typeof value === 'number' || typeof value === 'boolean') return String(value)
    return 'Structured answer'
}

function fileUrl(value: unknown): string | null {
    return typeof value === 'string' && value.includes('/') ? `/storage/${value}` : null
}

function paginationLabel(value: string): string {
    return value.replace('&laquo;', 'Previous').replace('&raquo;', 'Next')
}
</script>

<template>
    <Head :title="`${form.title} Submissions`" />

    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between gap-3">
            <Button variant="ghost" size="sm" class="gap-1.5" as-child>
                <Link :href="`/dashboard/events/${event.id}/forms/${form.id}`">
                    <ArrowLeft class="size-3.5" />
                    Back to builder
                </Link>
            </Button>
            <Badge variant="outline" class="rounded-full">{{ submissions.total }} submissions</Badge>
        </div>

        <PageHeader :title="`${form.title} Submissions`" :subtitle="`Responses collected for ${event.title}.`" />

        <Card class="rounded-2xl border shadow-xs">
            <CardHeader class="flex flex-row items-center justify-between gap-4">
                <CardTitle class="text-base">Response Table</CardTitle>
                <Button variant="outline" size="sm" disabled>
                    <Download class="mr-1.5 size-4" />
                    Export soon
                </Button>
            </CardHeader>
            <CardContent>
                <div v-if="submissions.data.length === 0" class="neo-muted-panel flex flex-col items-center justify-center px-6 py-14 text-center">
                    <Inbox class="size-10 text-muted-foreground/60" />
                    <p class="mt-3 font-display text-lg font-bold">No submissions yet</p>
                    <p class="mt-1 max-w-sm text-sm text-muted-foreground">Once members submit this form, their answers will appear here.</p>
                </div>

                <div v-else class="overflow-x-auto rounded-xl border border-[var(--brutal-ink)]/15">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>User</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead v-for="key in answerKeys" :key="key">{{ humanizeKey(key) }}</TableHead>
                                <TableHead>Submitted</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="submission in submissions.data" :key="submission.id">
                                <TableCell class="font-semibold">{{ submission.user?.name ?? 'Unknown user' }}</TableCell>
                                <TableCell class="text-muted-foreground">{{ submission.user?.email ?? 'No email' }}</TableCell>
                                <TableCell v-for="key in answerKeys" :key="key" class="max-w-[220px] truncate">
                                    <a v-if="fileUrl(submission.answers[key])" :href="fileUrl(submission.answers[key]) ?? undefined" target="_blank" class="inline-flex items-center gap-1 text-primary underline-offset-4 hover:underline">
                                        <FileText class="size-3.5" />
                                        Open file
                                    </a>
                                    <span v-else>{{ answerPreview(submission.answers[key]) }}</span>
                                </TableCell>
                                <TableCell class="whitespace-nowrap text-muted-foreground">{{ formatDate(submission.submitted_at) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="submissions.links && submissions.last_page > 1" class="mt-5 flex flex-wrap justify-end gap-2">
                    <Button
                        v-for="link in submissions.links"
                        :key="link.label"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        :disabled="!link.url"
                        as-child
                    >
                        <Link v-if="link.url" :href="link.url">{{ paginationLabel(link.label) }}</Link>
                        <span v-else>{{ paginationLabel(link.label) }}</span>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
