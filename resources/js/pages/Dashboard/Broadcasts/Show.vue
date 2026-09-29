<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import TipTapEditor from '@/components/modules/dashboard/events/TipTapEditor.vue'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { routes } from '@/lib/routes'
import { showErrorToast } from '@/lib/error-message'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

interface Attachment {
    id: string
    file_name: string
    mime_type: string | null
    file_size: number
}

interface Broadcast {
    id: string
    name: string
    status: string
    scheduled_at: string | null
    schedule_date: string | null
    schedule_time: string | null
    delay_min: number
    delay_max: number
    event_id: string | null
    event_title: string | null
    subject: string | null
    content: string | null
    datasets: Array<{ type: string; id?: string | null; event_id?: string | null; period_id?: string | null }>
    total_recipients: number
    total_sent: number
    total_failed: number
    sent_count: number
    failed_count: number
    pending_count: number
    processing_count: number
    cancelled_count: number
    recipients_count: number
    attachments: Attachment[]
}

interface RecipientRow {
    id: string
    name: string | null
    email: string
    status: string
    attempts: number
    sent_at: string | null
    error_message: string | null
}

interface EventOption {
    id: string
    title: string
}

const props = defineProps<{
    broadcast: Broadcast
    events: EventOption[]
    recipients?: { data: RecipientRow[]; current_page: number; last_page: number; total: number }
    duplicateSummary?: { total: number; unique: number; duplicates: number; duplicate_emails: string[] }
    preview?: { from: string; subject: string; content: string; sample_name: string; event_name: string; attachments: string[] }
}>()

onMounted(() => {
    setTopbar({ title: props.broadcast.name, subtitle: `Status: ${props.broadcast.status}` })
})

const isDraft = computed(() => props.broadcast.status === 'draft')
const isScheduled = computed(() => props.broadcast.status === 'scheduled')
const canCancel = computed(() => ['scheduled', 'processing'].includes(props.broadcast.status))

// --- Dataset / snapshot ---
const selectedSources = ref<string[]>(['users'])
const manualRows = ref('')

const snapshotForm = useForm({
    datasets: [] as Array<Record<string, string>>,
    manual: [] as Array<{ name: string | null; email: string }>,
    csv_file: null as File | null,
})

function generateSnapshot(): void {
    const datasets = selectedSources.value.map((t) => ({ type: t }))
    const manual = manualRows.value
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const parts = line.split(',').map((s) => s.trim())
            if (parts.length >= 2 && parts[1]?.includes('@')) {
                return { name: parts[0] || null, email: parts[1] as string }
            }
            return { name: null, email: line }
        })
    snapshotForm.datasets = datasets
    snapshotForm.manual = manual
    snapshotForm.post(routes.admin.broadcasts.snapshot(props.broadcast.id), {
        preserveScroll: true,
        forceFormData: true,
        onError: () => showErrorToast('Gagal generate snapshot.'),
    })
}

function onCsv(e: Event): void {
    const input = e.target as HTMLInputElement
    snapshotForm.csv_file = input.files?.[0] ?? null
}

// --- Recipients manual ---
const recipientForm = useForm({ name: '', email: '' })

function addRecipient(): void {
    recipientForm.post(routes.admin.broadcasts.recipients(props.broadcast.id), {
        preserveScroll: true,
        onSuccess: () => recipientForm.reset(),
        onError: () => showErrorToast('Gagal menambah recipient.'),
    })
}

function deleteRecipient(id: string): void {
    router.delete(`${routes.admin.broadcasts.recipients(props.broadcast.id)}/${id}`, {
        preserveScroll: true,
        onError: () => showErrorToast('Gagal menghapus recipient.'),
    })
}

function loadRecipients(): void {
    router.get(
        routes.admin.broadcasts.show(props.broadcast.id),
        { tab: 'recipients' },
        { preserveState: true, preserveScroll: true, only: ['recipients', 'duplicateSummary', 'broadcast'] },
    )
}

// --- Email content ---
const contentForm = useForm({
    subject: props.broadcast.subject ?? '',
    content: props.broadcast.content ?? '',
    event_id: props.broadcast.event_id ?? '',
})

function saveContent(): void {
    contentForm.post(routes.admin.broadcasts.content(props.broadcast.id), {
        preserveScroll: true,
        onError: () => showErrorToast('Gagal menyimpan email. Periksa variable {{name}}/{{event_name}}.'),
    })
}

// --- Attachments ---
const attachForm = useForm({ file: null as File | null })

function uploadAttachment(): void {
    attachForm.post(routes.admin.broadcasts.attachments(props.broadcast.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => attachForm.reset(),
        onError: () => showErrorToast('Attachment maks 1.5 MB.'),
    })
}

function deleteAttachment(id: string): void {
    router.delete(`${routes.admin.broadcasts.attachments(props.broadcast.id)}/${id}`, { preserveScroll: true })
}

// --- Schedule / actions ---
const scheduleForm = useForm({
    schedule_date: props.broadcast.schedule_date ?? '',
    schedule_time: props.broadcast.schedule_time ?? '',
})

const testForm = useForm({ email: '' })

function doSchedule(): void {
    router.post(routes.admin.broadcasts.schedule(props.broadcast.id), {}, { preserveScroll: true })
}

function updateSchedule(): void {
    scheduleForm.patch(routes.admin.broadcasts.schedule(props.broadcast.id), { preserveScroll: true })
}

function sendTest(): void {
    testForm.post(routes.admin.broadcasts.test(props.broadcast.id), {
        preserveScroll: true,
        onSuccess: () => testForm.reset(),
    })
}

function cancelBroadcast(): void {
    router.post(routes.admin.broadcasts.cancel(props.broadcast.id), {}, { preserveScroll: true })
}

function retryFailed(): void {
    router.post(routes.admin.broadcasts.retry(props.broadcast.id), {}, { preserveScroll: true })
}

function deleteBroadcast(): void {
    if (!confirm('Hapus broadcast beserta recipients & attachments?')) return
    router.delete(routes.admin.broadcasts.destroy(props.broadcast.id))
}

function loadPreview(): void {
    router.get(routes.admin.broadcasts.preview(props.broadcast.id), {}, { preserveState: true, preserveScroll: true, only: ['preview'] })
}

const progress = computed(() => {
    const total = props.broadcast.recipients_count || 0
    if (total === 0) return 0
    return Math.round(((props.broadcast.sent_count || 0) / total) * 100)
})
</script>

<template>
    <Head :title="broadcast.name" />
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 pb-10">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ broadcast.name }}</h1>
                <p class="text-muted-foreground text-sm">
                    Status: <Badge variant="outline">{{ broadcast.status }}</Badge>
                    <span class="ml-2">Jadwal: {{ broadcast.scheduled_at ?? '—' }}</span>
                    <span class="ml-2">Delay: {{ broadcast.delay_min }}–{{ broadcast.delay_max }} dtk</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="isDraft" variant="default" @click="doSchedule">Schedule Broadcast</Button>
                <Button v-if="canCancel" variant="destructive" @click="cancelBroadcast">Cancel</Button>
                <Button v-if="broadcast.failed_count > 0" variant="outline" @click="retryFailed">Retry Failed ({{ broadcast.failed_count }})</Button>
                <Button variant="ghost" @click="deleteBroadcast">Hapus</Button>
            </div>
        </div>

        <!-- Monitoring -->
        <Card class="rounded-2xl">
            <CardHeader><CardTitle>Monitoring</CardTitle></CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div class="rounded-xl border p-3 text-center"><div class="text-2xl font-bold">{{ broadcast.recipients_count }}</div><div class="text-muted-foreground text-xs">Total</div></div>
                    <div class="rounded-xl border p-3 text-center"><div class="text-2xl font-bold">{{ broadcast.sent_count }}</div><div class="text-muted-foreground text-xs">Sent</div></div>
                    <div class="rounded-xl border p-3 text-center"><div class="text-2xl font-bold">{{ broadcast.failed_count }}</div><div class="text-muted-foreground text-xs">Failed</div></div>
                    <div class="rounded-xl border p-3 text-center"><div class="text-2xl font-bold">{{ broadcast.pending_count }}</div><div class="text-muted-foreground text-xs">Pending</div></div>
                    <div class="rounded-xl border p-3 text-center"><div class="text-2xl font-bold">{{ progress }}%</div><div class="text-muted-foreground text-xs">Progress</div></div>
                </div>
            </CardContent>
        </Card>

        <!-- Dataset & snapshot -->
        <Card v-if="isDraft" class="rounded-2xl">
            <CardHeader><CardTitle>02–03 · Dataset & Snapshot</CardTitle></CardHeader>
            <CardContent class="flex flex-col gap-4">
                <div class="flex flex-wrap gap-4 text-sm">
                    <label v-for="opt in ['event_participants', 'recruitment_applicants', 'users']" :key="opt" class="flex items-center gap-2">
                        <input v-model="selectedSources" type="checkbox" :value="opt" class="size-4" />
                        {{ opt }}
                    </label>
                </div>
                <div class="grid gap-2">
                    <Label>Manual (satu per baris: <code>Nama,email</code> atau <code>email</code>)</Label>
                    <textarea v-model="manualRows" rows="3" class="border-input bg-background rounded-md border p-2 text-sm" placeholder="Nafan,nafan@gmail.com&#10;budi@gmail.com" />
                </div>
                <div class="grid gap-2">
                    <Label>CSV (header <code>name,email</code> atau <code>email</code>, maks 2 MB)</Label>
                    <Input type="file" accept=".csv,.txt" @change="onCsv" />
                </div>
                <Button :disabled="snapshotForm.processing" @click="generateSnapshot">
                    {{ snapshotForm.processing ? 'Memproses…' : 'Generate Snapshot' }}
                </Button>
                <div v-if="duplicateSummary && duplicateSummary.duplicates > 0" class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    {{ duplicateSummary.duplicates }} duplicate terdeteksi (tidak dihapus otomatis).
                    Contoh: {{ duplicateSummary.duplicate_emails.slice(0, 5).join(', ') }}
                </div>
            </CardContent>
        </Card>

        <!-- Recipients -->
        <Card class="rounded-2xl">
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>Recipients ({{ recipients?.total ?? broadcast.recipients_count }})</CardTitle>
                <Button variant="outline" size="sm" @click="loadRecipients">Muat recipients</Button>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <div v-if="isDraft" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="grid flex-1 gap-2"><Label>Nama</Label><Input v-model="recipientForm.name" placeholder="Opsional" /></div>
                    <div class="grid flex-1 gap-2"><Label>Email</Label><Input v-model="recipientForm.email" placeholder="nama@email.com" /></div>
                    <Button @click="addRecipient">+ Tambah</Button>
                </div>
                <div v-if="recipients?.data?.length" class="overflow-x-auto">
                    <Table>
                        <TableHeader><TableRow><TableHead>Nama</TableHead><TableHead>Email</TableHead><TableHead>Status</TableHead><TableHead class="text-right">Attempt</TableHead><TableHead v-if="isDraft" class="text-right">Aksi</TableHead></TableRow></TableHeader>
                        <TableBody>
                            <TableRow v-for="r in recipients.data" :key="r.id">
                                <TableCell>{{ r.name ?? '—' }}</TableCell>
                                <TableCell class="font-mono text-xs">{{ r.email }}</TableCell>
                                <TableCell><Badge variant="outline">{{ r.status }}</Badge></TableCell>
                                <TableCell class="text-right">{{ r.attempts }}</TableCell>
                                <TableCell v-if="isDraft" class="text-right">
                                    <Button variant="ghost" size="sm" @click="deleteRecipient(r.id)">Hapus</Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
                <p v-else class="text-muted-foreground text-sm">Klik “Muat recipients” untuk melihat snapshot. Status awal: <code>pending</code>.</p>
            </CardContent>
        </Card>

        <!-- Email composer -->
        <Card v-if="isDraft" class="rounded-2xl">
            <CardHeader><CardTitle>04 · Email (Tiptap)</CardTitle></CardHeader>
            <CardContent class="flex flex-col gap-4">
                <div class="grid gap-2">
                    <Label>Subject (mendukung <code v-pre>{{name}}</code> <code v-pre>{{event_name}}</code>)</Label>
                    <Input v-model="contentForm.subject" placeholder="Pengumuman Hasil Seleksi" />
                </div>
                <div class="grid gap-2">
                    <Label>Event context</Label>
                    <select v-model="contentForm.event_id" class="border-input bg-background rounded-md border px-3 py-2 text-sm">
                        <option value="">— Tanpa event —</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">{{ e.title }}</option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label>Konten</Label>
                    <TipTapEditor v-model="contentForm.content" />
                    <p class="text-muted-foreground text-xs">Inline image otomatis base64, maks 1.5 MB/gambar. Hanya <code v-pre>{{name}}</code> dan <code v-pre>{{event_name}}</code> yang didukung.</p>
                </div>
                <Button :disabled="contentForm.processing" @click="saveContent">Simpan email</Button>

                <div class="border-t pt-4">
                    <Label>Attachments (masing-masing maks 1.5 MB)</Label>
                    <ul class="mt-2 flex flex-col gap-1 text-sm">
                        <li v-for="a in broadcast.attachments" :key="a.id" class="flex items-center justify-between gap-2">
                            <span class="font-mono text-xs">{{ a.file_name }} ({{ (a.file_size / 1024).toFixed(1) }} KB)</span>
                            <Button variant="ghost" size="sm" @click="deleteAttachment(a.id)">Hapus</Button>
                        </li>
                    </ul>
                    <div class="mt-2 flex gap-2">
                        <Input type="file" @change="(e: Event) => { attachForm.file = (e.target as HTMLInputElement).files?.[0] ?? null }" />
                        <Button variant="outline" @click="uploadAttachment">Upload</Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Preview & test -->
        <Card class="rounded-2xl">
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>05 · Preview & Test</CardTitle>
                <Button variant="outline" size="sm" @click="loadPreview">Muat preview</Button>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <div v-if="preview" class="rounded-xl border p-4 text-sm">
                    <p><strong>From:</strong> {{ preview.from }}</p>
                    <p><strong>Subject:</strong> {{ preview.subject }}</p>
                    <div class="prose mt-2 max-w-none" v-html="preview.content" />
                    <p class="text-muted-foreground mt-2 text-xs">Attachments: {{ preview.attachments.join(', ') || '—' }}</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="grid flex-1 gap-2"><Label>Test email</Label><Input v-model="testForm.email" placeholder="admin@example.com" /></div>
                    <Button variant="outline" @click="sendTest">Kirim test</Button>
                </div>
                <p class="text-muted-foreground text-xs">Test email tidak masuk snapshot & tidak memengaruhi statistik.</p>
            </CardContent>
        </Card>

        <!-- Schedule edit -->
        <Card v-if="isScheduled" class="rounded-2xl">
            <CardHeader><CardTitle>06 · Ubah jadwal (hanya schedule saat scheduled)</CardTitle></CardHeader>
            <CardContent class="flex gap-2">
                <Input v-model="scheduleForm.schedule_date" type="date" />
                <Input v-model="scheduleForm.schedule_time" type="time" />
                <Button @click="updateSchedule">Simpan jadwal</Button>
            </CardContent>
        </Card>
    </div>
</template>
