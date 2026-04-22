<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue'
import EmptyState from '@/components/modules/dashboard/EmptyState.vue'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import {
    Search, CheckCircle2, XCircle, Eye, Download, Users,
    Clock, ShieldCheck, ShieldX, ArrowUpRight, Sparkles, CalendarDays, MapPin,
} from 'lucide-vue-next'
import { dummyRegistrants, dummyEvents, formatDate, formatDateTime } from '@/lib/dummyData'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event?: IEvent
    registrants?: IRegistrant[]
}>()

const event = props.event ?? dummyEvents[0]

const searchQuery = ref('')
const activeStatusTab = ref<'all' | 'pending' | 'approved' | 'rejected'>('all')
const registrants = ref<IRegistrant[]>([...(props.registrants ?? dummyRegistrants)])

const selectedRegistrant = ref<IRegistrant | null>(null)
const showDetailSheet = ref(false)
const showApproveModal = ref(false)
const showRejectModal = ref(false)
const actionTarget = ref<IRegistrant | null>(null)

const filteredRegistrants = computed(() => {
    let list = registrants.value

    if (activeStatusTab.value !== 'all') {
        list = list.filter((r) => r.status === activeStatusTab.value)
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase()
        list = list.filter((r) =>
            r.user.name.toLowerCase().includes(q) || r.user.email.toLowerCase().includes(q),
        )
    }

    return list
})

const statusCounts = computed(() => ({
    all: registrants.value.length,
    pending: registrants.value.filter((r) => r.status === 'pending').length,
    approved: registrants.value.filter((r) => r.status === 'approved').length,
    rejected: registrants.value.filter((r) => r.status === 'rejected').length,
}))

const approvalRate = computed(() => {
    const decided = statusCounts.value.approved + statusCounts.value.rejected
    if (!decided) return 0
    return Math.round((statusCounts.value.approved / decided) * 100)
})

const statCards = computed(() => [
    {
        key: 'all',
        label: 'Total registrants',
        helper: statusCounts.value.all === 1 ? 'submission received' : 'submissions received',
        value: statusCounts.value.all,
        icon: Users,
        tone: 'primary' as const,
    },
    {
        key: 'pending',
        label: 'Awaiting review',
        helper: statusCounts.value.pending === 1 ? 'person needs a decision' : 'people need a decision',
        value: statusCounts.value.pending,
        icon: Clock,
        tone: 'warning' as const,
    },
    {
        key: 'approved',
        label: 'Approved',
        helper: `${approvalRate.value}% approval rate`,
        value: statusCounts.value.approved,
        icon: ShieldCheck,
        tone: 'success' as const,
    },
    {
        key: 'rejected',
        label: 'Rejected',
        helper: statusCounts.value.rejected === 1 ? 'decision recorded' : 'decisions recorded',
        value: statusCounts.value.rejected,
        icon: ShieldX,
        tone: 'destructive' as const,
    },
])

const toneStyles: Record<'primary' | 'warning' | 'success' | 'destructive', {
    chip: string
    ring: string
    bar: string
    dot: string
}> = {
    primary: {
        chip: 'bg-primary/10 text-primary ring-primary/15',
        ring: 'ring-primary/15',
        bar: 'bg-primary',
        dot: 'bg-primary',
    },
    warning: {
        chip: 'bg-warning/15 text-warning-foreground ring-warning/25',
        ring: 'ring-warning/20',
        bar: 'bg-warning',
        dot: 'bg-warning',
    },
    success: {
        chip: 'bg-success/10 text-success ring-success/20',
        ring: 'ring-success/15',
        bar: 'bg-success',
        dot: 'bg-success',
    },
    destructive: {
        chip: 'bg-destructive/10 text-destructive ring-destructive/20',
        ring: 'ring-destructive/15',
        bar: 'bg-destructive',
        dot: 'bg-destructive',
    },
}

const statusBadge = (s: IRegistrant['status']) => {
    if (s === 'approved') return 'bg-success/10 text-success ring-success/15'
    if (s === 'rejected') return 'bg-destructive/10 text-destructive ring-destructive/15'
    return 'bg-warning/15 text-warning-foreground ring-warning/20'
}

function openDetail(reg: IRegistrant) {
    selectedRegistrant.value = reg
    showDetailSheet.value = true
}

function startApprove(reg: IRegistrant) {
    actionTarget.value = reg
    showApproveModal.value = true
}

function startReject(reg: IRegistrant) {
    actionTarget.value = reg
    showRejectModal.value = true
}

function confirmApprove() {
    if (actionTarget.value) {
        actionTarget.value.status = 'approved'
        toast.success(`${actionTarget.value.user.name} is in — approval email queued.`)
    }
    showApproveModal.value = false
}

function confirmReject() {
    if (actionTarget.value) {
        actionTarget.value.status = 'rejected'
        toast.success(`${actionTarget.value.user.name} has been declined — notification queued.`)
    }
    showRejectModal.value = false
}

function getInitials(name: string): string {
    return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}

function relativeTime(dateStr: string): string {
    const diff = Date.now() - new Date(dateStr).getTime()
    const minutes = Math.floor(diff / 60000)
    if (minutes < 1) return 'just now'
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.floor(hours / 24)
    if (days < 7) return `${days}d ago`
    return formatDate(dateStr)
}

const cardShadow = 'shadow-[0_1px_2px_rgb(15_23_42/0.04),0_8px_24px_-12px_rgb(15_23_42/0.08)]'

const pendingCount = computed(() => statusCounts.value.pending)

const tabItems: { value: 'all' | 'pending' | 'approved' | 'rejected'; label: string; tone: 'default' | 'warning' | 'success' | 'destructive' }[] = [
    { value: 'all', label: 'All', tone: 'default' },
    { value: 'pending', label: 'Pending', tone: 'warning' },
    { value: 'approved', label: 'Approved', tone: 'success' },
    { value: 'rejected', label: 'Rejected', tone: 'destructive' },
]
</script>

<template>
    <Head :title="`Registrants — ${event.title}`" />
    <TooltipProvider :delay-duration="150">
        <div class="flex flex-col gap-7">
            <!-- CONTEXT HEADER (event reminder strip) -->
            <section
                :class="[
                    'relative flex items-stretch gap-4 overflow-hidden rounded-3xl border border-border/60 bg-card p-4 ring-1 ring-black/5 sm:gap-5 sm:p-5',
                    cardShadow,
                ]"
            >
                <div class="hidden h-28 w-44 shrink-0 overflow-hidden rounded-2xl ring-1 ring-black/5 sm:block">
                    <EventBannerImage :src="event.banner_url" :alt="event.title" />
                </div>
                <div class="flex min-w-0 flex-1 flex-col justify-center gap-1.5">
                    <p class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.12em] text-primary">
                        <Sparkles class="size-3" />
                        Registrants Hub
                    </p>
                    <h1 class="truncate text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                        {{ event.title }}
                    </h1>
                    <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-1.5">
                            <CalendarDays class="size-3.5" />
                            {{ formatDate(event.start_date) }}
                        </span>
                        <span class="text-border">·</span>
                        <span class="inline-flex items-center gap-1.5">
                            <MapPin class="size-3.5" />
                            {{ event.location }}
                        </span>
                        <span class="text-border">·</span>
                        <span class="inline-flex items-center gap-1.5">
                            <Users class="size-3.5" />
                            <span class="tabular-nums"><span class="font-medium text-foreground">{{ event.registered_count }}</span> / {{ event.quota }} seats</span>
                        </span>
                    </p>
                </div>
                <div class="hidden shrink-0 items-center sm:flex">
                    <Button variant="outline" size="sm" class="rounded-full" as-child>
                        <Link :href="`/dashboard/events/${event.id}`">
                            View event
                            <ArrowUpRight class="ml-1 size-3.5" />
                        </Link>
                    </Button>
                </div>
            </section>

            <!-- STAT CARDS -->
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <button
                    v-for="stat in statCards"
                    :key="stat.key"
                    type="button"
                    :class="[
                        'group relative flex items-start gap-4 overflow-hidden rounded-2xl border border-border/60 bg-card p-5 text-left transition-all',
                        'hover:-translate-y-0.5 hover:border-primary/30',
                        cardShadow,
                        activeStatusTab === stat.key ? 'ring-2 ring-primary/40' : 'ring-1 ring-transparent',
                    ]"
                    @click="activeStatusTab = stat.key as typeof activeStatusTab"
                >
                    <span :class="['absolute inset-x-0 top-0 h-1', toneStyles[stat.tone].bar]" />
                    <div
                        :class="[
                            'flex size-11 shrink-0 items-center justify-center rounded-xl ring-1',
                            toneStyles[stat.tone].chip,
                            toneStyles[stat.tone].ring,
                        ]"
                    >
                        <component :is="stat.icon" class="size-[19px]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">{{ stat.label }}</p>
                        <p class="mt-1 text-[28px] font-semibold leading-none tabular-nums tracking-tight text-foreground">
                            {{ stat.value.toLocaleString() }}
                        </p>
                        <p class="mt-1.5 text-[11.5px] leading-snug text-muted-foreground">{{ stat.helper }}</p>
                    </div>
                </button>
            </section>

            <!-- TOOLBAR -->
            <section class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <Tabs v-model="activeStatusTab" class="w-full md:w-auto">
                    <TabsList class="h-10 rounded-full bg-muted/60 p-1">
                        <TabsTrigger
                            v-for="t in tabItems"
                            :key="t.value"
                            :value="t.value"
                            class="gap-1.5 rounded-full px-4 text-xs font-medium data-[state=active]:shadow-sm"
                        >
                            {{ t.label }}
                            <span
                                :class="[
                                    'rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums',
                                    t.value === 'pending' && 'bg-warning/15 text-warning-foreground',
                                    t.value === 'approved' && 'bg-success/10 text-success',
                                    t.value === 'rejected' && 'bg-destructive/10 text-destructive',
                                    t.value === 'all' && 'bg-muted text-muted-foreground',
                                ]"
                            >
                                {{ statusCounts[t.value] }}
                            </span>
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                <div class="flex items-center gap-2">
                    <div class="relative w-full md:w-64">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search name or email…"
                            class="h-10 rounded-full border-border/60 bg-card pl-9 shadow-sm focus-visible:ring-primary/30"
                        />
                    </div>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="outline" size="icon" class="size-10 rounded-full" @click="toast.info('Exporting as CSV…')">
                                <Download class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Export visible list as CSV</TooltipContent>
                    </Tooltip>
                </div>
            </section>

            <!-- Pending nudge -->
            <div
                v-if="pendingCount > 0 && activeStatusTab !== 'pending'"
                class="flex items-start gap-3 rounded-2xl border border-warning/20 bg-warning/5 px-4 py-3"
            >
                <div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl bg-warning/15 text-warning-foreground">
                    <Clock class="size-4" />
                </div>
                <div class="flex-1 text-sm">
                    <p class="font-medium text-foreground">
                        {{ pendingCount }} {{ pendingCount === 1 ? 'person is' : 'people are' }} waiting for your decision.
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">A quick review helps them plan — most registrants hope for a response within 24 hours.</p>
                </div>
                <Button variant="ghost" size="sm" class="rounded-full" @click="activeStatusTab = 'pending'">
                    Review now
                    <ArrowUpRight class="ml-1 size-3.5" />
                </Button>
            </div>

            <!-- TABLE -->
            <section
                v-if="filteredRegistrants.length > 0"
                :class="['overflow-hidden rounded-2xl border border-border/60 bg-card', cardShadow]"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border/60 bg-muted/40 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                <th class="px-5 py-3.5">Registrant</th>
                                <th class="px-5 py-3.5">Status</th>
                                <th class="px-5 py-3.5">Submitted</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="reg in filteredRegistrants"
                                :key="reg.id"
                                class="group border-b border-border/40 transition-colors last:border-0 hover:bg-muted/30"
                            >
                                <td class="px-5 py-3.5">
                                    <button type="button" class="flex items-center gap-3 text-left" @click="openDetail(reg)">
                                        <Avatar class="size-9 ring-2 ring-background transition-transform group-hover:scale-[1.03]">
                                            <AvatarImage :src="reg.user.avatar ?? ''" :alt="reg.user.name" />
                                            <AvatarFallback class="bg-primary/10 text-[11px] font-semibold text-primary">
                                                {{ getInitials(reg.user.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-foreground">{{ reg.user.name }}</p>
                                            <p class="truncate text-xs text-muted-foreground">{{ reg.user.email }}</p>
                                        </div>
                                    </button>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium capitalize ring-1',
                                            statusBadge(reg.status),
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current" />
                                        {{ reg.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-medium text-foreground">{{ relativeTime(reg.submitted_at) }}</span>
                                        <span class="text-[11px] text-muted-foreground">{{ formatDateTime(reg.submitted_at) }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button variant="ghost" size="icon" class="size-8 rounded-full" @click="openDetail(reg)">
                                                    <Eye class="size-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>View submission</TooltipContent>
                                        </Tooltip>
                                        <template v-if="reg.status === 'pending'">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="size-8 rounded-full text-success hover:bg-success/10 hover:text-success"
                                                        @click="startApprove(reg)"
                                                    >
                                                        <CheckCircle2 class="size-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Approve</TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="size-8 rounded-full text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                        @click="startReject(reg)"
                                                    >
                                                        <XCircle class="size-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Reject</TooltipContent>
                                            </Tooltip>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <EmptyState
                v-else
                title="No registrants match your view"
                description="Try switching tabs or clearing the search. Registrants appear here the moment they submit the form."
                animation-url="https://lottie.host/4e039bf3-670e-4a0f-8a6c-1bee793bfc23/JkaGBMIxOz.json"
            />
        </div>
    </TooltipProvider>

    <!-- DETAIL SHEET (right drawer) -->
    <Sheet :open="showDetailSheet" @update:open="showDetailSheet = $event">
        <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader class="space-y-0">
                <SheetTitle class="text-base font-semibold">Submission details</SheetTitle>
                <SheetDescription class="text-xs">Review the answers submitted by this registrant.</SheetDescription>
            </SheetHeader>

            <div v-if="selectedRegistrant" class="flex flex-col gap-5 px-4 pb-4">
                <!-- Identity -->
                <div class="flex items-center gap-3 rounded-2xl border border-border/60 bg-muted/30 p-3.5">
                    <Avatar class="size-12 ring-2 ring-background">
                        <AvatarImage :src="selectedRegistrant.user.avatar ?? ''" :alt="selectedRegistrant.user.name" />
                        <AvatarFallback class="bg-primary/10 text-sm font-semibold text-primary">
                            {{ getInitials(selectedRegistrant.user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-foreground">{{ selectedRegistrant.user.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ selectedRegistrant.user.email }}</p>
                    </div>
                    <span
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium capitalize ring-1',
                            statusBadge(selectedRegistrant.status),
                        ]"
                    >
                        <span class="size-1.5 rounded-full bg-current" />
                        {{ selectedRegistrant.status }}
                    </span>
                </div>

                <div class="flex items-center gap-2 text-[11px] text-muted-foreground">
                    <Clock class="size-3" />
                    Submitted {{ relativeTime(selectedRegistrant.submitted_at) }} · {{ formatDateTime(selectedRegistrant.submitted_at) }}
                </div>

                <!-- Answers -->
                <div class="flex flex-col gap-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">Form answers</p>
                    <div class="flex flex-col divide-y divide-border/50 rounded-2xl border border-border/60">
                        <div
                            v-for="(value, key) in selectedRegistrant.answers"
                            :key="key"
                            class="flex flex-col gap-1 px-4 py-3"
                        >
                            <p class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">{{ key }}</p>
                            <p class="text-sm leading-relaxed text-foreground">{{ value || '—' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div v-if="selectedRegistrant.status === 'pending'" class="flex items-center gap-2 pt-2">
                    <Button
                        class="flex-1 rounded-xl bg-success text-success-foreground hover:bg-success/90"
                        @click="() => { showDetailSheet = false; startApprove(selectedRegistrant!) }"
                    >
                        <CheckCircle2 class="mr-1.5 size-4" />
                        Approve
                    </Button>
                    <Button
                        variant="outline"
                        class="flex-1 rounded-xl border-destructive/30 text-destructive hover:bg-destructive/5 hover:text-destructive"
                        @click="() => { showDetailSheet = false; startReject(selectedRegistrant!) }"
                    >
                        <XCircle class="mr-1.5 size-4" />
                        Reject
                    </Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>

    <ConfirmationModal
        :open="showApproveModal"
        title="Approve this registrant?"
        :description="`We’ll mark ${actionTarget?.user.name} as approved and send them a confirmation email.`"
        confirm-text="Approve"
        @confirm="confirmApprove"
        @cancel="showApproveModal = false"
        @update:open="showApproveModal = $event"
    />
    <ConfirmationModal
        :open="showRejectModal"
        title="Reject this registrant?"
        :description="`We’ll let ${actionTarget?.user.name} know their registration wasn’t accepted this time.`"
        confirm-text="Reject"
        variant="destructive"
        @confirm="confirmReject"
        @cancel="showRejectModal = false"
        @update:open="showRejectModal = $event"
    />
</template>
