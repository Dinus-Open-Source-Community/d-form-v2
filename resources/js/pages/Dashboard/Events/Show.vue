<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Separator } from '@/components/ui/separator'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import {
    Pencil, Trash2, RotateCcw, Download, Upload, QrCode, FileText, Users,
    MapPin, CalendarDays, Clock, DollarSign, FileSpreadsheet,
} from 'lucide-vue-next'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import {
    destroy as destroyEvent,
    restore as restoreEvent,
    edit as editEvent,
} from '@/actions/App/Http/Controllers/Dashboard/Events/EventController'
import {
    dummyEvents, dummyForms, formatDate, formatDateTime,
    statusColorMap, categoryLabelMap, categoryColorMap, sessionLabelMap,
} from '@/lib/dummyData'
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue'

function parseCategories(raw: unknown): string[] {
    if (Array.isArray(raw)) return raw.map((s) => String(s).trim()).filter(Boolean)
    if (typeof raw === 'string') return raw.split(',').map((s) => s.trim()).filter(Boolean)
    return []
}

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    event?: IEvent
    forms?: { id: string; title: string }[]
}>()

const event = props.event ?? dummyEvents[0]
const forms = props.forms ?? dummyForms.filter((f) => f.event_id === event.id)

const showDeleteModal = ref(false)
const showRestoreModal = ref(false)
const showImportModal = ref(false)
const importFile = ref<File | null>(null)
const isDeleting = ref(false)
const isRestoring = ref(false)

function handleDelete() {
    isDeleting.value = true
    router.delete(destroyEvent(event.id).url, {
        onSuccess: () => toast.success('Event has been archived.'),
        onError: () => toast.error('Failed to archive event.'),
        onFinish: () => { isDeleting.value = false; showDeleteModal.value = false },
    })
}

function handleRestore() {
    isRestoring.value = true
    router.post(restoreEvent(event.id).url, {}, {
        onSuccess: () => toast.success('Event has been restored.'),
        onError: () => toast.error('Failed to restore event.'),
        onFinish: () => { isRestoring.value = false; showRestoreModal.value = false },
    })
}

function handleExport(format: string) {
    toast.info(`Exporting as ${format}...`)
}

function handleImportFileChange(e: Event) {
    const input = e.target as HTMLInputElement
    if (input.files?.[0]) importFile.value = input.files[0]
}

function handleImport() {
    if (!importFile.value) { toast.error('Please select a file.'); return }
    toast.success(`Importing ${importFile.value.name}...`)
    importFile.value = null
    showImportModal.value = false
}

const metaBlocks = [
    {
        title: 'Schedule',
        value: `${formatDate(event.start_date)} — ${formatDate(event.end_date)}`,
        icon: CalendarDays,
    },
    { title: 'Location', value: event.location, icon: MapPin },
    {
        title: 'Session',
        value: parseCategories(event.session).map((s) => sessionLabelMap[s] ?? s).join(', ') || '—',
        icon: Clock,
    },
    {
        title: 'Price',
        value: event.price > 0 ? `Rp ${Number(event.price).toLocaleString('id-ID')}` : 'Free',
        icon: DollarSign,
    },
]
</script>

<template>
    <Head :title="event.title" />

    <div class="flex flex-col gap-6">
        <PageHeader :title="event.title" backHref="/dashboard/events">
            <template #actions>
                <div class="flex flex-wrap items-center gap-1.5">
                    <Badge
                        v-for="cat in parseCategories(event.category)"
                        :key="cat"
                        class="text-[10px] text-white"
                        :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                    >
                        {{ categoryLabelMap[cat] ?? cat }}
                    </Badge>
                    <Badge variant="outline" class="text-[10px]" :style="{ color: statusColorMap[event.status] }">
                        {{ event.status === 'published' ? 'Published' : 'Draft' }}
                    </Badge>
                    <Badge v-if="event.deleted_at" variant="destructive" class="text-[10px]">Archived</Badge>
                </div>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div class="relative h-52 w-full lg:h-64">
                        <EventBannerImage :src="event.banner_url" :alt="event.title" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="m in metaBlocks"
                        :key="m.title"
                        class="flex gap-3 rounded-lg border border-border bg-card px-3 py-2.5 shadow-xs"
                    >
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted/60 text-primary">
                            <component :is="m.icon" class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{{ m.title }}</p>
                            <p class="mt-0.5 text-sm font-medium leading-snug text-foreground">{{ m.value }}</p>
                        </div>
                    </div>
                </div>

                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium">Registration</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 pt-0">
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-1.5 text-muted-foreground">
                                <Users class="size-4 shrink-0" />
                                Capacity
                            </span>
                            <span class="font-semibold tabular-nums text-foreground">{{ event.registered_count }}/{{ event.quota }}</span>
                        </div>
                        <Progress :model-value="event.registered_count" :max="event.quota" class="h-2" />
                        <div class="flex flex-col gap-1 text-xs text-muted-foreground sm:flex-row sm:justify-between sm:gap-4">
                            <span><span class="text-foreground/80">Opens</span> {{ formatDateTime(event.registration_start) }}</span>
                            <span><span class="text-foreground/80">Closes</span> {{ formatDateTime(event.registration_end) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Description</CardTitle>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <div class="prose prose-sm max-w-3xl text-pretty text-foreground/85" v-html="event.description" />
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-col gap-4">
                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Actions</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2 pt-0">
                        <Button variant="outline" size="sm" class="w-full justify-start" as-child>
                            <Link :href="editEvent.url(event.id)"><Pencil class="mr-2 size-4" />Edit Event</Link>
                        </Button>
                        <Button v-if="!event.deleted_at" variant="outline" size="sm" class="w-full justify-start text-destructive hover:text-destructive" @click="showDeleteModal = true">
                            <Trash2 class="mr-2 size-4" />Archive Event
                        </Button>
                        <Button v-if="event.deleted_at" variant="outline" size="sm" class="w-full justify-start" @click="showRestoreModal = true">
                            <RotateCcw class="mr-2 size-4" />Restore Event
                        </Button>
                        <Separator class="my-1!" />
                        <Button variant="outline" size="sm" class="w-full justify-start" @click="handleExport('CSV')">
                            <Download class="mr-2 size-4" />Export CSV
                        </Button>
                        <Button variant="outline" size="sm" class="w-full justify-start" @click="handleExport('Excel')">
                            <FileSpreadsheet class="mr-2 size-4" />Export Excel
                        </Button>
                        <Separator class="my-1!" />
                        <Button variant="outline" size="sm" class="w-full justify-start" @click="showImportModal = true">
                            <Upload class="mr-2 size-4" />Import Data
                        </Button>
                    </CardContent>
                </Card>

                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-3"><CardTitle class="text-sm font-medium">Attendance</CardTitle></CardHeader>
                    <CardContent class="pt-0">
                        <Button variant="outline" size="sm" class="w-full justify-start" as-child>
                            <Link :href="`/dashboard/events/${event.id}/scan`"><QrCode class="mr-2 size-4" />Scan QR Code</Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-3"><CardTitle class="text-sm font-medium">Forms</CardTitle></CardHeader>
                    <CardContent class="flex flex-col gap-2 pt-0">
                        <Button variant="outline" size="sm" class="w-full justify-start" as-child>
                            <Link :href="`/dashboard/events/${event.id}/forms`"><FileText class="mr-2 size-4" />Manage Forms</Link>
                        </Button>
                        <div v-if="forms.length > 0" class="flex flex-col gap-1 pt-1">
                            <div v-for="form in forms" :key="form.id" class="rounded-lg border px-3 py-2 text-xs text-muted-foreground">
                                {{ form.title }}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-3"><CardTitle class="text-sm font-medium">Registrants</CardTitle></CardHeader>
                    <CardContent class="pt-0">
                        <Button variant="outline" size="sm" class="w-full justify-start" as-child>
                            <Link :href="`/dashboard/events/${event.id}/registrants`"><Users class="mr-2 size-4" />Manage Registrants</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <ConfirmationModal :open="showDeleteModal" title="Archive Event" description="Are you sure you want to archive this event? It can be restored later." confirm-text="Archive" variant="destructive" :loading="isDeleting" @confirm="handleDelete" @cancel="showDeleteModal = false" @update:open="showDeleteModal = $event" />
    <ConfirmationModal :open="showRestoreModal" title="Restore Event" description="Are you sure you want to restore this event?" confirm-text="Restore" :loading="isRestoring" @confirm="handleRestore" @cancel="showRestoreModal = false" @update:open="showRestoreModal = $event" />

    <Dialog :open="showImportModal" @update:open="showImportModal = $event">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Import Data</DialogTitle>
                <DialogDescription>Upload a CSV or Excel file to import registrant data.</DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-4 pt-2">
                <div
                    class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-border px-6 py-8 text-center transition-colors hover:border-primary/50 hover:bg-muted/30"
                    @click="($refs.importInput as HTMLInputElement)?.click()"
                >
                    <Upload class="size-8 text-muted-foreground" />
                    <p class="mt-2 text-sm font-medium">{{ importFile ? importFile.name : 'Click to select file' }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">CSV, XLSX up to 5MB</p>
                    <input ref="importInput" type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="handleImportFileChange" />
                </div>
                <div class="flex items-center justify-between">
                    <Button variant="link" size="sm" class="h-auto p-0 text-xs" @click="toast.info('Downloading template...')">
                        <Download class="mr-1 size-3" />Download template
                    </Button>
                    <Button :disabled="!importFile" @click="handleImport">Import</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
