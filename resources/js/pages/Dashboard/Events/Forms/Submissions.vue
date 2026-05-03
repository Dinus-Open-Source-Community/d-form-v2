<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import KpiCard from '@/components/modules/dashboard/KpiCard.vue';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import {
    FileText,
    Inbox,
    LayoutGrid,
    List,
    Clock,
    Eye,
    Users,
    CalendarClock,
    Download,
    CheckCircle2,
    XCircle,
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

defineOptions({ layout: DashboardFocusLayout });

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface SubmissionPaginator {
    data: IFormSubmission[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links?: PaginationLink[];
}

const props = defineProps<{
    event: { id: string; title: string };
    form: { id: string; title: string };
    fields: IFormField[];
    submissions: SubmissionPaginator;
}>();

const viewType = ref<'table' | 'form'>('table');
const selectedSubmission = ref<IFormSubmission | null>(null);
const isDetailOpen = ref(false);

const fieldLabelMap = computed(() => {
    const map: Record<string, string> = {};
    props.fields.forEach((field) => {
        map[field.name] = field.label;
    });
    return map;
});

const answerKeys = computed(() => {
    // Prefer keys from fields first to maintain order
    const keysFromFields = props.fields.map((f) => f.name);

    // Add any other keys found in submissions that might not be in fields (fallback)
    const keysInSubmissions = new Set<string>();
    for (const submission of props.submissions.data) {
        Object.keys(submission.answers).forEach((key) => keysInSubmissions.add(key));
    }

    const allKeys = [...new Set([...keysFromFields, ...keysInSubmissions])];
    return allKeys.slice(0, 4); // Show fewer columns in table for better readability
});

const allAnswerKeys = computed(() => {
    const keysFromFields = props.fields.map((f) => f.name);
    const keysInSubmissions = new Set<string>();
    for (const submission of props.submissions.data) {
        Object.keys(submission.answers).forEach((key) => keysInSubmissions.add(key));
    }
    return [...new Set([...keysFromFields, ...keysInSubmissions])];
});

const latestSubmissionDate = computed(() => {
    if (props.submissions.data.length === 0) return '-';
    const dates = props.submissions.data.map((s) => new Date(s.submitted_at).getTime());
    return formatDate(new Date(Math.max(...dates)).toISOString());
});

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((w) => w[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function humanizeKey(value: string): string {
    return fieldLabelMap.value[value] || value.replace(/^field_/, '').replace(/_/g, ' ');
}

function answerPreview(value: unknown): string {
    if (Array.isArray(value)) return value.map(String).join(', ');
    if (typeof value === 'string') return value;
    if (value === null || value === undefined) return 'Empty';
    if (typeof value === 'number' || typeof value === 'boolean') return String(value);
    return 'Structured answer';
}

function fileUrl(value: unknown): string | null {
    return typeof value === 'string' && value.includes('/') ? `/storage/${value}` : null;
}

function paginationLabel(value: string): string {
    return value.replace('&laquo;', 'Previous').replace('&raquo;', 'Next');
}

function openDetail(submission: IFormSubmission) {
    selectedSubmission.value = submission;
    isDetailOpen.value = true;
}

/** Approve / reject submission — backend API not implemented yet (no column or routes). */
function submissionReviewStub(action: 'accept' | 'reject', submission: IFormSubmission) {
    const title = action === 'accept' ? 'Terima pendaftar' : 'Tolak pendaftar';
    toast.info(title, {
        description: `Aksi untuk “${submission.user?.name ?? submission.user?.email ?? submission.id}” belum tersedia. Model form_answers belum memiliki status review. Lihat docs/milestone-m1-m4-remaining.md.`,
        duration: 8000,
    });
}
</script>

<template>
    <Head :title="`${form.title} Submissions`" />

    <div class="neo-page">
        <div class="flex flex-col gap-8">
            <!-- ─── Page Header ────────────────────────────────────────────── -->
            <PageHeader
                :title="form.title"
                :subtitle="`Collected responses for ${event.title}`"
                :back-href="`/dashboard/events/${event.id}/forms/${form.id}`"
            >
                <template #actions>
                    <div class="flex items-center gap-3">
                        <Tabs v-model="viewType" class="w-auto">
                            <TabsList class="border-foreground/20 bg-muted/30 h-10 border-[1.5px] p-1">
                                <TabsTrigger
                                    value="table"
                                    class="h-7 px-3 text-[10px] font-black tracking-widest uppercase"
                                >
                                    <List class="mr-1.5 size-3.5" />
                                    Table
                                </TabsTrigger>
                                <TabsTrigger
                                    value="form"
                                    class="h-7 px-3 text-[10px] font-black tracking-widest uppercase"
                                >
                                    <LayoutGrid class="mr-1.5 size-3.5" />
                                    Cards
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                        <Button variant="outline" size="sm" class="h-10 gap-2 border-2 px-4 font-bold">
                            <Download class="size-4" />
                            Export
                        </Button>
                    </div>
                </template>
            </PageHeader>

            <!-- ─── KPI Summary ────────────────────────────────────────────── -->
            <div v-if="submissions.total > 0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <KpiCard
                    label="Total Submissions"
                    :value="submissions.total"
                    :icon="Users"
                    color="primary"
                    class="border-2"
                />
                <KpiCard
                    label="Latest Response"
                    :value="latestSubmissionDate"
                    :icon="CalendarClock"
                    color="warning"
                    class="border-2"
                />
            </div>

            <!-- ─── Empty State ────────────────────────────────────────────── -->
            <div
                v-if="submissions.data.length === 0"
                class="neo-muted-panel flex flex-col items-center justify-center py-24 text-center"
            >
                <div class="neo-surface flex size-16 items-center justify-center bg-white shadow-sm">
                    <Inbox class="text-muted-foreground/30 size-8" />
                </div>
                <h3 class="mt-6 text-xl font-black tracking-tight">No responses yet</h3>
                <p class="text-muted-foreground mt-2 max-w-sm text-sm font-semibold">
                    When people submit this form, you'll see their detailed answers here in real-time.
                </p>
            </div>

            <template v-else>
                <!-- ─── Table View ─────────────────────────────────────────────── -->
                <div v-if="viewType === 'table'" class="neo-surface overflow-hidden bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow class="hover:bg-transparent">
                                    <TableHead
                                        class="bg-muted/40 text-muted-foreground sticky left-0 z-20 h-14 px-6 text-[10px] font-black tracking-widest uppercase"
                                    >
                                        Member
                                    </TableHead>
                                    <TableHead
                                        v-for="key in answerKeys"
                                        :key="key"
                                        class="bg-muted/20 text-muted-foreground h-14 min-w-[180px] px-6 text-[10px] font-black tracking-widest uppercase"
                                    >
                                        {{ humanizeKey(key) }}
                                    </TableHead>
                                    <TableHead
                                        class="bg-muted/20 text-muted-foreground h-14 px-6 text-[10px] font-black tracking-widest uppercase"
                                    >
                                        Submitted
                                    </TableHead>
                                    <TableHead
                                        class="bg-muted/20 text-muted-foreground h-14 px-6 text-center text-[10px] font-black tracking-widest uppercase"
                                    >
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="(submission, idx) in submissions.data"
                                    :key="submission.id"
                                    class="border-border/10 hover:bg-muted/10 animate-brutal-pop border-b-[1.5px] transition-colors"
                                    :style="{ animationDelay: `${idx * 50}ms` }"
                                >
                                    <TableCell
                                        class="border-border/10 sticky left-0 z-10 border-r-[1.5px] bg-white px-6 py-4"
                                    >
                                        <div class="flex items-center gap-3">
                                            <Avatar class="size-9 rounded-lg border-2 border-foreground/10">
                                                <AvatarImage :src="''" :alt="submission.user?.name ?? 'U'" />
                                                <AvatarFallback class="bg-primary/10 text-primary rounded-lg text-xs font-black">
                                                    {{ getInitials(submission.user?.name ?? 'An') }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div class="min-w-0">
                                                <p class="font-display truncate text-sm font-extrabold tracking-tight">
                                                    {{ submission.user?.name ?? 'Anonymous' }}
                                                </p>
                                                <p class="text-muted-foreground truncate text-[10px] font-bold">
                                                    {{ submission.user?.email ?? '-' }}
                                                </p>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        v-for="key in answerKeys"
                                        :key="key"
                                        class="max-w-[240px] px-6 py-4 text-xs leading-relaxed font-semibold"
                                    >
                                        <div v-if="fileUrl(submission.answers[key])" class="flex items-center gap-1.5 text-primary">
                                            <FileText class="size-3.5" />
                                            <span class="font-bold underline underline-offset-4">Attachment</span>
                                        </div>
                                        <span v-else class="line-clamp-2 text-foreground/80">
                                            {{ answerPreview(submission.answers[key]) }}
                                        </span>
                                    </TableCell>
                                    <TableCell
                                        class="text-muted-foreground px-6 py-4 text-[10px] font-bold whitespace-nowrap"
                                    >
                                        {{ formatDate(submission.submitted_at) }}
                                    </TableCell>
                                    <TableCell class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                title="Terima (UI only — backend belum ada)"
                                                class="size-8 rounded-lg border-2 border-transparent text-success transition-all hover:border-success/30 hover:bg-success/10"
                                                @click="submissionReviewStub('accept', submission)"
                                            >
                                                <CheckCircle2 class="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                title="Tolak (UI only — backend belum ada)"
                                                class="size-8 rounded-lg border-2 border-transparent text-destructive transition-all hover:border-destructive/30 hover:bg-destructive/10"
                                                @click="submissionReviewStub('reject', submission)"
                                            >
                                                <XCircle class="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-8 rounded-lg hover:bg-primary/10 hover:text-primary border-2 border-transparent transition-all hover:border-primary/20"
                                                @click="openDetail(submission)"
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

                <!-- ─── Card View ──────────────────────────────────────────────── -->
                <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(submission, idx) in submissions.data"
                        :key="submission.id"
                        class="brutal-card animate-brutal-pop flex flex-col p-6"
                        :style="{ animationDelay: `${idx * 100}ms` }"
                    >
                        <div class="border-border/10 flex items-center gap-3 border-b-[1.5px] pb-5">
                            <Avatar class="size-11 rounded-xl border-2 border-foreground/10">
                                <AvatarFallback class="bg-primary/10 text-primary rounded-xl text-sm font-black">
                                    {{ getInitials(submission.user?.name ?? 'An') }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="min-w-0 flex-1">
                                <h4 class="font-display truncate text-base leading-none font-extrabold tracking-tight">
                                    {{ submission.user?.name ?? 'Anonymous' }}
                                </h4>
                                <p class="text-muted-foreground mt-1.5 truncate text-xs font-bold">
                                    {{ submission.user?.email ?? 'No email address' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex-1 space-y-5 pt-5">
                            <div v-for="key in answerKeys" :key="key" class="space-y-1.5">
                                <div class="text-muted-foreground text-[10px] font-black tracking-widest uppercase">
                                    {{ humanizeKey(key) }}
                                </div>
                                <div class="text-xs leading-relaxed font-bold">
                                    <div v-if="fileUrl(submission.answers[key])" class="flex items-center gap-2 text-primary">
                                        <FileText class="size-4" />
                                        <span class="underline underline-offset-4">Attached Document</span>
                                    </div>
                                    <span v-else class="text-foreground/90 line-clamp-2 break-words">
                                        {{ answerPreview(submission.answers[key]) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col gap-4">
                            <div class="flex items-center justify-between text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                <span class="flex items-center gap-1.5"><Clock class="size-3" /> Submitted</span>
                                <span>{{ formatDate(submission.submitted_at) }}</span>
                            </div>
                            <div class="flex flex-col gap-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="gap-1 border-2 border-success/40 font-black text-success hover:bg-success/10"
                                        @click="submissionReviewStub('accept', submission)"
                                    >
                                        <CheckCircle2 class="size-4" />
                                        Accept
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="gap-1 border-2 border-destructive/40 font-black text-destructive hover:bg-destructive/10"
                                        @click="submissionReviewStub('reject', submission)"
                                    >
                                        <XCircle class="size-4" />
                                        Reject
                                    </Button>
                                </div>
                                <Button
                                    variant="outline"
                                    class="w-full gap-2 border-2 font-black tracking-widest uppercase"
                                    @click="openDetail(submission)"
                                >
                                    <Eye class="size-4" />
                                    View Details
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── Pagination ─────────────────────────────────────────────── -->
                <div
                    v-if="submissions.links && submissions.last_page > 1"
                    class="flex items-center justify-center gap-2 pt-8"
                >
                    <Button
                        v-for="link in submissions.links"
                        :key="link.label"
                        variant="outline"
                        size="sm"
                        class="hover:bg-primary/5 h-10 min-w-10 rounded-xl font-black tracking-widest uppercase transition-all"
                        :class="[
                            link.active ? 'bg-primary/10 !border-primary text-primary !shadow-none' : 'bg-white',
                            !link.url ? 'opacity-30' : '',
                        ]"
                        :disabled="!link.url"
                        as-child
                    >
                        <Link v-if="link.url" :href="link.url">{{ paginationLabel(link.label) }}</Link>
                        <span v-else>{{ paginationLabel(link.label) }}</span>
                    </Button>
                </div>
            </template>
        </div>
    </div>

    <!-- ─── Submission Detail Slideover ─────────────────────────────────── -->
    <Sheet v-model:open="isDetailOpen">
        <SheetContent class="sm:max-w-md md:max-w-lg">
            <SheetHeader class="border-b-[1.5px] border-foreground/10 pb-6">
                <div class="flex items-center gap-4">
                    <Avatar class="size-14 rounded-2xl border-2 border-foreground/10">
                        <AvatarFallback class="bg-primary/10 text-primary rounded-2xl text-lg font-black">
                            {{ getInitials(selectedSubmission?.user?.name ?? 'An') }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="flex-1 min-w-0">
                        <SheetTitle class="font-display text-2xl font-black tracking-tight leading-none">
                            Submission Details
                        </SheetTitle>
                        <SheetDescription class="mt-2 text-sm font-bold text-muted-foreground truncate">
                            From {{ selectedSubmission?.user?.name ?? 'Anonymous' }}
                        </SheetDescription>
                    </div>
                </div>
            </SheetHeader>

            <div class="mt-8 space-y-8 overflow-y-auto px-1 pb-20 pr-3">
                <!-- Submitter Info Section -->
                <section class="space-y-4">
                    <h5 class="text-[10px] font-black tracking-[0.2em] text-primary uppercase">
                        Member Information
                    </h5>
                    <div class="neo-surface-soft space-y-4 p-5">
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-black text-muted-foreground uppercase">Email Address</span>
                            <span class="text-sm font-extrabold">{{ selectedSubmission?.user?.email ?? 'No email provided' }}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-black text-muted-foreground uppercase">Submitted At</span>
                            <div class="flex items-center gap-2 text-sm font-extrabold">
                                <Clock class="size-4 text-muted-foreground" />
                                <span>{{ selectedSubmission ? formatDate(selectedSubmission.submitted_at) : '-' }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Form Responses Section -->
                <section class="space-y-4">
                    <h5 class="text-[10px] font-black tracking-[0.2em] text-primary uppercase">
                        Form Responses
                    </h5>
                    <div class="grid grid-cols-1 gap-4">
                        <div
                            v-for="key in allAnswerKeys"
                            :key="key"
                            class="neo-surface flex flex-col gap-2 bg-white p-5 transition-transform hover:scale-[1.02]"
                        >
                            <span class="neo-kicker !px-2 !py-0.5 !text-[9px]">
                                {{ humanizeKey(key) }}
                            </span>
                            <div class="mt-1">
                                <a
                                    v-if="fileUrl(selectedSubmission?.answers[key])"
                                    :href="fileUrl(selectedSubmission?.answers[key]) ?? undefined"
                                    target="_blank"
                                    class="text-primary decoration-primary/30 hover:decoration-primary inline-flex items-center gap-2.5 font-black underline underline-offset-4 transition-all"
                                >
                                    <div class="bg-primary/10 flex size-10 items-center justify-center rounded-lg border-2 border-primary/20">
                                        <FileText class="size-5" />
                                    </div>
                                    <span>Download Attachment</span>
                                </a>
                                <p v-else class="text-foreground whitespace-pre-wrap text-sm leading-relaxed font-bold">
                                    {{ answerPreview(selectedSubmission?.answers[key]) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="selectedSubmission" class="space-y-3 border-t-[1.5px] border-foreground/10 pt-6">
                    <h5 class="text-[10px] font-black tracking-[0.2em] text-primary uppercase">Review (preview)</h5>
                    <p class="text-xs font-semibold text-muted-foreground">
                        Tombol ini memanggil notifikasi saja sampai backend menambah kolom status dan route approve/reject.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            class="flex-1 gap-2 border-2 border-success/50 font-black text-success min-w-[120px]"
                            variant="outline"
                            @click="submissionReviewStub('accept', selectedSubmission)"
                        >
                            <CheckCircle2 class="size-4" />
                            Accept
                        </Button>
                        <Button
                            type="button"
                            class="flex-1 gap-2 border-2 border-destructive/50 font-black text-destructive min-w-[120px]"
                            variant="outline"
                            @click="submissionReviewStub('reject', selectedSubmission)"
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

<style scoped>
.animate-brutal-pop {
    opacity: 0;
    animation: brutal-pop 0.4s cubic-bezier(0.2, 1.4, 0.4, 1) forwards;
}
</style>

