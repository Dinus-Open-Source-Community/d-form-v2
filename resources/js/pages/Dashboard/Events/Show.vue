<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import {
    Pencil, Trash2, RotateCcw, Download, Upload, QrCode, FileText, Users,
    MapPin, CalendarDays, Clock, Banknote, FileSpreadsheet, ArrowUpRight, Sparkles,
} from 'lucide-vue-next'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import {
    destroy as destroyEvent,
    restore as restoreEvent,
    edit as editEvent,
} from '@/actions/App/Http/Controllers/Dashboard/Events/EventController'
import {
    dummyEvents, dummyForms, dummyRegistrants, formatDate, formatDateTime,
    categoryLabelMap, categoryColorMap, sessionLabelMap,
} from '@/lib/dummyData'
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event?: IEvent
    forms?: { id: string; title: string }[]
}>()

const event = props.event ?? dummyEvents[0]
const forms = props.forms ?? dummyForms.filter((f) => f.event_id === event.id)
const previewRegistrants = dummyRegistrants.filter((r) => r.event_id === event.id).slice(0, 5)
const totalRegistrants = dummyRegistrants.filter((r) => r.event_id === event.id).length

function parseCategories(raw: unknown): string[] {
    if (Array.isArray(raw)) return raw.map((s) => String(s).trim()).filter(Boolean)
    if (typeof raw === 'string') return raw.split(',').map((s) => s.trim()).filter(Boolean)
    return []
}

function getInitials(name: string): string {
    return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}

const showDeleteModal = ref(false)
const showRestoreModal = ref(false)
const showImportModal = ref(false)
const importFile = ref<File | null>(null)
const isDeleting = ref(false)
const isRestoring = ref(false)

const fillPercent = computed(() => {
    if (!event.quota) return 0
    return Math.min(100, Math.round((event.registered_count / event.quota) * 100))
})

const remainingSeats = computed(() => Math.max(0, event.quota - event.registered_count))

const progressTone = computed(() => {
    const p = fillPercent.value
    if (p >= 90) return { ring: 'text-destructive', label: 'Almost full', pill: 'bg-destructive/10 text-destructive' }
    if (p >= 60) return { ring: 'text-warning', label: 'Filling fast', pill: 'bg-warning/15 text-warning-foreground' }
    return { ring: 'text-success', label: 'Seats available', pill: 'bg-success/10 text-success' }
})

const statusPill = computed(() => {
    if (event.deleted_at) return { label: 'Archived', classes: 'bg-muted text-muted-foreground border-border' }
    if (event.status === 'published') return { label: 'Published', classes: 'bg-success/10 text-success border-success/20' }
    return { label: 'Draft', classes: 'bg-muted text-muted-foreground border-border' }
})

const metaBlocks = computed(() => [
    {
        title: 'Schedule',
        value: event.start_date === event.end_date ? formatDate(event.start_date) : `${formatDate(event.start_date)} — ${formatDate(event.end_date)}`,
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
        icon: Banknote,
    },
])

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

const cardShadow = 'shadow-[0_1px_2px_rgb(15_23_42/0.04),0_8px_24px_-12px_rgb(15_23_42/0.08)]'
</script>

<template>
    <Head :title="event.title" />
    <TooltipProvider :delay-duration="150">
        <div class="flex flex-col gap-8">
            <!-- HERO -->
            <section
                :class="[
                    'relative overflow-hidden rounded-3xl border border-border/60 bg-card ring-1 ring-black/5',
                    cardShadow,
                ]"
            >
                <div class="relative h-56 w-full sm:h-64 lg:h-80">
                    <EventBannerImage :src="event.banner_url" :alt="event.title" img-class="scale-[1.02]" />
                    <!-- Atmospheric gradient wash for readability -->
                    <div
                        class="absolute inset-0 bg-[linear-gradient(180deg,transparent_0%,transparent_40%,color-mix(in_oklab,var(--card)_55%,transparent)_72%,var(--card)_100%)]"
                    />
                    <!-- Top-left status pills over banner -->
                    <div class="absolute left-5 top-5 flex flex-wrap items-center gap-1.5 sm:left-7 sm:top-7">
                        <span
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium backdrop-blur-md',
                                'bg-background/70 border-border/70',
                                statusPill.classes,
                            ]"
                        >
                            <span class="size-1.5 rounded-full bg-current" />
                            {{ statusPill.label }}
                        </span>
                        <Badge
                            v-for="cat in parseCategories(event.category)"
                            :key="cat"
                            class="rounded-full border-0 px-2.5 py-1 text-[11px] font-medium text-white shadow-sm"
                            :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                        >
                            {{ categoryLabelMap[cat] ?? cat }}
                        </Badge>
                    </div>
                </div>

                <!-- Title block overlapping bottom of banner -->
                <div class="relative -mt-16 px-5 pb-6 sm:-mt-20 sm:px-8 sm:pb-8 lg:-mt-24">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="mb-2 flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.12em] text-primary">
                                <Sparkles class="size-3" />
                                Event Overview
                            </p>
                            <h1 class="text-balance text-3xl font-semibold leading-[1.15] tracking-tight text-foreground sm:text-4xl lg:text-[2.6rem]">
                                {{ event.title }}
                            </h1>
                            <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                                <span class="inline-flex items-center gap-1.5">
                                    <CalendarDays class="size-3.5" />
                                    {{ formatDate(event.start_date) }}
                                </span>
                                <span class="text-border">·</span>
                                <span class="inline-flex items-center gap-1.5">
                                    <MapPin class="size-3.5" />
                                    {{ event.location }}
                                </span>
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <Button variant="outline" size="sm" class="rounded-full" as-child>
                                <Link :href="`/dashboard/events/${event.id}/registrants`">
                                    <Users class="mr-1.5 size-3.5" />
                                    Registrants
                                </Link>
                            </Button>
                            <Button size="sm" class="rounded-full" as-child>
                                <Link :href="editEvent.url(event.id)">
                                    <Pencil class="mr-1.5 size-3.5" />
                                    Edit Event
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <!-- Floating meta chips -->
                    <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="m in metaBlocks"
                            :key="m.title"
                            :class="['group flex items-center gap-3 rounded-2xl border border-border/60 bg-background/70 p-3.5 backdrop-blur-sm transition-all hover:border-primary/30 hover:bg-background', cardShadow]"
                        >
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/8 text-primary ring-1 ring-primary/10 transition-colors group-hover:bg-primary/12">
                                <component :is="m.icon" class="size-[18px]" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">{{ m.title }}</p>
                                <p class="mt-0.5 truncate text-[13.5px] font-medium leading-snug text-foreground">{{ m.value }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- MAIN GRID -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- LEFT: primary content -->
                <div class="flex flex-col gap-6 lg:col-span-2">
                    <!-- Registration pulse card -->
                    <Card :class="['overflow-hidden rounded-2xl border-border/60', cardShadow]">
                        <CardContent class="grid gap-6 p-6 sm:grid-cols-[auto_1fr] sm:items-center sm:gap-8">
                            <!-- Circular progress -->
                            <div class="relative mx-auto size-36 shrink-0 sm:mx-0">
                                <svg viewBox="0 0 120 120" class="size-full -rotate-90">
                                    <circle cx="60" cy="60" r="52" stroke="currentColor" stroke-width="10" fill="none" class="text-muted/50" />
                                    <circle
                                        cx="60" cy="60" r="52"
                                        stroke="currentColor" stroke-width="10" fill="none"
                                        stroke-linecap="round"
                                        :stroke-dasharray="2 * Math.PI * 52"
                                        :stroke-dashoffset="2 * Math.PI * 52 * (1 - fillPercent / 100)"
                                        :class="[progressTone.ring, 'transition-[stroke-dashoffset] duration-700 ease-out']"
                                    />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ fillPercent }}%</span>
                                    <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">filled</span>
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-semibold tracking-tight text-foreground">Registration pulse</h3>
                                    <span :class="['inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium', progressTone.pill]">
                                        <span class="size-1.5 rounded-full bg-current" />
                                        {{ progressTone.label }}
                                    </span>
                                </div>
                                <p class="mt-2 text-[15px] leading-relaxed text-foreground/85">
                                    <span class="font-semibold tabular-nums">{{ event.registered_count.toLocaleString() }}</span>
                                    of
                                    <span class="font-semibold tabular-nums">{{ event.quota.toLocaleString() }}</span>
                                    seats are taken —
                                    <span class="text-muted-foreground">{{ remainingSeats.toLocaleString() }} still open.</span>
                                </p>

                                <!-- Dual timeline bars -->
                                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-xl border border-border/50 bg-muted/30 px-3.5 py-2.5">
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Opens</p>
                                        <p class="mt-0.5 text-[13px] font-medium text-foreground">{{ formatDateTime(event.registration_start) }}</p>
                                    </div>
                                    <div class="rounded-xl border border-border/50 bg-muted/30 px-3.5 py-2.5">
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Closes</p>
                                        <p class="mt-0.5 text-[13px] font-medium text-foreground">{{ formatDateTime(event.registration_end) }}</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Description -->
                    <Card :class="['rounded-2xl border-border/60', cardShadow]">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">About this event</CardTitle>
                                <span class="text-[11px] text-muted-foreground">Last updated {{ formatDate(event.updated_at) }}</span>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-0">
                            <div
                                class="prose prose-sm max-w-none text-pretty leading-relaxed text-foreground/90 prose-headings:font-semibold prose-headings:tracking-tight prose-headings:text-foreground prose-p:my-3 prose-ul:my-3 prose-li:my-0.5 prose-strong:text-foreground"
                                v-html="event.description"
                            />
                        </CardContent>
                    </Card>

                    <!-- Recent registrants preview -->
                    <Card :class="['rounded-2xl border-border/60', cardShadow]">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">Recent registrants</CardTitle>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        <span class="font-medium tabular-nums text-foreground">{{ totalRegistrants }}</span>
                                        {{ totalRegistrants === 1 ? 'person has' : 'people have' }} signed up so far.
                                    </p>
                                </div>
                                <Button variant="ghost" size="sm" class="h-8 gap-1 rounded-full text-xs" as-child>
                                    <Link :href="`/dashboard/events/${event.id}/registrants`">
                                        View all
                                        <ArrowUpRight class="size-3.5" />
                                    </Link>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-0">
                            <div v-if="previewRegistrants.length > 0" class="flex flex-col gap-1">
                                <Link
                                    v-for="reg in previewRegistrants"
                                    :key="reg.id"
                                    :href="`/dashboard/events/${event.id}/registrants`"
                                    class="group flex items-center justify-between rounded-xl px-2.5 py-2 transition-colors hover:bg-muted/50"
                                >
                                    <div class="flex min-w-0 items-center gap-3">
                                        <Avatar class="size-9 ring-2 ring-background">
                                            <AvatarImage :src="reg.user.avatar ?? ''" :alt="reg.user.name" />
                                            <AvatarFallback class="bg-primary/10 text-[11px] font-semibold text-primary">
                                                {{ getInitials(reg.user.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-foreground">{{ reg.user.name }}</p>
                                            <p class="truncate text-xs text-muted-foreground">{{ reg.user.email }}</p>
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-3">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10.5px] font-medium capitalize',
                                                reg.status === 'approved' && 'bg-success/10 text-success',
                                                reg.status === 'pending' && 'bg-warning/15 text-warning-foreground',
                                                reg.status === 'rejected' && 'bg-destructive/10 text-destructive',
                                            ]"
                                        >
                                            <span class="size-1.5 rounded-full bg-current" />
                                            {{ reg.status }}
                                        </span>
                                        <span class="hidden text-[11px] tabular-nums text-muted-foreground sm:inline">
                                            {{ formatDate(reg.submitted_at) }}
                                        </span>
                                    </div>
                                </Link>
                            </div>
                            <p v-else class="px-2 py-6 text-center text-sm text-muted-foreground">
                                No registrants yet — share your event to get things rolling.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- RIGHT: sticky action rail -->
                <aside class="flex flex-col gap-5 lg:sticky lg:top-20 lg:self-start">
                    <!-- Primary actions -->
                    <Card :class="['rounded-2xl border-border/60', cardShadow]">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">Manage event</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-2 pt-0">
                            <Button class="w-full justify-start rounded-xl" as-child>
                                <Link :href="editEvent.url(event.id)"><Pencil class="mr-2 size-4" />Edit details</Link>
                            </Button>
                            <Button variant="outline" class="w-full justify-start rounded-xl" as-child>
                                <Link :href="`/dashboard/events/${event.id}/scan`"><QrCode class="mr-2 size-4" />Check-in scanner</Link>
                            </Button>
                            <Button variant="outline" class="w-full justify-start rounded-xl" as-child>
                                <Link :href="`/dashboard/events/${event.id}/registrants`"><Users class="mr-2 size-4" />Manage registrants</Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Data utilities -->
                    <Card :class="['rounded-2xl border-border/60', cardShadow]">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">Data</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-2 pt-0">
                            <div class="grid grid-cols-2 gap-2">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button variant="outline" size="sm" class="rounded-xl" @click="handleExport('CSV')">
                                            <Download class="mr-1.5 size-3.5" />CSV
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Export registrants as CSV</TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button variant="outline" size="sm" class="rounded-xl" @click="handleExport('Excel')">
                                            <FileSpreadsheet class="mr-1.5 size-3.5" />Excel
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Export registrants as XLSX</TooltipContent>
                                </Tooltip>
                            </div>
                            <Button variant="outline" size="sm" class="w-full justify-start rounded-xl" @click="showImportModal = true">
                                <Upload class="mr-2 size-4" />Import registrants
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Forms -->
                    <Card :class="['rounded-2xl border-border/60', cardShadow]">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">Forms</CardTitle>
                                <span class="text-[11px] font-medium tabular-nums text-muted-foreground">{{ forms.length }}</span>
                            </div>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-2 pt-0">
                            <Button variant="outline" size="sm" class="w-full justify-start rounded-xl" as-child>
                                <Link :href="`/dashboard/events/${event.id}/forms`"><FileText class="mr-2 size-4" />Manage forms</Link>
                            </Button>
                            <div v-if="forms.length > 0" class="mt-1 flex flex-col gap-1">
                                <div
                                    v-for="form in forms"
                                    :key="form.id"
                                    class="flex items-center gap-2 rounded-xl border border-border/50 bg-muted/30 px-3 py-2 text-xs"
                                >
                                    <FileText class="size-3.5 shrink-0 text-muted-foreground" />
                                    <span class="truncate font-medium text-foreground">{{ form.title }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Danger zone -->
                    <Card :class="['rounded-2xl border-border/60', cardShadow]">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">Lifecycle</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-2 pt-0">
                            <Button
                                v-if="!event.deleted_at"
                                variant="outline"
                                size="sm"
                                class="w-full justify-start rounded-xl border-destructive/20 text-destructive hover:bg-destructive/5 hover:text-destructive"
                                @click="showDeleteModal = true"
                            >
                                <Trash2 class="mr-2 size-4" />Archive event
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                size="sm"
                                class="w-full justify-start rounded-xl"
                                @click="showRestoreModal = true"
                            >
                                <RotateCcw class="mr-2 size-4" />Restore event
                            </Button>
                            <Separator class="my-1" />
                            <p class="px-1 text-[11px] leading-relaxed text-muted-foreground">
                                Archiving hides this event from the public but keeps all registrant data safe. You can restore it anytime.
                            </p>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </div>
    </TooltipProvider>

    <ConfirmationModal
        :open="showDeleteModal"
        title="Archive this event?"
        description="It will be hidden from the public, but all registrant data stays intact. You can restore it whenever you’re ready."
        confirm-text="Archive"
        variant="destructive"
        :loading="isDeleting"
        @confirm="handleDelete"
        @cancel="showDeleteModal = false"
        @update:open="showDeleteModal = $event"
    />
    <ConfirmationModal
        :open="showRestoreModal"
        title="Restore this event?"
        description="It will become visible again and accept registrations based on its current schedule."
        confirm-text="Restore"
        :loading="isRestoring"
        @confirm="handleRestore"
        @cancel="showRestoreModal = false"
        @update:open="showRestoreModal = $event"
    />

    <Dialog :open="showImportModal" @update:open="showImportModal = $event">
        <DialogContent class="max-w-md rounded-2xl">
            <DialogHeader>
                <DialogTitle>Import registrants</DialogTitle>
                <DialogDescription>Upload a CSV or Excel file to bring existing registrants into this event.</DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-4 pt-2">
                <div
                    class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-border px-6 py-10 text-center transition-colors hover:border-primary/50 hover:bg-muted/30"
                    @click="($refs.importInput as HTMLInputElement)?.click()"
                >
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <Upload class="size-5" />
                    </div>
                    <p class="mt-3 text-sm font-medium">{{ importFile ? importFile.name : 'Click to select a file' }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">CSV, XLSX up to 5 MB</p>
                    <input ref="importInput" type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="handleImportFileChange" />
                </div>
                <div class="flex items-center justify-between">
                    <Button variant="link" size="sm" class="h-auto p-0 text-xs" @click="toast.info('Downloading template...')">
                        <Download class="mr-1 size-3" />Download template
                    </Button>
                    <Button class="rounded-full" :disabled="!importFile" @click="handleImport">Import</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
