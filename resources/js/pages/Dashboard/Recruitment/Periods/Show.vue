<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { fetchApplicantDetail } from '@/adapters/http/recruitment';
import { isHttpRequestError } from '@/adapters/http/httpError';
import { replaceUrl, currentSearchParam } from '@/adapters/browser/navigation';
import { onWindowEvent } from '@/adapters/browser/domEvents';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import PeriodApplicantSection from '@/components/modules/dashboard/recruitment/PeriodApplicantSection.vue';
import PeriodSettingsSection from '@/components/modules/dashboard/recruitment/PeriodSettingsSection.vue';
import PeriodInterviewSection from '@/components/modules/dashboard/recruitment/PeriodInterviewSection.vue';
import PeriodReportSection from '@/components/modules/dashboard/recruitment/PeriodReportSection.vue';
import ApplicantDetailPanel from '@/components/modules/dashboard/recruitment/ApplicantDetailPanel.vue';
import type { IApplicationDetail } from '@/types/recruitment';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import InterviewerCreateSheet from '@/components/modules/dashboard/recruitment/InterviewerCreateSheet.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { type IPaginatorMeta } from '@/lib/paginatorLinks';
import { SearchableSelect, type SearchableSelectOption } from '@/components/ui/searchable-select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import {
    BarChart3,
    CalendarClock,
    Plus,
    QrCode,
    RefreshCw,
    Send,
    Settings,
    Trash2,
    UserCheck,
    Users,
} from 'lucide-vue-next';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import { describeRequestError } from '@/lib/requestErrorText';
import { routes } from '@/lib/routes';
import type { TPeriodStatusValue } from '@/lib/recruitmentPeriodPhase';
import {
    daysRemaining,
    parsePeriodDate,
    phaseCountdownLabel,
    phaseDeadline,
    resolvePeriodPhase,
    statusLabel,
} from '@/lib/recruitmentPeriodPhase';
import { formatIdDateLabel, formatIdDateTimeLabel } from '@/lib/shadcnDateFormat';
import { cn } from '@/lib/utils';
import { setTopbar } from '@/hooks/useDashboardTopbar';
import { useAuth } from '@/hooks/useAuth';

defineOptions({ layout: DashboardLayout });

interface Period {
    id: string;
    name: string;
    slug: string;
    status: TPeriodStatusValue;
    status_label: string;
    description: string | null;
    registration_opens_at: string | null;
    registration_closes_at: string | null;
    interview_starts_at: string | null;
    interview_ends_at: string | null;
    finalization_deadline_at: string | null;
    applications_count: number;
    banner_url: string | null;
    can_edit?: boolean;
    whatsapp_group_url?: string | null;
    whatsapp_group_aa_url?: string | null;
    whatsapp_group_member_url?: string | null;
}

interface ApplicationRow {
    id: string;
    registration_number: string;
    full_name: string;
    nim: string;
    semester: number;
    stage: string;
    stage_label: string;
    result: string;
    result_label: string;
    revision_required: boolean;
    submitted_at: string | null;
    /** Status kirim link grup terakhir: sent | failed | null (belum pernah). */
    group_link_status?: string | null;
    /** Status kirim QR terakhir: sent | failed | queued | null (belum pernah). */
    qr_status?: 'sent' | 'failed' | 'queued' | string | null;
    /** Pesan error kirim QR terakhir (bila gagal). */
    qr_error?: string | null;
    primary_division: { id: string; name: string } | null;
    secondary_division: { id: string; name: string } | null;
    period: { id: string; name: string } | null;
}

interface SessionRow {
    id: string;
    session_date: string;
    starts_at: string;
    ends_at: string;
    location: string;
    room: string;
    is_active: boolean;
    interviews_count: number;
    period: { id: string; name: string } | null;
    division: { id: string; name: string; code: string } | null;
}

interface ApplicationPaginator extends IPaginatorMeta {
    data: ApplicationRow[];
}

interface SessionPaginator extends IPaginatorMeta {
    data: SessionRow[];
}

interface ReportPayload {
    period: { id: string; name: string } | null;
    funnel: { stage: string; label: string; count: number }[];
    by_division: { division: string; count: number }[];
    by_semester: { semester: number; count: number }[];
    interview_stats: Record<string, number>;
    feedback: { count: number; averages: Record<string, number | null> };
}

interface InterviewerDivision {
    id: string;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    interviewer_assignments_count: number;
}

interface InterviewerAssignment {
    id: string;
    user_id: string;
    user_name: string;
    user_email: string;
    division_id: string;
    division_name: string;
    division_code: string;
}

interface InterviewerCandidate {
    id: string;
    name: string;
    email: string;
}

const props = withDefaults(
    defineProps<{
        period: Period;
        /** Paginator kontrak 8a FINAL; null = tanpa izin, undefined = key absent (tab lain). */
        applications?: ApplicationPaginator | null;
        queue_counts: Record<string, number>;
        divisionOptions: { id: string; name: string; code: string }[];
        stageOptions: { value: string; label: string }[];
        semesterOptions?: { value: string; label: string }[];
        screening_reason_options?: { value: string; label: string }[];
        division_options?: { id: string; name: string; code: string }[];
        membership_type_options?: { value: string; label: string }[];
        query: {
            search?: string;
            division_id?: string;
            stage?: string;
            queue?: string;
            semester?: string;
            application?: string;
            page?: number | string;
            per_page?: number | string;
        };
        tab: string;
        sessions?: SessionPaginator | null;
        interview_division_options?: { id: string; name: string; code: string }[];
        report?: ReportPayload | null;
        applicant_detail?: IApplicationDetail | null;
        /** Jumlah eligible kirim link grup (lolos, bukan rejected). */
        group_link_eligible_count?: number;
        /** Jumlah eligible kirim QR (tahap interview, belum absen, belum terkirim). */
        qr_eligible_count?: number;
        /** Jumlah applicant yang QR-nya sudah terkirim (opsi kirim ulang); 0 bila key absent. */
        qr_sent_count?: number;
        /** Ringkasan progres kirim QR bulk ({ sent, failed, queued }); absent = backend belum mengirim. */
        qr_status_summary?: { sent: number; failed: number; queued: number } | null;
        divisions?: InterviewerDivision[];
        assignments?: InterviewerAssignment[];
        interviewerCandidates?: InterviewerCandidate[];
    }>(),
    {
        semesterOptions: () => [],
        screening_reason_options: () => [],
        division_options: () => [],
        membership_type_options: () => [],
        interview_division_options: () => [],
        divisions: () => [],
        assignments: () => [],
        interviewerCandidates: () => [],
    }
);

const page = usePage();
const user = useAuth(page.props);
const { showErrorToast, showSuccessToast } = useToastNotifications();
const canListApplications = computed(() => user.value?.can_list_recruitment_applications === true);
const canScheduleInterviews = computed(() => user.value?.can_schedule_recruitment_interviews === true);
const canScreenApplications = computed(() => user.value?.can_screen_recruitment_applications === true);
const canViewReports = computed(() => user.value?.can_view_recruitment_reports === true);
/** Sama seperti halaman Divisi semula: hanya pengelola periode yang mengatur interviewer. */
const canManagePeriods = computed(() => user.value?.can_manage_recruitment_periods === true);
/** Tab Settings + tombol Edit memakai izin edit per-periode dari backend (can_edit). */
const canEdit = computed<boolean>(() => props.period.can_edit === true);
/** Jumlah eligible kirim link grup; 0 bila key absent (tab lain). */
const groupLinkEligibleCount = computed<number>(() => props.group_link_eligible_count ?? 0);
/** Jumlah eligible kirim QR bulk; 0 bila key absent (tab lain). */
const qrEligibleCount = computed<number>(() => props.qr_eligible_count ?? 0);
/** Jumlah applicant yang QR-nya sudah terkirim (opsi kirim ulang); 0 bila key absent. */
const qrSentCount = computed<number>(() => props.qr_sent_count ?? 0);
/** Tombol Kirim QR aktif bila ada penerima baru atau ada yang bisa dikirimi ulang. */
const canSendQr = computed<boolean>(() => qrEligibleCount.value > 0 || qrSentCount.value > 0);
const qrButtonTitle = computed<string>(() => {
    if (qrEligibleCount.value > 0) return `Kirim QR ke ${qrEligibleCount.value} applicant tahap interview`;
    if (qrSentCount.value > 0) return `Kirim ulang QR ke ${qrSentCount.value} applicant yang sudah terkirim`;
    return 'Belum ada applicant yang bisa dikirimi QR';
});
/** Centang bila admin ingin menyertakan ulang applicant yang sudah terkirim. */
const includeSentQr = ref<boolean>(false);
const qrDialogDescription = computed<string>(() => {
    const base = `QR dikirim 1 per 1 ke ${qrEligibleCount.value} applicant tahap interview yang belum absen.`;
    const retry = 'Kirim ulang hanya mengulang yang gagal atau belum terkirim.';
    if (includeSentQr.value && qrSentCount.value > 0) {
        return `${base} Termasuk ${qrSentCount.value} applicant yang sudah dikirimi. ${retry}`;
    }
    return `${base} ${retry}`;
});
/** Ringkasan progres kirim QR bulk; defensif bila backend belum mengirim key. */
const qrSummary = computed<{ sent: number; failed: number; queued: number }>(() => ({
    sent: props.qr_status_summary?.sent ?? 0,
    failed: props.qr_status_summary?.failed ?? 0,
    queued: props.qr_status_summary?.queued ?? 0,
}));
/** Chip progres tampil bila sudah ada aktivitas kirim QR. */
const hasQrActivity = computed<boolean>(
    () => qrSummary.value.sent + qrSummary.value.failed + qrSummary.value.queued > 0
);
const qrStatusLabel = computed<string>(() => {
    const s = qrSummary.value;
    return `Progres kirim QR: ${s.queued} antrean, ${s.sent} terkirim, ${s.failed} gagal`;
});

const isRefreshingQr = ref<boolean>(false);
let qrPollTimer: ReturnType<typeof setInterval> | null = null;

/** Props yang dimuat ulang saat memantau progres kirim QR. */
const QR_RELOAD_ONLY: string[] = ['qr_status_summary', 'applications', 'qr_eligible_count', 'qr_sent_count'];

function stopQrPolling(): void {
    if (qrPollTimer !== null) {
        clearInterval(qrPollTimer);
        qrPollTimer = null;
    }
}

function refreshQrStatus(): void {
    if (isRefreshingQr.value) return;
    router.reload({
        only: QR_RELOAD_ONLY,
        onStart: () => {
            isRefreshingQr.value = true;
        },
        onFinish: () => {
            isRefreshingQr.value = false;
        },
    });
}

function startQrPolling(): void {
    stopQrPolling();
    qrPollTimer = setInterval(() => {
        if (qrSummary.value.queued <= 0) {
            stopQrPolling();
            return;
        }
        refreshQrStatus();
    }, 5000);
}

/** Hentikan polling saat antrean habis (mis. selesai di tab lain atau usai reload manual). */
watch(
    () => qrSummary.value.queued,
    (queued) => {
        if (queued <= 0) stopQrPolling();
    }
);
/** Link grup WA tersedia bila periode menyimpannya (diisi di tab Settings). */
const hasGroupLink = computed<boolean>(
    () =>
        props.period.whatsapp_group_url !== null &&
        props.period.whatsapp_group_url !== undefined &&
        props.period.whatsapp_group_url !== ''
);

const groupLinkDialogOpen = ref(false);
const isSendingGroupLink = ref(false);
const qrDialogOpen = ref(false);
const isSendingQr = ref(false);

function openGroupLink(): void {
    if (!hasGroupLink.value || isSendingGroupLink.value) return;
    groupLinkDialogOpen.value = true;
}

function cancelGroupLink(): void {
    groupLinkDialogOpen.value = false;
}

function confirmGroupLink(): void {
    if (!hasGroupLink.value || isSendingGroupLink.value) return;
    isSendingGroupLink.value = true;
    router.post(
        routes.admin.recruitment.periods.sendGroupLink(props.period.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showSuccessToast('Link grup dikirim ke applicant yang lolos.');
            },
            onError: () => {
                showErrorToast('Gagal mengirim link grup. Coba lagi.');
            },
            onFinish: () => {
                isSendingGroupLink.value = false;
                groupLinkDialogOpen.value = false;
            },
        }
    );
}

function openQr(): void {
    if (!canSendQr.value || isSendingQr.value) return;
    includeSentQr.value = false;
    qrDialogOpen.value = true;
}

function cancelQr(): void {
    includeSentQr.value = false;
    qrDialogOpen.value = false;
}

function confirmQr(): void {
    if (!canSendQr.value || isSendingQr.value) return;
    isSendingQr.value = true;
    router.post(
        routes.admin.recruitment.periods.sendQr(props.period.id),
        includeSentQr.value ? { include_sent: true } : {},
        {
            preserveScroll: true,
            onSuccess: () => {
                startQrPolling();
            },
            onError: () => {
                showErrorToast('Gagal mengirim QR. Coba lagi.');
            },
            onFinish: () => {
                isSendingQr.value = false;
                qrDialogOpen.value = false;
            },
        }
    );
}

const assignForm = useForm({
    user_id: '',
    recruitment_division_id: '',
});

function submitAssign(): void {
    if (isDuplicateAssign.value || assignForm.processing) return;
    assignForm.post(routes.admin.recruitment.interviewers.assign, {
        preserveScroll: true,
        onSuccess: () => {
            assignForm.reset('user_id');
        },
    });
}

const unassignDialogOpen = ref(false);
const pendingUnassign = ref<InterviewerAssignment | null>(null);
const unassigningId = ref<string | null>(null);

function requestUnassign(row: InterviewerAssignment): void {
    pendingUnassign.value = row;
    unassignDialogOpen.value = true;
}

function cancelUnassign(): void {
    unassignDialogOpen.value = false;
}

function confirmUnassign(): void {
    const row: InterviewerAssignment | null = pendingUnassign.value;
    if (!row || unassigningId.value !== null) return;
    unassigningId.value = row.id;
    router.delete(routes.admin.recruitment.interviewers.unassign(row.id), {
        preserveScroll: true,
        onError: () => {
            showErrorToast('Gagal menghapus penugasan interviewer.');
        },
        onFinish: () => {
            unassigningId.value = null;
            pendingUnassign.value = null;
            unassignDialogOpen.value = false;
        },
    });
}

function initialsOf(name: string): string {
    const words: string[] = name
        .trim()
        .split(/\s+/)
        .filter((w) => w.length > 0);
    if (words.length === 0) return '—';
    const first: string = words[0]?.charAt(0) ?? '';
    const second: string = words.length > 1 ? (words[1]?.charAt(0) ?? '') : '';
    const letters: string = `${first}${second}`.toUpperCase();
    return letters.length > 0 ? letters : '—';
}

const assignedPairKeys = computed<Set<string>>(
    () => new Set(props.assignments.map((a) => `${a.user_id}|${a.division_id}`))
);

const assignInterviewerOptions = computed<SearchableSelectOption[]>(() =>
    props.interviewerCandidates.map((u) => {
        const currentDivision: string = assignForm.recruitment_division_id;
        const isTaken: boolean = currentDivision.length > 0 && assignedPairKeys.value.has(`${u.id}|${currentDivision}`);
        return {
            value: u.id,
            label: u.name,
            sublabel: u.email,
            initials: initialsOf(u.name),
            disabled: isTaken,
        };
    })
);

const assignDivisionOptions = computed<SearchableSelectOption[]>(() =>
    props.divisions.map((d) => {
        const currentUser: string = assignForm.user_id;
        const isTaken: boolean = currentUser.length > 0 && assignedPairKeys.value.has(`${currentUser}|${d.id}`);
        return {
            value: d.id,
            label: d.name,
            sublabel: d.code,
            initials: d.code.slice(0, 2).toUpperCase(),
            disabled: isTaken,
        };
    })
);

const isDuplicateAssign = computed<boolean>(() => {
    const userId: string = assignForm.user_id;
    const divisionId: string = assignForm.recruitment_division_id;
    if (userId.length === 0 || divisionId.length === 0) return false;
    return assignedPairKeys.value.has(`${userId}|${divisionId}`);
});

const canSubmitAssign = computed<boolean>(() => {
    return (
        assignForm.user_id.length > 0 &&
        assignForm.recruitment_division_id.length > 0 &&
        !isDuplicateAssign.value &&
        !assignForm.processing
    );
});

interface AssignmentGroup {
    key: string;
    divisionName: string;
    divisionCode: string;
    count: number;
    rows: InterviewerAssignment[];
}

const groupedAssignments = computed<AssignmentGroup[]>(() => {
    const byDivision = new Map<string, InterviewerAssignment[]>();
    for (const row of props.assignments) {
        const list: InterviewerAssignment[] = byDivision.get(row.division_id) ?? [];
        list.push(row);
        byDivision.set(row.division_id, list);
    }
    const order = new Map<string, number>(props.divisions.map((d, i) => [d.id, i]));
    const groups: AssignmentGroup[] = Array.from(byDivision.entries()).map(([divisionId, rows]) => {
        const sortedRows: InterviewerAssignment[] = [...rows].sort((a, b) =>
            a.user_name.localeCompare(b.user_name, 'id')
        );
        const first: InterviewerAssignment | undefined = sortedRows[0];
        return {
            key: divisionId,
            divisionName: first?.division_name ?? 'Divisi',
            divisionCode: first?.division_code ?? '',
            count: sortedRows.length,
            rows: sortedRows,
        };
    });
    groups.sort((a, b) => {
        const orderA: number | undefined = order.get(a.key);
        const orderB: number | undefined = order.get(b.key);
        if (orderA !== undefined && orderB !== undefined) return orderA - orderB;
        if (orderA !== undefined) return -1;
        if (orderB !== undefined) return 1;
        return a.divisionName.localeCompare(b.divisionName, 'id');
    });
    return groups;
});

const assignmentsCountLabel = computed<string>(() => props.assignments.length.toLocaleString('id-ID'));

// Fitur broadcast dinonaktifkan (config/features.php): seluruh state daftar
// broadcast period dihapus dari halaman ini. File komponen broadcast tetap ada.
const pendingUnassignDescription = computed<string>(() => {
    const row: InterviewerAssignment | null = pendingUnassign.value;
    if (!row) return '';
    return `${row.user_name} tidak lagi menjadi interviewer untuk ${row.division_name}.`;
});

const isCreateSheetOpen = ref<boolean>(false);
const pendingCreatedEmail = ref<string>('');

function openCreateSheet(): void {
    isCreateSheetOpen.value = true;
}

function closeCreateSheet(): void {
    isCreateSheetOpen.value = false;
}

function tryAdoptCreatedInterviewer(): void {
    const email: string = pendingCreatedEmail.value.trim().toLowerCase();
    if (email === '') return;
    const match: InterviewerCandidate | undefined = props.interviewerCandidates.find(
        (c) => c.email.trim().toLowerCase() === email
    );
    if (match) {
        assignForm.user_id = match.id;
        pendingCreatedEmail.value = '';
    }
}

function onInterviewerCreated(email: string): void {
    pendingCreatedEmail.value = email;
    tryAdoptCreatedInterviewer();
}

watch(
    () => props.interviewerCandidates,
    () => {
        tryAdoptCreatedInterviewer();
    }
);

const applicationRows = computed<ApplicationRow[]>(() => props.applications?.data ?? []);

const applicantTotal = computed<number>(() => {
    const total: number | undefined = props.applications?.total;
    if (total !== undefined && total > 0) return total;
    return applicationRows.value.length > 0 ? applicationRows.value.length : (props.period.applications_count ?? 0);
});

const participantCountLabel = computed<string>(() => {
    return applicantTotal.value.toLocaleString('id-ID');
});

const validTabs = ['peserta', 'interview', 'laporan', 'interviewer', 'settings'] as const;
type TabValue = (typeof validTabs)[number];

function normalizeTab(value: string): TabValue {
    if (!(validTabs as readonly string[]).includes(value)) return 'peserta';
    // Tab settings hanya untuk yang berizin edit; tanpa izin jatuh ke peserta.
    if (value === 'settings' && !canEdit.value) return 'peserta';
    return value as TabValue;
}

/** Deep-link ?tab=settings dibaca dari URL karena backend menormalkan tab asing ke peserta. */
function initialTab(): TabValue {
    const fromUrl = currentSearchParam('tab') ?? '';
    if ((validTabs as readonly string[]).includes(fromUrl)) return normalizeTab(fromUrl);
    return normalizeTab(props.tab);
}

const activeTab = ref<TabValue>(initialTab());
/**
 * Membedakan navigasi tab sungguhan dari echo server (redirect PUT update settings
 * kembali tanpa query tab) agar pengguna tidak terlempar dari tab settings usai simpan.
 */
const expectTab = ref<TabValue | null>(null);
/** Indikator ringan selama pindah tab (navigasi Inertia replace). */
const tabNavigating = ref<boolean>(false);

/**
 * Props yang diminta ulang per tab (partial reload): tab aktif selalu butuh
 * period/tab/query segar, sisanya hanya data milik tab itu agar pindah tab
 * tetap hit backend tanpa memuat ulang data tab lain.
 */
const TAB_ONLY: Record<TabValue, string[]> = {
    peserta: [
        'period',
        'tab',
        'query',
        'applications',
        'group_link_eligible_count',
        'qr_eligible_count',
        'qr_sent_count',
        'qr_status_summary',
        'queue_counts',
        'screening_reason_options',
        'division_options',
        'membership_type_options',
        'divisionOptions',
        'semesterOptions',
        'stageOptions',
    ],
    interview: ['period', 'tab', 'query', 'sessions', 'interview_division_options', 'queue_counts'],
    laporan: ['period', 'tab', 'query', 'report'],
    interviewer: ['period', 'tab', 'query', 'divisions', 'assignments', 'interviewerCandidates'],
    settings: ['period', 'tab', 'query'],
};

function settingsUrl(): string {
    return `${routes.admin.recruitment.periods.show(props.period.id)}?tab=settings`;
}

watch(
    () => props.tab,
    (value) => {
        if (expectTab.value === null) return;
        const next = normalizeTab(value);
        if (next !== expectTab.value) return;
        expectTab.value = null;
        activeTab.value = next;
    }
);

function visitTab(target: TabValue): void {
    if (target === activeTab.value) return;
    expectTab.value = target;
    router.get(
        routes.admin.recruitment.periods.show(props.period.id),
        { tab: target === 'peserta' ? undefined : target },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: TAB_ONLY[target],
            onStart: () => {
                tabNavigating.value = true;
            },
            onFinish: () => {
                tabNavigating.value = false;
            },
            onError: () => {
                tabNavigating.value = false;
                expectTab.value = null;
            },
        }
    );
}

function onTabChange(value: string | number): void {
    const next = String(value);
    if (!(validTabs as readonly string[]).includes(next)) return;
    const target = normalizeTab(next);
    if (target === 'settings' && !canEdit.value) return;
    visitTab(target);
}

/** Usai simpan (PUT update redirect tanpa query tab): pin kembali tab settings + URL. */
function onSettingsSaved(): void {
    expectTab.value = null;
    activeTab.value = 'settings';
    replaceUrl(settingsUrl());
}

const selectedApplication = ref<IApplicationDetail | null>(null);
const detailLoading = ref<boolean>(false);
/** Pesan kegagalan muat detail; panel tetap terbuka agar penyebabnya terlihat. */
const detailError = ref<string>('');
/** Applicant yang gagal dimuat, dipakai tombol "Coba lagi" di panel. */
const detailFailedId = ref<string | null>(null);
const detailCache = new Map<string, IApplicationDetail>();
/** Urutan request detail; hanya request terbaru yang boleh menulis state panel. */
let detailRequestSeq = 0;
/** Baris tabel yang tetap tersorot saat panel menampilkan error, bukan detail. */
const panelApplicantId = computed<string | null>(() => selectedApplication.value?.id ?? detailFailedId.value);

function detailUrl(id: string): string {
    return `${routes.admin.recruitment.periods.show(props.period.id)}/applications/${id}`;
}

/**
 * Terjemahkan kegagalan muat detail ke kalimat sebab + tindakan, tanpa pesan mentah.
 * Sumber string tunggal ada di `describeRequestError`; hanya `not_found` yang
 * memakai kalimat kontekstual halaman ini.
 */
function detailErrorMessage(error: unknown): string {
    if (!isHttpRequestError(error)) {
        return describeRequestError({ kind: 'unknown', status: null }) ?? '';
    }

    if (error.kind === 'not_found') {
        return 'Data peserta ini tidak ditemukan. Muat ulang daftar peserta, lalu pilih lagi.';
    }

    return (
        describeRequestError({
            kind: error.kind,
            status: error.status,
            retryAfterSeconds: error.retryAfterSeconds,
        }) ?? ''
    );
}

/** Muat detail satu applicant; error lama dibiarkan oleh pemanggil (retry menjaga target). */
async function loadApplicantDetail(id: string): Promise<void> {
    const seq = ++detailRequestSeq;
    detailLoading.value = true;
    try {
        const application = await fetchApplicantDetail(detailUrl(id));
        if (seq !== detailRequestSeq) return;
        detailCache.set(id, application);
        selectedApplication.value = application;
        detailError.value = '';
        detailFailedId.value = null;
    } catch (error) {
        if (seq !== detailRequestSeq) return;
        selectedApplication.value = null;
        detailError.value = detailErrorMessage(error);
        detailFailedId.value = id;
    } finally {
        if (seq === detailRequestSeq) {
            detailLoading.value = false;
        }
    }
}

async function selectApplicant(id: string): Promise<void> {
    if (selectedApplication.value?.id === id) return;
    const cached: IApplicationDetail | undefined = detailCache.get(id);
    if (cached && 'can_resend_tracking' in cached) {
        // Cache dipilih langsung: request detail yang masih jalan tidak boleh menimpa.
        detailRequestSeq += 1;
        detailLoading.value = false;
        selectedApplication.value = cached;
        detailError.value = '';
        detailFailedId.value = null;
        return;
    }
    // Klik ulang applicant yang gagal = retry; panel tidak boleh kehilangan target.
    if (id === detailFailedId.value) {
        await loadApplicantDetail(id);
        return;
    }
    // Error applicant sebelumnya tidak boleh tampil selama applicant baru dimuat.
    detailError.value = '';
    detailFailedId.value = null;
    await loadApplicantDetail(id);
}

/** Ulangi pemuatan applicant yang gagal tanpa menutup panel error. */
function retryApplicantDetail(): void {
    const failedId = detailFailedId.value;
    if (failedId === null) return;
    void loadApplicantDetail(failedId);
}

function closePanel(): void {
    // Invalidasi load in-flight agar panel tidak terbuka sendiri setelah ditutup.
    detailRequestSeq += 1;
    detailLoading.value = false;
    selectedApplication.value = null;
    detailError.value = '';
    detailFailedId.value = null;
}

function refreshList(): void {
    detailCache.clear();
    // `reload()` selalu preserveScroll; ReloadOptions tidak menerima opsi itu di Inertia 3.
    router.reload({
        only: [
            'applications',
            'queue_counts',
            'screening_reason_options',
            'division_options',
            'membership_type_options',
        ],
    });
}

function onGlobalKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape') return;
    closePanel();
}

let releaseGlobalKeydown: (() => void) | null = null;

onMounted(() => {
    releaseGlobalKeydown = onWindowEvent('keydown', onGlobalKeydown);
});

onUnmounted(() => {
    releaseGlobalKeydown?.();
    releaseGlobalKeydown = null;
    stopQrPolling();
});

const phaseInput = computed(() => ({
    status: props.period.status,
    registrationOpensAt: props.period.registration_opens_at,
    registrationClosesAt: props.period.registration_closes_at,
    interviewStartsAt: props.period.interview_starts_at,
    interviewEndsAt: props.period.interview_ends_at,
    finalizationDeadlineAt: props.period.finalization_deadline_at,
}));

const phase = computed(() => resolvePeriodPhase(phaseInput.value));
const remainingDays = computed<number | null>(() => daysRemaining(phaseDeadline(phase.value, phaseInput.value)));
const countdown = computed<string | null>(() => phaseCountdownLabel(phase.value, remainingDays.value));

const statusClasses: Record<TPeriodStatusValue, string> = {
    draft: 'border-border bg-secondary text-secondary-foreground',
    open: 'border-success/20 bg-success/10 text-success',
    closed: 'border-warning/25 bg-warning/10 text-warning-foreground',
    archived: 'border-border bg-muted text-muted-foreground',
};

const canOpen = computed<boolean>(() => props.period.status === 'draft' || props.period.status === 'closed');
const canClose = computed<boolean>(() => props.period.status === 'open');

const descriptionExpanded = ref<boolean>(false);
const showDescriptionToggle = computed<boolean>(() => (props.period.description?.length ?? 0) > 140);

interface ScheduleItem {
    key: string;
    label: string;
    display: string;
    dotClass: string;
    isUnscheduled: boolean;
}

function scheduleDisplay(value: string | null, withTime: boolean): string {
    if (!value || !parsePeriodDate(value)) return 'Belum ditentukan';
    return withTime ? formatIdDateTimeLabel(value) : formatIdDateLabel(value);
}

const scheduleNodes = computed<ScheduleItem[]>(() => {
    const now = new Date();
    const raw: { key: string; label: string; value: string | null; withTime: boolean }[] = [
        {
            key: 'registration-open',
            label: 'Buka pendaftaran',
            value: props.period.registration_opens_at,
            withTime: true,
        },
        {
            key: 'registration-close',
            label: 'Tutup pendaftaran',
            value: props.period.registration_closes_at,
            withTime: true,
        },
        { key: 'interview-start', label: 'Mulai interview', value: props.period.interview_starts_at, withTime: false },
        { key: 'interview-end', label: 'Selesai interview', value: props.period.interview_ends_at, withTime: false },
        {
            key: 'finalization',
            label: 'Target finalisasi',
            value: props.period.finalization_deadline_at,
            withTime: false,
        },
    ];

    const isOpen = props.period.status === 'open';
    let currentAssigned = false;

    return raw.map((node) => {
        const parsed = node.value ? parsePeriodDate(node.value) : null;
        let dotClass = 'border border-dashed border-muted-foreground/40 bg-transparent';
        let isUnscheduled = false;

        if (!parsed) {
            isUnscheduled = true;
        } else if (parsed.getTime() <= now.getTime()) {
            dotClass = isOpen ? 'bg-success' : 'bg-muted-foreground/40';
        } else if (!isOpen) {
            dotClass = 'bg-muted-foreground/30';
        } else if (!currentAssigned) {
            currentAssigned = true;
            dotClass = 'bg-primary';
        } else {
            dotClass = 'bg-muted-foreground/30';
        }

        return {
            key: node.key,
            label: node.label,
            display: scheduleDisplay(node.value, node.withTime),
            dotClass,
            isUnscheduled,
        };
    });
});

onMounted(() => {
    setTopbar({ title: props.period.name, subtitle: 'Detail periode Open Recruitment' });
    if (qrSummary.value.queued > 0) startQrPolling();
});

function openPeriod(): void {
    router.post(routes.admin.recruitment.periods.open(props.period.id));
}

function closePeriod(): void {
    router.post(routes.admin.recruitment.periods.close(props.period.id));
}
</script>

<template>
    <Head :title="period.name" />

    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 pb-8 sm:pb-10">
        <Card class="border-border/70 overflow-hidden rounded-xl shadow-sm">
            <CardContent class="p-4 sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                            <h1 class="min-w-0 text-lg font-semibold tracking-tight break-words sm:text-xl">
                                {{ period.name }}
                            </h1>
                            <Badge :class="cn('shrink-0 border text-[11px] font-medium', statusClasses[period.status])">
                                {{ statusLabel(period.status) }}
                            </Badge>
                        </div>
                        <p class="text-muted-foreground mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                            <span class="inline-flex max-w-full min-w-0 items-center">
                                <span class="truncate font-mono text-[11px]" :title="period.slug">{{
                                    period.slug
                                }}</span>
                            </span>
                            <span v-if="countdown" class="inline-flex flex-wrap items-center gap-x-2">
                                <span aria-hidden="true" class="select-none">·</span>
                                <span>{{ countdown }}</span>
                            </span>
                            <span v-if="applicantTotal > 0" class="inline-flex items-center gap-x-2">
                                <span aria-hidden="true" class="select-none">·</span>
                                <span class="tabular-nums">{{ participantCountLabel }} pendaftar</span>
                            </span>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 lg:shrink-0 lg:justify-end">
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="!canSendQr || isSendingQr"
                            :title="qrButtonTitle"
                            :aria-label="qrButtonTitle"
                            @click="openQr"
                        >
                            <QrCode class="mr-2 size-4" aria-hidden="true" />
                            {{ isSendingQr ? 'Mengirim…' : 'Kirim QR' }}
                        </Button>
                        <div
                            v-if="hasQrActivity"
                            role="status"
                            :aria-label="qrStatusLabel"
                            class="flex items-center gap-1.5"
                        >
                            <span
                                class="inline-flex shrink-0 items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 tabular-nums"
                                title="Menunggu giliran pengiriman QR"
                            >
                                Antrean {{ qrSummary.queued.toLocaleString('id-ID') }}
                            </span>
                            <Badge variant="secondary" class="shrink-0 tabular-nums" title="QR sudah terkirim">
                                Terkirim {{ qrSummary.sent.toLocaleString('id-ID') }}
                            </Badge>
                            <Badge
                                :variant="qrSummary.failed > 0 ? 'destructive' : 'outline'"
                                class="shrink-0 tabular-nums"
                                :title="
                                    qrSummary.failed > 0
                                        ? 'Sebagian pengiriman gagal. Kirim ulang untuk mencoba lagi.'
                                        : 'Belum ada pengiriman yang gagal'
                                "
                            >
                                Gagal {{ qrSummary.failed.toLocaleString('id-ID') }}
                            </Badge>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                title="Perbarui status pengiriman QR"
                                aria-label="Perbarui status pengiriman QR"
                                :disabled="isRefreshingQr"
                                @click="refreshQrStatus"
                            >
                                <RefreshCw
                                    class="size-4"
                                    :class="isRefreshingQr && 'animate-spin'"
                                    aria-hidden="true"
                                />
                            </Button>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="!hasGroupLink || isSendingGroupLink"
                            :title="
                                hasGroupLink
                                    ? 'Kirim link grup WA ke applicant yang lolos'
                                    : 'Isi link grup WA di tab Settings dulu'
                            "
                            :aria-label="'Kirim link grup WA ke applicant yang lolos'"
                            @click="openGroupLink"
                        >
                            <Send class="mr-2 size-4" aria-hidden="true" />
                            {{ isSendingGroupLink ? 'Mengirim…' : 'Kirim Link Grup' }}
                        </Button>
                        <Button
                            v-if="canOpen"
                            size="sm"
                            :aria-label="'Buka pendaftaran ' + period.name"
                            @click="openPeriod"
                        >
                            Buka pendaftaran
                        </Button>
                        <Button
                            v-if="canClose"
                            size="sm"
                            variant="destructive"
                            :aria-label="'Tutup pendaftaran ' + period.name"
                            @click="closePeriod"
                        >
                            Tutup pendaftaran
                        </Button>
                    </div>
                </div>

                <div v-if="period.description" class="border-border/60 mt-3 border-t pt-3">
                    <h2 class="sr-only">Deskripsi periode</h2>
                    <p
                        id="period-description"
                        class="text-muted-foreground text-sm leading-relaxed"
                        :class="!descriptionExpanded && 'line-clamp-2'"
                    >
                        {{ period.description }}
                    </p>
                    <button
                        v-if="showDescriptionToggle"
                        type="button"
                        :aria-expanded="descriptionExpanded"
                        aria-controls="period-description"
                        class="text-foreground mt-1 text-xs font-medium underline-offset-4 hover:underline"
                        @click="descriptionExpanded = !descriptionExpanded"
                    >
                        {{ descriptionExpanded ? 'Ringkas' : 'Selengkapnya' }}
                    </button>
                </div>

                <div class="border-border/60 mt-3 border-t pt-3">
                    <h2 class="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">Jadwal</h2>
                    <ol
                        class="mt-2 flex gap-5 overflow-x-auto pb-0.5 sm:grid sm:grid-cols-5 sm:gap-4 sm:overflow-visible"
                    >
                        <li v-for="node in scheduleNodes" :key="node.key" class="min-w-[136px] flex-1 sm:min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span aria-hidden="true" :class="cn('size-1.5 shrink-0 rounded-full', node.dotClass)" />
                                <p class="text-foreground truncate text-xs font-medium">{{ node.label }}</p>
                            </div>
                            <p
                                class="mt-1 pl-3 text-[11px] tabular-nums"
                                :class="
                                    node.isUnscheduled ? 'text-muted-foreground/80 italic' : 'text-muted-foreground'
                                "
                            >
                                {{ node.display }}
                            </p>
                        </li>
                    </ol>
                </div>
            </CardContent>
        </Card>

        <Tabs :model-value="activeTab" @update:model-value="onTabChange" class="w-full">
            <TabsList
                class="border-border text-muted-foreground flex h-auto w-full [scrollbar-width:none] items-center justify-start gap-6 overflow-x-auto overflow-y-hidden rounded-none border-0 border-b bg-transparent p-0 whitespace-nowrap [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
            >
                <TabsTrigger
                    v-if="canListApplications"
                    value="peserta"
                    class="group hover:text-foreground data-[state=active]:border-foreground data-[state=active]:text-foreground -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                >
                    <Users
                        class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100"
                        aria-hidden="true"
                    />
                    <span>Peserta</span>
                    <span
                        class="bg-muted text-muted-foreground group-hover:text-foreground group-data-[state=active]:bg-foreground/10 group-data-[state=active]:text-foreground ml-1 inline-flex min-h-5 min-w-6 items-center justify-center rounded-full px-1.5 text-[11px] leading-4 font-medium tabular-nums transition-colors"
                    >
                        {{ participantCountLabel }}
                    </span>
                </TabsTrigger>
                <TabsTrigger
                    v-if="canScheduleInterviews"
                    value="interview"
                    class="group hover:text-foreground data-[state=active]:border-foreground data-[state=active]:text-foreground -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                >
                    <CalendarClock
                        class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100"
                        aria-hidden="true"
                    />
                    <span>Interview</span>
                </TabsTrigger>
                <TabsTrigger
                    v-if="canViewReports"
                    value="laporan"
                    class="group hover:text-foreground data-[state=active]:border-foreground data-[state=active]:text-foreground -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                >
                    <BarChart3
                        class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100"
                        aria-hidden="true"
                    />
                    <span>Laporan</span>
                </TabsTrigger>
                <TabsTrigger
                    v-if="canManagePeriods"
                    value="interviewer"
                    class="group hover:text-foreground data-[state=active]:border-foreground data-[state=active]:text-foreground -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                >
                    <UserCheck
                        class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100"
                        aria-hidden="true"
                    />
                    <span>Interviewer</span>
                    <span
                        class="bg-muted text-muted-foreground group-hover:text-foreground group-data-[state=active]:bg-foreground/10 group-data-[state=active]:text-foreground ml-1 inline-flex min-h-5 min-w-6 items-center justify-center rounded-full px-1.5 text-[11px] leading-4 font-medium tabular-nums transition-colors"
                    >
                        {{ assignmentsCountLabel }}
                    </span>
                </TabsTrigger>
                <TabsTrigger
                    v-if="canEdit"
                    value="settings"
                    class="group hover:text-foreground data-[state=active]:border-foreground data-[state=active]:text-foreground -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                >
                    <Settings
                        class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100"
                        aria-hidden="true"
                    />
                    <span>Settings</span>
                </TabsTrigger>
                <span
                    v-if="tabNavigating"
                    role="status"
                    class="text-muted-foreground ml-auto inline-flex shrink-0 animate-pulse items-center gap-1.5 px-1 py-2.5 text-xs"
                >
                    <span aria-hidden="true" class="bg-primary size-1.5 animate-ping rounded-full" />
                    Memuat…
                </span>
            </TabsList>

            <TabsContent value="peserta" class="mt-4">
                <div v-if="canListApplications">
                    <PeriodApplicantSection
                        :period-id="period.id"
                        :tab="activeTab"
                        :can-screen="canScreenApplications"
                        :selected-id="panelApplicantId"
                        :applications="applications ?? null"
                        :queue-counts="queue_counts"
                        :division-options="divisionOptions"
                        :stage-options="stageOptions"
                        :semester-options="semesterOptions"
                        :query="query"
                        :whatsapp-group-url="period.whatsapp_group_url ?? null"
                        :group-link-eligible-count="groupLinkEligibleCount ?? 0"
                        @select="selectApplicant"
                        @deselect="closePanel"
                    />

                    <ApplicantDetailPanel
                        :application="selectedApplication"
                        :loading="detailLoading"
                        :error-message="detailError"
                        :reason-options="screening_reason_options"
                        :division-options="division_options"
                        :membership-type-options="membership_type_options"
                        :editable="canScreenApplications"
                        :whatsapp-group-url="period.whatsapp_group_url ?? null"
                        :whatsapp-group-aa-url="period.whatsapp_group_aa_url ?? null"
                        :whatsapp-group-member-url="period.whatsapp_group_member_url ?? null"
                        @close="closePanel"
                        @submitted="refreshList"
                        @retry="retryApplicantDetail"
                    />
                </div>
            </TabsContent>

            <TabsContent value="interview" class="mt-4">
                <PeriodInterviewSection
                    v-if="canScheduleInterviews"
                    :sessions="sessions ?? null"
                    :period-id="period.id"
                    :division-options="interview_division_options"
                />
            </TabsContent>

            <TabsContent value="laporan" class="mt-4">
                <PeriodReportSection v-if="canViewReports" :period-id="period.id" :report="report ?? null" />
            </TabsContent>

            <TabsContent value="interviewer" class="mt-4">
                <Card v-if="canManagePeriods" class="border-border/70 rounded-2xl">
                    <CardHeader class="pb-1">
                        <CardTitle class="text-base">Tugaskan interviewer</CardTitle>
                        <p class="text-muted-foreground text-sm">
                            Pilih orang dan divisi, lalu tugaskan. Kombinasi yang sudah ada tidak bisa dipilih lagi.
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitAssign">
                            <div class="space-y-2">
                                <Label for="assign-user">Interviewer</Label>
                                <SearchableSelect
                                    id="assign-user"
                                    v-model="assignForm.user_id"
                                    :options="assignInterviewerOptions"
                                    placeholder="Pilih interviewer"
                                    search-placeholder="Cari nama atau email…"
                                    :aria-invalid="assignForm.errors.user_id ? true : undefined"
                                    @create="openCreateSheet"
                                >
                                    <template #action>
                                        <Plus class="size-4" aria-hidden="true" />
                                        <span>Tambah interviewer baru</span>
                                    </template>
                                </SearchableSelect>
                                <p v-if="assignForm.errors.user_id" role="alert" class="text-destructive text-xs">
                                    {{ assignForm.errors.user_id }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="assign-division">Divisi</Label>
                                <SearchableSelect
                                    id="assign-division"
                                    v-model="assignForm.recruitment_division_id"
                                    :options="assignDivisionOptions"
                                    placeholder="Pilih divisi"
                                    search-placeholder="Cari divisi…"
                                    :aria-invalid="assignForm.errors.recruitment_division_id ? true : undefined"
                                />
                                <p
                                    v-if="assignForm.errors.recruitment_division_id"
                                    role="alert"
                                    class="text-destructive text-xs"
                                >
                                    {{ assignForm.errors.recruitment_division_id }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <p v-if="isDuplicateAssign" role="status" class="text-muted-foreground mb-2 text-xs">
                                    Kombinasi interviewer dan divisi ini sudah ditugaskan.
                                </p>
                                <Button type="submit" size="sm" :disabled="!canSubmitAssign">
                                    {{ assignForm.processing ? 'Menugaskan…' : 'Tugaskan' }}
                                </Button>
                            </div>
                        </form>

                        <TooltipProvider v-if="groupedAssignments.length > 0">
                            <div class="space-y-4">
                                <section
                                    v-for="group in groupedAssignments"
                                    :key="group.key"
                                    aria-label="Interviewer divisi"
                                    class="border-border/70 overflow-hidden rounded-xl border"
                                >
                                    <header
                                        class="border-border/60 bg-muted/40 flex items-center justify-between gap-3 border-b px-4 py-2.5"
                                    >
                                        <div class="flex min-w-0 items-center gap-2">
                                            <span
                                                aria-hidden="true"
                                                class="bg-muted text-muted-foreground flex size-7 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold tracking-wide"
                                            >
                                                {{ group.divisionCode.slice(0, 2).toUpperCase() }}
                                            </span>
                                            <h3 class="truncate text-sm font-semibold">{{ group.divisionName }}</h3>
                                        </div>
                                        <Badge variant="secondary" class="shrink-0 tabular-nums">
                                            {{ group.count.toLocaleString('id-ID') }} orang
                                        </Badge>
                                    </header>
                                    <ul class="divide-border/70 divide-y">
                                        <li
                                            v-for="row in group.rows"
                                            :key="row.id"
                                            class="flex items-center justify-between gap-3 px-4 py-3"
                                        >
                                            <div class="flex min-w-0 items-center gap-3">
                                                <Avatar class="size-8 shrink-0">
                                                    <AvatarFallback class="text-[11px] font-semibold">
                                                        {{ initialsOf(row.user_name) }}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-medium">{{ row.user_name }}</p>
                                                    <p class="text-muted-foreground truncate text-xs">
                                                        {{ row.user_email }}
                                                    </p>
                                                </div>
                                            </div>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        :aria-label="`Hapus ${row.user_name} dari ${row.division_name}`"
                                                        @click="requestUnassign(row)"
                                                    >
                                                        <Trash2 class="size-4" aria-hidden="true" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Hapus penugasan</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </li>
                                    </ul>
                                </section>
                            </div>
                        </TooltipProvider>
                        <div
                            v-else
                            class="border-border/80 flex flex-col items-center gap-2 rounded-xl border border-dashed px-4 py-8 text-center"
                        >
                            <span
                                aria-hidden="true"
                                class="bg-muted flex size-10 items-center justify-center rounded-full"
                            >
                                <UserCheck class="text-muted-foreground size-5" />
                            </span>
                            <p class="text-sm font-medium">Belum ada interviewer yang ditugaskan.</p>
                            <p class="text-muted-foreground max-w-sm text-xs leading-relaxed">
                                Pilih interviewer dan divisi di atas untuk menugaskan.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <ConfirmationModal
                    :open="unassignDialogOpen"
                    title="Hapus penugasan?"
                    :description="pendingUnassignDescription"
                    confirm-text="Hapus"
                    cancel-text="Batal"
                    variant="destructive"
                    :loading="unassigningId !== null"
                    @confirm="confirmUnassign"
                    @cancel="cancelUnassign"
                    @update:open="
                        (v: boolean) => {
                            unassignDialogOpen = v;
                        }
                    "
                />

                <ConfirmationModal
                    :open="qrDialogOpen"
                    title="Kirim QR interview?"
                    :description="qrDialogDescription"
                    confirm-text="Kirim"
                    :loading="isSendingQr"
                    @confirm="confirmQr"
                    @cancel="cancelQr"
                    @update:open="
                        (v: boolean) => {
                            qrDialogOpen = v;
                        }
                    "
                >
                    <template #extra>
                        <label
                            v-if="qrSentCount > 0"
                            for="qr-include-sent"
                            class="border-border/70 flex cursor-pointer items-start gap-2.5 rounded-xl border px-3 py-2.5 text-left"
                        >
                            <Checkbox
                                id="qr-include-sent"
                                :model-value="includeSentQr"
                                class="mt-0.5"
                                @update:model-value="includeSentQr = $event === true"
                            />
                            <span class="text-sm leading-snug">
                                Sertakan {{ qrSentCount.toLocaleString('id-ID') }} applicant yang sudah terkirim
                                <span class="text-muted-foreground block text-xs">
                                    Kirim ulang QR ke applicant yang sudah dikirimi sebelumnya.
                                </span>
                            </span>
                        </label>
                    </template>
                </ConfirmationModal>

                <ConfirmationModal
                    :open="groupLinkDialogOpen"
                    title="Kirim link grup WA?"
                    :description="`Link grup dikirim 1 per 1 ke ${groupLinkEligibleCount} applicant yang lolos.`"
                    confirm-text="Kirim"
                    :loading="isSendingGroupLink"
                    @confirm="confirmGroupLink"
                    @cancel="cancelGroupLink"
                    @update:open="
                        (v: boolean) => {
                            groupLinkDialogOpen = v;
                        }
                    "
                />

                <InterviewerCreateSheet
                    :open="isCreateSheetOpen"
                    :divisions="divisions"
                    :initial-division-id="assignForm.recruitment_division_id"
                    @close="closeCreateSheet"
                    @created="onInterviewerCreated"
                />
            </TabsContent>

            <TabsContent value="settings" class="mt-4">
                <PeriodSettingsSection v-if="canEdit" :period="period" @saved="onSettingsSaved" />
            </TabsContent>
        </Tabs>
    </div>
</template>
