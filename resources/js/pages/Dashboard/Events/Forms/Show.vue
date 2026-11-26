<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { resolveBuilderImageUploadUrl } from '@/adapters/http/forms';
import { currentOrigin } from '@/adapters/browser/navigation';
import { onDocumentEvent } from '@/adapters/browser/domEvents';
import { matchesMediaQuery } from '@/adapters/browser/mediaQuery';
import { useFormBuilderAutosave } from '@/hooks/useFormBuilderAutosave';
import type { IFormBuilderAutosaveEndpoints } from '@/hooks/useFormBuilderAutosave';
import { builderPendingUploads } from '@/hooks/useBuilderImageUpload';
import { readFieldMetadata, readMetaText, readMetadataBag } from '@/lib/formFieldMetadata';
import { getFieldError } from '@/lib/error-message';
import { toggleVisibilitySelection } from '@/lib/formVisibility';
import { emptyFormRegistrationMetadata } from '@/lib/formRegistrationMetadata';

import { useToastNotifications } from '@/hooks/useToastNotifications';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import FormBuilderWorkspace from '@/components/modules/builder/FormBuilderWorkspace.vue';
import { useFormBuilderHistory } from '@/hooks/useFormBuilderHistory';
import FormSettingsPanel from '@/components/modules/builder/FormSettingsPanel.vue';
import FormReferralSettingsCard from '@/components/modules/builder/FormReferralSettingsCard.vue';
import { FORM_VISIBILITY_OPTIONS } from '@/components/modules/builder/formBuilderPalette';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { AutosaveStatus } from '@/components/ui/autosave-status';
import {
    ClipboardList,
    Undo2,
    Redo2,
    Check,
    Download,
    Eye,
    FileArchive,
    FileSpreadsheet,
    FileText,
    Inbox,
    PenLine,
    Settings,
    Share2,
    X,
} from 'lucide-vue-next';
import {
    isDisplayOnlyBuilderType,
    type IBackendField,
    type IBuilderField,
} from '@/components/modules/builder/fieldMapping';
import { defaultFormBannerState } from '@/components/modules/builder/formBanner';
import { hydrateFormBuilderState } from '@/components/modules/builder/formBuilderHydration';
import type { IFormSession, IFormSiblingOption } from '@/types/form';
import FormSchedulePanel from '@/components/modules/builder/FormSchedulePanel.vue';
import {
    answerPreview,
    formSubmissionReviewActionLabel,
    formSubmissionReviewIsPending,
    humanizeSubmissionKey,
    submissionReviewBadge,
} from '@/lib/formSubmissionsUi';
import { duIssuanceBadge } from '@/lib/duSubmissionUi';
import FormAnswerDetailSheet from '@/components/modules/dashboard/FormAnswerDetailSheet.vue';
import FormAnswerMobileList from '@/components/modules/dashboard/FormAnswerMobileList.vue';
import FormAnswerToolbar from '@/components/modules/dashboard/FormAnswerToolbar.vue';
import BulkFormAnswerReviewDialog from '@/components/modules/dashboard/BulkFormAnswerReviewDialog.vue';
import AppPagination from '@/components/core/AppPagination.vue';
import EmptyState from '@/components/core/EmptyState.vue';
import { usePaginationNavigation } from '@/hooks/usePaginationNavigation';
import { useFormSubmissionFilters } from '@/hooks/useFormSubmissionFilters';
import { useFormSubmissionReview } from '@/hooks/useFormSubmissionReview';
import { useFormSubmissionBulkReview } from '@/hooks/useFormSubmissionBulkReview';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import { formatDateTime } from '@/lib/dateTime';
import { setTopbar } from '@/hooks/useDashboardTopbar';
import { Checkbox } from '@/components/ui/checkbox';
import FormShareDialog from '@/components/modules/dashboard/FormShareDialog.vue';
import { useFormShareActions } from '@/hooks/useFormShareActions';
import type { IFormSharePayload } from '@/types/formShare';
import { useFormReferralCodes } from '@/hooks/useFormReferralCodes';
import {
    FORM_ANSWER_BULK_REVIEW_MAX_SELECTION,
    FORM_ANSWER_TABLE_FIXED_COLUMNS,
    FORM_ANSWER_TABLE_MAX_COLUMNS,
} from '@/constants/formAnswerReview';
import type { IFormSubmissionQuery } from '@/types/formSubmissions';
import type { IReferralPayload } from '@/types/referral';
import { routes } from '@/lib/routes';
import type { IDuCourseOption } from '@/types/du';

defineOptions({ layout: DashboardLayout });

type TShowTab = 'editor' | 'jawaban' | 'settings';

/** Metadata pagination jawaban dari server (satu halaman per request). */
interface ISubmissionsPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    event: { id: string; title: string };
    form: IForm;
    formShare: IFormSharePayload;
    fields: IBackendField[];
    siblingForms?: IFormSiblingOption[];
    saveFieldsUrl: string;
    updateFormUrl: string;
    /** Hanya halaman jawaban yang sedang aktif. */
    submissions?: IFormSubmission[];
    submissionsCount?: number;
    /** Metadata halaman jawaban; `submissions` berisi satu halaman saja. */
    submissionsPagination?: ISubmissionsPagination;
    /** Jadwal sesi dan status jawaban dari payload halaman show. */
    sessions?: IFormSession[];
    hasSubmissions?: boolean;
    /** ID yang boleh dipilih lewat "pilih semua" lintas halaman (dibatasi `bulkSelectableLimit`). */
    selectableSubmissionIds?: string[];
    bulkSelectableTotal?: number;
    bulkSelectableLimit?: number;
    referral: IReferralPayload;
    /** Filter aktif tab Jawaban dari query string. */
    query: IFormSubmissionQuery;
    /** URL unduhan jawaban dari backend; `csv` per field, `zip` per pengirim beserta berkas. */
    exportUrls: { csv: string; zip: string };
    /** Katalog course DU aktif untuk drawer jawaban; kosong untuk form tanpa integrasi DU. */
    duCatalog?: IDuCourseOption[];
}>();

const formSessions = computed<IFormSession[]>(() => props.sessions ?? props.form.sessions ?? []);
const hasSubmissions = computed<boolean>(
    () => props.hasSubmissions ?? props.form.hasSubmissions ?? (props.submissionsCount ?? 0) > 0
);

const page = usePage();
const { handleInertiaFormErrors, notifyAutosaveFailure, showErrorToast } = useToastNotifications();

const referralPayload = computed<IReferralPayload>(() => props.referral);
const referralEnabled = computed<boolean>(() => referralPayload.value.enabled);
const referralCodes = useFormReferralCodes({
    eventId: props.event.id,
    formId: props.form.id,
    payload: () => referralPayload.value,
    currentPath: () => new URL(page.url, currentOrigin()).pathname,
});
const referralProcessing = referralCodes.processing;
const referralFieldErrors = referralCodes.fieldErrors;
const referralErrorMessage = referralCodes.errorMessage;

/** Baca tab dari query string agar deep-link ?tab=jawaban bekerja saat refresh/back. */
function tabFromQuery(): TShowTab {
    const raw = new URLSearchParams(page.url.split('?')[1] ?? '').get('tab');
    if (raw === 'jawaban') return 'jawaban';
    return raw === 'settings' ? 'settings' : 'editor';
}
const activeTab = ref<TShowTab>(tabFromQuery());
/** Status buka menu Export di tab jawaban; dikendalikan eksplisit dari trigger native. */
const isExportMenuOpen = ref(false);
/** Di layar sempit trigger Export ada di kiri header, jadi menu rata kiri agar tidak terpotong. */
const exportMenuAlign = ref<'start' | 'end'>('end');
const exportTriggerRef = ref<InstanceType<typeof Button> | null>(null);

/** Buka/tutup menu Export; perataan dipilih saat dibuka sesuai lebar layar. */
function toggleExportMenu(): void {
    if (isExportMenuOpen.value) {
        isExportMenuOpen.value = false;
        return;
    }
    exportMenuAlign.value = matchesMediaQuery('(min-width: 768px)') ? 'end' : 'start';
    isExportMenuOpen.value = true;
}

/** Elemen trigger untuk anchor posisi menu; tanpa DropdownMenuTrigger tidak ada anchor otomatis. */
const exportTriggerElement = computed<HTMLElement | undefined>(() => {
    const element = exportTriggerRef.value?.$el;
    return element instanceof HTMLElement ? element : undefined;
});

/** Fokus kembali ke trigger Export; tanpa DropdownMenuTrigger tidak ada komponen yang mengurusnya. */
function focusExportTrigger(): void {
    exportTriggerElement.value?.focus();
}

/** Sinkronkan status buka menu Export dan pulihkan fokus trigger saat menu ditutup. */
function onExportMenuOpenChange(open: boolean): void {
    isExportMenuOpen.value = open;
    if (!open) focusExportTrigger();
}

/** Tutup menu Export: dipakai item menu dan tombol Escape. */
function closeExportMenu(): void {
    if (!isExportMenuOpen.value) return;
    isExportMenuOpen.value = false;
    focusExportTrigger();
}

/** Escape menutup menu Export; Reka tidak menanganinya di halaman ini. */
function onExportMenuEscape(event: KeyboardEvent): void {
    if (event.key !== 'Escape' || !isExportMenuOpen.value) return;
    event.stopPropagation();
    closeExportMenu();
}

/** Klik di luar menu dan trigger menutup menu; Reka tidak menutupnya di halaman ini. */
function onExportMenuPointerDown(event: PointerEvent): void {
    if (!isExportMenuOpen.value) return;

    const path = event.composedPath();
    const trigger = exportTriggerElement.value;
    if (trigger !== undefined && path.includes(trigger)) return;

    const insideMenu = path.some((node) => node instanceof HTMLElement && node.getAttribute('role') === 'menu');
    if (!insideMenu) closeExportMenu();
}

let releaseExportDismiss: (() => void) | null = null;

/** Pasang listener tutup menu hanya selama menu terbuka, lepas saat menu tertutup. */
watch(isExportMenuOpen, (open: boolean): void => {
    releaseExportDismiss?.();
    if (!open) {
        releaseExportDismiss = null;
        return;
    }

    const releaseKeydown = onDocumentEvent('keydown', onExportMenuEscape);
    const releasePointerDown = onDocumentEvent('pointerdown', onExportMenuPointerDown);
    releaseExportDismiss = () => {
        releaseKeydown();
        releasePointerDown();
    };
});
const formStatus = ref<'draft' | 'published'>(props.form.status ?? 'published');
const isChangingStatus = ref(false);
const isShareDialogOpen = ref(false);
const formShareActions = useFormShareActions(props.formShare);

watch(
    () => props.formShare.can_share_participant,
    (canShareParticipant: boolean): void => {
        formShareActions.payload.value = {
            ...formShareActions.payload.value,
            can_share_participant: canShareParticipant,
        };
    }
);

const statusActionLabel = computed<string>(() => (formStatus.value === 'draft' ? 'Publish form' : 'Jadikan draft'));

onMounted(() => {
    // Topbar builder: judul form + konteks acara, tanpa strip breadcrumb.
    setTopbar({
        title: props.form.title || 'Form tanpa judul',
        subtitle: `Edit form · ${props.event.title}`,
        segmentLabels: {
            [props.event.id]: props.event.title,
            [props.form.id]: props.form.title || 'Form tanpa judul',
        },
    });
});

/** Sinkronkan tab ke query string (?tab=jawaban) agar deep-link tetap bertahan saat refresh. */
watch(activeTab, (tab) => {
    const url = new URL(page.url, currentOrigin());
    url.searchParams.set('tab', tab);
    router.visit(`${url.pathname}${url.search}`, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

const settingsForm = useForm({
    _method: 'put',
    title: props.form.title,
    description: props.form.description,
    success_content: props.form.success_content ?? '',
    closed_at: props.form.closed_at ?? '',
    visible_for: [...props.form.visible_for],
    banner_url: props.form.banner_url ?? '',
    banner_caption: props.form.banner_caption ?? '',
});

const bannerState = ref(defaultFormBannerState());
const formFields = ref<IBuilderField[]>([]);
const formMetadata = ref(emptyFormRegistrationMetadata());

/** Endpoint upload gambar builder `POST {form}/images` (multipart field `image`). */
const builderImageUploadUrl = computed(() => resolveBuilderImageUploadUrl(props.event.id, props.form.id));

/** Ref ke FormBuilderWorkspace untuk memicu preview/save dari bar aksi inline (toolbar disembunyikan). */
const workspaceRef = ref<InstanceType<typeof FormBuilderWorkspace> | null>(null);

/** Sama dengan wb.isEmpty di workspace: kanvas belum punya field. */
const builderEmpty = computed(() => formFields.value.length === 0);

function requestPreview(): void {
    workspaceRef.value?.showPreview();
}

function requestSaveAll(): void {
    workspaceRef.value?.requestSave();
}

const formTitle = computed({
    get: () => settingsForm.title,
    set: (v: string) => {
        settingsForm.title = v;
    },
});
const formDescription = computed({
    get: () => settingsForm.description,
    set: (v: string) => {
        settingsForm.description = v;
    },
});
const successContent = computed({
    get: () => settingsForm.success_content ?? '',
    set: (v: string) => {
        settingsForm.success_content = v;
    },
});
const { canUndo, canRedo, undo, redo } = useFormBuilderHistory({
    formTitle,
    formDescription,
    successContent,
    banner: bannerState,
    formFields,
    formMetadata,
});

const closedAt = computed({
    get: () => settingsForm.closed_at ?? '',
    set: (v: string) => {
        settingsForm.closed_at = v;
    },
});
const visibleFor = computed({
    get: () => [...settingsForm.visible_for],
    set: (v: string[]) => {
        settingsForm.visible_for = v;
    },
});

/** Panel pengaturan meng-emit pilihan; state tetap satu sumber di `settingsForm.visible_for`. */
function onToggleVisibility(value: string, checked: boolean): void {
    visibleFor.value = toggleVisibilitySelection(visibleFor.value, value, checked);
}

const fieldErrors = computed(() => ({
    title: getFieldError(settingsForm.errors, 'title'),
    description: getFieldError(settingsForm.errors, 'description'),
    closed_at: getFieldError(settingsForm.errors, 'closed_at'),
    visible_for: getFieldError(settingsForm.errors, 'visible_for'),
}));

/**
 * Terapkan snapshot server (header + fields) ke state builder. Pemetaannya satu
 * implementasi bersama halaman create (`hydrateFormBuilderState`), bukan salinan.
 */
function applyServerSnapshot(): void {
    const hydrated = hydrateFormBuilderState({ form: props.form, fields: props.fields ?? [] });

    settingsForm.title = hydrated.title;
    settingsForm.description = hydrated.description;
    settingsForm.success_content = hydrated.successContent;
    settingsForm.closed_at = hydrated.closedAt;
    settingsForm.visible_for = hydrated.visibleFor;
    settingsForm.banner_url = hydrated.banner.bannerUrl;
    settingsForm.banner_caption = hydrated.banner.caption;

    bannerState.value = hydrated.banner;
    formMetadata.value = hydrated.metadata;
    formFields.value = hydrated.fields;
}

watch(() => props.fields, applyServerSnapshot, { immediate: true, deep: true });
watch(() => props.form, applyServerSnapshot, { deep: true, immediate: true });

// ── Autosave global: di-instantiasi SETELAH watcher hydrate di atas, agar snapshot
// awal menangkap state yang sudah terisi → tidak ada job save-redundan saat mount.
// Form di halaman ini sudah ada (bukan draf baru), jadi settings selalu ikut disimpan
// dan payload-nya membawa daftar field. ──
const autosaveEndpoints = computed<IFormBuilderAutosaveEndpoints>(() => ({
    fieldsUrl: props.saveFieldsUrl,
    settingsUrl: props.updateFormUrl,
}));

const showAutosave = useFormBuilderAutosave({
    models: {
        formTitle,
        formDescription,
        successContent,
        closedAt,
        visibleFor,
        banner: bannerState,
        formFields,
        formMetadata,
    },
    endpoints: autosaveEndpoints,
    // Draf boleh belum lengkap: autosave parsial (`autosave: true`) alih-alih 422.
    isDraft: computed<boolean>(() => formStatus.value === 'draft'),
    onError: (message) => notifyAutosaveFailure(message, () => void showAutosave.retry()),
});
const showSaveState = showAutosave.status;
const manualSaving = ref(false);

onUnmounted(() => {
    void showAutosave.flush();
    releaseExportDismiss?.();
    releaseExportDismiss = null;
});

/** Manual save: blokir autosave, tunggu save aktif selesai, lalu PUT dengan state terbaru. */
async function onSave(): Promise<void> {
    if (settingsForm.processing || manualSaving.value) return;
    if (builderPendingUploads.value > 0) {
        showErrorToast('Masih ada gambar yang diunggah. Tunggu sebentar lalu simpan lagi.');
        return;
    }

    manualSaving.value = true;

    try {
        // Suspend dulu: debounce batal dan tidak ada save remote baru. waitForIdle hanya
        // menunggu request yang sudah berjalan agar manual PUT tidak overlap.
        showAutosave.suspend();
        await showAutosave.waitForIdle();

        // Payload settings (termasuk daftar field) dibangun oleh satu implementasi di
        // `useFormBuilderAutosave`; `_method` tetap eksplisit untuk Inertia.
        settingsForm
            .transform(() => ({
                _method: 'put',
                ...showAutosave.buildSettingsPayload({ fields: showAutosave.buildFieldsPayload() }),
            }))
            .put(props.updateFormUrl, {
                preserveScroll: true,
                onSuccess: () => {
                    // Manual PUT menyimpan seluruh state; error autosave sebelumnya
                    // tidak lagi berlaku.
                    showAutosave.markSaved();
                    // Toast sukses berasal dari flash server (`FormController@update`).
                },
                onError: (errors) => {
                    handleInertiaFormErrors(errors, { title: 'Gagal menyimpan form' });
                },
                onFinish: () => {
                    showAutosave.resume();
                    manualSaving.value = false;
                },
            });
    } catch {
        showAutosave.resume();
        manualSaving.value = false;
        showErrorToast('Gagal menyimpan form.');
    }
}

/** Ubah status tanpa mengirim ulang field atau metadata form. */
async function toggleFormStatus(): Promise<void> {
    if (isChangingStatus.value || settingsForm.processing || manualSaving.value) return;

    const nextStatus = formStatus.value === 'draft' ? 'published' : 'draft';
    const hadUnsavedBuilderChanges = showAutosave.hasUnsavedChanges.value;
    let builderStatePersisted = false;
    isChangingStatus.value = true;
    showAutosave.suspend();

    try {
        await showAutosave.waitForIdle();
        settingsForm
            .transform(() => {
                if (nextStatus === 'draft') return { _method: 'put', status: nextStatus };

                return {
                    _method: 'put',
                    ...showAutosave.buildSettingsPayload({ fields: showAutosave.buildFieldsPayload() }),
                    status: nextStatus,
                };
            })
            .put(props.updateFormUrl, {
                preserveScroll: true,
                onSuccess: () => {
                    formStatus.value = nextStatus;
                    if (nextStatus === 'published') {
                        showAutosave.markSaved();
                        builderStatePersisted = true;
                    }
                    // Toast sukses berasal dari flash server (`FormController@update`).
                },
                onError: (errors) => {
                    handleInertiaFormErrors(errors, { title: 'Gagal mengubah status form' });
                },
                onFinish: () => {
                    showAutosave.resume();
                    if (hadUnsavedBuilderChanges && !builderStatePersisted) showAutosave.schedule();
                    isChangingStatus.value = false;
                },
                only: ['formShare'],
            });
    } catch {
        showAutosave.resume();
        isChangingStatus.value = false;
        showErrorToast('Gagal mengubah status form.');
    }
}

/** Jawaban terurut mengikuti urutan field di builder; sisa key (legacy) di akhir. */
const answerKeys = computed(() => {
    const orderMap = new Map<string, number>();
    props.fields.forEach((f) => orderMap.set(f.name, f.order));

    /** Display-only (banner/heading/paragraph/divider/image) bukan jawaban. */
    const displayOnlyNames = new Set(
        (props.fields ?? [])
            .filter((f) => {
                const builderType = readFieldMetadata(f)['builderType'];
                return f.name === 'form_banner' || isDisplayOnlyBuilderType(builderType);
            })
            .map((f) => f.name)
    );

    const keys = new Set<string>();
    props.fields.forEach((f) => {
        if (!displayOnlyNames.has(f.name)) keys.add(f.name);
    });
    for (const submission of props.submissions ?? []) {
        Object.keys(submission.answers ?? {}).forEach((key) => {
            if (!displayOnlyNames.has(key)) keys.add(key);
        });
    }

    return [...keys].sort((a, b) => {
        const hasA = orderMap.has(a);
        const hasB = orderMap.has(b);
        if (hasA && hasB) return (orderMap.get(a) ?? 0) - (orderMap.get(b) ?? 0);
        if (hasA) return -1;
        if (hasB) return 1;
        return a.localeCompare(b);
    });
});

/** Nilai `type` aktual untuk field berkas/foto: API `fileUpload`, builder `file_upload`/`image_upload`. */
const FILE_FIELD_TYPE_NAMES: ReadonlySet<string> = new Set(['fileUpload', 'file_upload', 'image_upload']);

function backendFieldBuilderType(field: IBackendField): string {
    return readMetaText(readMetadataBag(field.metadata), 'builderType');
}

/** True bila field adalah unggahan berkas/foto (banner dikecualikan — bukan jawaban). */
function isFileBackendField(field: IBackendField): boolean {
    if (field.name === 'form_banner' || backendFieldBuilderType(field) === 'banner') return false;
    if (FILE_FIELD_TYPE_NAMES.has(field.type)) return true;
    return FILE_FIELD_TYPE_NAMES.has(backendFieldBuilderType(field));
}

const fileFieldNames = computed(() => new Set((props.fields ?? []).filter(isFileBackendField).map((f) => f.name)));

/** Kolom Kode DU hanya muncul bila minimal satu baris halaman ini punya data DU. */
const duColumnVisible = computed<boolean>(() => (props.submissions ?? []).some((submission) => submission.du != null));

/**
 * Slot kolom field: batas tabel dikurangi kolom tetap, kolom Referral bila aktif,
 * dan kolom Kode DU bila tampil. Kolom field mengikuti urutan builder; sisanya
 * hanya terlihat di drawer detail.
 */
const fieldColumnBudget = computed<number>(
    () =>
        FORM_ANSWER_TABLE_MAX_COLUMNS -
        FORM_ANSWER_TABLE_FIXED_COLUMNS -
        (referralEnabled.value ? 1 : 0) -
        (duColumnVisible.value ? 1 : 0)
);

const answerColumnKeys = computed(() => answerKeys.value.filter((key) => !fileFieldNames.value.has(key)));

/** Kolom field yang tampil di tabel, sudah dipotong sesuai batas kolom. */
const tableAnswerKeys = computed(() => answerColumnKeys.value.slice(0, fieldColumnBudget.value));

const submissionRows = computed(() => props.submissions ?? []);
/** ID eligible untuk "pilih semua" lintas halaman; fallback array kosong agar hook tetap aman. */
const bulkSelectableIds = computed<readonly string[]>(() => props.selectableSubmissionIds ?? []);
/** Offset nomor baris agar halaman kedua mulai dari 21, bukan 1. */
const rowNumberOffset = computed<number>(() => {
    const pagination = props.submissionsPagination;
    if (!pagination) return 0;
    return (pagination.current_page - 1) * pagination.per_page;
});
/** Tampilkan keterangan batas hanya saat total eligible melebihi jumlah ID yang bisa dipilih. */
const showsBulkSelectionLimit = computed<boolean>(() => {
    const limit = props.bulkSelectableLimit ?? 0;
    return limit > 0 && (props.bulkSelectableTotal ?? 0) > bulkSelectableIds.value.length;
});
/** Bulk review ditolak server untuk D-Kelas (422): kontrolnya disembunyikan, guard server tetap berlaku. */
const bulkReviewEnabled = computed<boolean>(() => formMetadata.value.purpose !== 'd_kelas');

/** Toggle jadwal pertemuan hanya menulis metadata; tombol simpan form yang mempersistensikannya. */
function setMeetingScheduleEnabled(enabled: boolean): void {
    formMetadata.value.meeting_schedule_enabled = enabled;
}
const { goToPage: goToSubmissionPage, changePerPage: changeSubmissionPerPage } = usePaginationNavigation();
/** Filter daftar jawaban; state chip dipakai langsung oleh toolbar. */
const {
    search: answerSearch,
    reviewStatus: answerReviewStatus,
    referral: answerReferral,
    isLoading: answerFiltersLoading,
    hasActiveFilters: hasActiveAnswerFilters,
    resetFilters: resetAnswerFilters,
} = useFormSubmissionFilters({
    query: props.query,
    perPage: props.submissionsPagination?.per_page ?? 20,
});

/** Kolom tabel jawaban; dipakai colspan baris kosong agar header tetap utuh. */
const answersTableColspan = computed<number>(() => {
    let count = 4; // Pengirim, Status review, Dikirim, Aksi
    if (bulkReviewEnabled.value) count += 1;
    if (referralEnabled.value) count += 1;
    if (duColumnVisible.value) count += 1;

    return count + tableAnswerKeys.value.length;
});

/** Bedakan belum ada jawaban sama sekali dari hasil filter yang kosong. */
const answersEmptyKind = computed<'empty' | 'filter' | 'search'>(() => {
    if (!hasSubmissions.value) return 'empty';

    return answerSearch.value.trim() === '' ? 'filter' : 'search';
});

const answersEmptyStateProps = computed(() => ({
    kind: answersEmptyKind.value,
    query: answerSearch.value,
    title: hasSubmissions.value ? undefined : 'Belum ada jawaban',
    description: hasSubmissions.value
        ? undefined
        : 'Saat ada yang mengisi dan mengirim formulir ini, daftar jawaban akan muncul di sini beserta status review-nya.',
}));
const {
    selectedSubmissionIds,
    selectedCount,
    selectableSelectionState,
    ineligibleSelectedCount,
    isSubmitting: isBulkReviewSubmitting,
    actionStatus: bulkReviewActionStatus,
    actionError: bulkReviewActionError,
    isConfirmOpen: isBulkReviewConfirmOpen,
    pendingDecision: bulkReviewPendingDecision,
    isSelected: isSubmissionSelected,
    setSelectableSelection,
    setSubmissionSelection,
    clearSelection,
    requestBulkReview,
    setDialogOpen: setBulkReviewDialogOpen,
    confirmBulkReview,
    cancelBulkReview,
} = useFormSubmissionBulkReview({
    eventId: props.event.id,
    formId: props.form.id,
    submissions: submissionRows,
    selectableSubmissionIds: bulkSelectableIds,
    reloadOnly: ['submissions'],
});
const submissionLabelMap = computed(() => {
    const map: Record<string, string> = {};
    props.fields.forEach((f) => {
        map[f.name] = f.label;
    });
    return map;
});
const humanizeKey = (key: string): string => humanizeSubmissionKey(submissionLabelMap.value, key);
const answerPreviewOf = (value: unknown): string => answerPreview(value);

/** Nama file polos untuk sel lampiran — tanpa link, tanpa membuka tab baru. */
function fileNameOf(value: unknown): string {
    const raw = typeof value === 'string' ? value.trim() : '';
    if (!raw) return 'Lampiran';
    try {
        const path = new URL(raw, currentOrigin()).pathname;
        const last = path.split('/').filter(Boolean).pop() ?? '';
        return decodeURIComponent(last) || 'Lampiran';
    } catch {
        const last = raw.split('/').filter(Boolean).pop() ?? '';
        return last || 'Lampiran';
    }
}

/** Field builder dipetakan ke bentuk IFormField untuk pratinjau jawaban di drawer. */
const detailFields = computed<IFormField[]>(() =>
    (props.fields ?? []).map((f) => ({
        id: f.id,
        type: f.type,
        label: f.label,
        description: f.description,
        name: f.name,
        order: f.order,
        metadata: f.metadata ?? {},
    }))
);

// ── Detail drawer + review jawaban ──
const selectedSubmission = ref<IFormSubmission | null>(null);
const isDetailOpen = ref(false);

const duCatalog = computed<IDuCourseOption[]>(() => props.duCatalog ?? []);
const duMappingUrl = computed<string>(() => routes.admin.events.forms.duFieldMap(props.event.id, props.form.id));
const duIssueUrl = computed<string>(() => {
    const submission = selectedSubmission.value;

    return submission === null
        ? ''
        : routes.admin.events.forms.duIssuance(props.event.id, props.form.id, submission.id);
});

/**
 * Mapping DU tersimpan untuk semua jawaban dengan opsi yang sama, jadi daftar
 * dimuat ulang dan jawaban terpilih disegarkan dari payload server.
 */
function onDuChanged(): void {
    router.reload({
        only: ['submissions'],
        onSuccess: (): void => {
            const current = selectedSubmission.value;

            if (current === null) return;

            selectedSubmission.value =
                (props.submissions ?? []).find((submission) => submission.id === current.id) ?? current;
        },
    });
}

const { isReviewing: isSubmissionReviewing, reviewSubmission } = useFormSubmissionReview({
    eventId: props.event.id,
    formId: props.form.id,
    reloadOnly: ['submissions'],
    onReviewed: (submissionId: string): void => {
        const next = (props.submissions ?? []).find((candidate) => candidate.id === submissionId) ?? null;
        if (next && selectedSubmission.value?.id === submissionId) {
            selectedSubmission.value = next;
        }
    },
});

function openSubmissionDetail(submission: IFormSubmission): void {
    selectedSubmission.value = submission;
    isDetailOpen.value = true;
}

function isRowInteractiveTarget(target: EventTarget | null): boolean {
    return (
        target instanceof Element &&
        target.closest('button, a, input, [role="checkbox"], [data-row-interactive]') !== null
    );
}

function onSubmissionRowClick(event: MouseEvent, submission: IFormSubmission): void {
    if (isRowInteractiveTarget(event.target)) return;
    openSubmissionDetail(submission);
}

function onSubmissionRowKeydown(event: KeyboardEvent, submission: IFormSubmission): void {
    if (event.target !== event.currentTarget || (event.key !== 'Enter' && event.key !== ' ')) return;
    event.preventDefault();
    openSubmissionDetail(submission);
}

function onDetailReview(payload: { action: 'accept' | 'reject'; submission: IFormSubmission }): void {
    reviewSubmission(payload.action, payload.submission);
}

function acceptLabel(submission: IFormSubmission): string {
    return formSubmissionReviewActionLabel(
        'accept',
        isSubmissionReviewing(submission.id),
        submission.user?.name ?? 'pengirim'
    );
}

function rejectLabel(submission: IFormSubmission): string {
    return formSubmissionReviewActionLabel(
        'reject',
        isSubmissionReviewing(submission.id),
        submission.user?.name ?? 'pengirim'
    );
}
</script>

<template>
    <Head :title="`Edit: ${form.title}`" />

    <div class="flex h-full min-h-0 min-w-0 flex-col gap-4">
        <Tabs
            v-model="activeTab"
            class="flex min-h-0 w-full flex-1 flex-col gap-4"
            :unmount-on-hide="false"
            aria-label="Konten form"
        >
            <div class="border-border/60 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <div class="flex min-w-0 flex-wrap items-center gap-3">
                    <TabsList class="bg-muted/40 h-auto min-h-10 flex-wrap gap-1 rounded-xl p-1">
                        <TabsTrigger
                            value="editor"
                            class="data-[state=active]:bg-card gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium data-[state=active]:shadow-sm"
                        >
                            <PenLine class="size-4 shrink-0" aria-hidden="true" />
                            Editor
                        </TabsTrigger>
                        <TabsTrigger
                            value="jawaban"
                            class="data-[state=active]:bg-card gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium data-[state=active]:shadow-sm"
                        >
                            <Inbox class="size-4 shrink-0" aria-hidden="true" />
                            Jawaban
                            <Badge
                                v-if="submissionsCount && submissionsCount > 0"
                                variant="secondary"
                                class="ml-0.5 h-5 min-w-5 px-1.5 text-[10px] font-semibold tabular-nums"
                            >
                                {{ submissionsCount }}
                            </Badge>
                        </TabsTrigger>
                        <TabsTrigger
                            value="settings"
                            class="data-[state=active]:bg-card gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium data-[state=active]:shadow-sm"
                        >
                            <Settings class="size-4 shrink-0" aria-hidden="true" />
                            Pengaturan Form
                        </TabsTrigger>
                    </TabsList>
                    <AutosaveStatus :status="showSaveState" variant="inline" @retry="showAutosave.retry" />
                </div>

                <div class="ml-auto flex shrink-0 items-center gap-2">
                    <Button
                        v-if="activeTab === 'editor'"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="hidden size-9 shrink-0 px-0 sm:inline-flex"
                        :disabled="!canUndo"
                        title="Undo (Ctrl+Z)"
                        aria-label="Undo perubahan"
                        @click="undo"
                    >
                        <Undo2 class="size-4" aria-hidden="true" />
                    </Button>
                    <Button
                        v-if="activeTab === 'editor'"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="hidden size-9 shrink-0 px-0 sm:inline-flex"
                        :disabled="!canRedo"
                        title="Redo (Ctrl+Shift+Z)"
                        aria-label="Redo perubahan"
                        @click="redo"
                    >
                        <Redo2 class="size-4" aria-hidden="true" />
                    </Button>
                    <Button
                        v-if="activeTab === 'editor'"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="hidden px-2.5 text-xs font-medium sm:inline-flex sm:px-3 sm:text-sm"
                        aria-haspopup="dialog"
                        @click="isShareDialogOpen = true"
                    >
                        <Share2 class="mr-1.5 size-4" aria-hidden="true" />
                        Bagikan
                    </Button>
                    <Button
                        v-if="activeTab !== 'jawaban'"
                        variant="outline"
                        size="sm"
                        class="hidden px-2.5 text-xs font-medium sm:inline-flex sm:px-3 sm:text-sm"
                        :disabled="isChangingStatus || settingsForm.processing || manualSaving"
                        :aria-label="statusActionLabel"
                        @click="toggleFormStatus"
                    >
                        {{ isChangingStatus ? 'Memproses…' : statusActionLabel }}
                    </Button>
                    <Button
                        v-if="activeTab !== 'jawaban'"
                        variant="outline"
                        size="sm"
                        class="border-border/80 bg-background/90 hidden px-3 text-sm font-medium shadow-sm sm:inline-flex"
                        :disabled="builderEmpty"
                        aria-label="Pratinjau formulir"
                        @click="requestPreview"
                    >
                        <Eye class="size-4 shrink-0 sm:hidden" aria-hidden="true" />
                        <span>Pratinjau</span>
                    </Button>
                    <Button
                        v-if="activeTab !== 'jawaban'"
                        size="sm"
                        class="hidden px-3 text-sm font-medium shadow-sm sm:inline-flex sm:px-4"
                        :disabled="settingsForm.processing || manualSaving"
                        @click="requestSaveAll"
                    >
                        <span class="sm:hidden">Simpan</span>
                        <span class="hidden sm:inline">Save All</span>
                    </Button>
                </div>
            </div>

            <TabsContent value="editor" class="mt-0 min-h-0 flex-1">
                <FormBuilderWorkspace
                    ref="workspaceRef"
                    v-model:form-title="formTitle"
                    v-model:form-description="formDescription"
                    v-model:closed-at="closedAt"
                    v-model:visible-for="visibleFor"
                    v-model:banner="bannerState"
                    v-model:form-fields="formFields"
                    v-model:form-metadata="formMetadata"
                    :image-upload-url="builderImageUploadUrl"
                    :referral-enabled="referralPayload.enabled"
                    :processing="settingsForm.processing || manualSaving"
                    @save="onSave"
                    @open-settings="activeTab = 'settings'"
                >
                    <template #mobile-actions>
                        <div class="grid grid-cols-2 gap-2">
                            <Button
                                variant="outline"
                                class="border-border/80 bg-background h-11 min-w-0 gap-1.5 px-3 text-sm font-medium whitespace-nowrap shadow-sm"
                                aria-haspopup="dialog"
                                @click="isShareDialogOpen = true"
                            >
                                <Share2 class="size-4 shrink-0" aria-hidden="true" />
                                Bagikan
                            </Button>
                            <Button
                                variant="outline"
                                class="border-border/80 bg-background h-11 min-w-0 px-3 text-sm font-medium whitespace-nowrap shadow-sm"
                                :disabled="isChangingStatus || settingsForm.processing || manualSaving"
                                :aria-label="statusActionLabel"
                                @click="toggleFormStatus"
                            >
                                {{ isChangingStatus ? 'Memproses…' : statusActionLabel }}
                            </Button>
                        </div>
                    </template>
                </FormBuilderWorkspace>
            </TabsContent>

            <TabsContent value="settings" class="mt-0 min-h-0 flex-1 overflow-y-auto">
                <div class="mx-auto w-full max-w-2xl">
                    <FormSettingsPanel
                        v-model:closed-at="closedAt"
                        v-model:visible-for="visibleFor"
                        v-model:form-metadata="formMetadata"
                        v-model:success-content="successContent"
                        id-prefix="tab"
                        :field-errors="fieldErrors"
                        :visibility-options="FORM_VISIBILITY_OPTIONS"
                        :sibling-forms="siblingForms ?? []"
                        :has-submissions="hasSubmissions"
                        @toggle-visibility="onToggleVisibility"
                    />
                    <FormSchedulePanel
                        v-if="formMetadata.purpose === 'd_kelas' || formMetadata.purpose === 'registration'"
                        :event-id="event.id"
                        :form-id="form.id"
                        :sessions="formSessions"
                        :toggleable="formMetadata.purpose === 'registration'"
                        :enabled="formMetadata.purpose === 'd_kelas' || formMetadata.meeting_schedule_enabled"
                        @update:enabled="setMeetingScheduleEnabled"
                    />
                    <FormReferralSettingsCard
                        v-if="formMetadata.purpose === 'registration' || formMetadata.purpose === 'd_kelas'"
                        :referral="referralPayload"
                        :processing="referralProcessing"
                        :field-errors="referralFieldErrors"
                        :error-message="referralErrorMessage"
                        @toggle-enabled="referralCodes.toggleEnabled"
                        @create="referralCodes.createCode"
                        @update="referralCodes.updateCode"
                        @remove="referralCodes.deleteCode"
                        @clear-errors="referralCodes.clearErrors"
                    />
                </div>
            </TabsContent>

            <TabsContent value="jawaban" class="mt-0 flex min-h-0 flex-1 flex-col overflow-hidden">
                <!-- Identitas section berdiri sendiri, terpisah dari kartu tabel. -->
                <div class="mb-2 flex min-w-0 shrink-0 items-center gap-2.5">
                    <ClipboardList class="text-primary size-5 shrink-0" aria-hidden="true" />
                    <div class="min-w-0">
                        <h2 class="font-display text-foreground text-base font-semibold tracking-[-0.01em]">
                            Daftar jawaban
                        </h2>
                        <p class="text-muted-foreground text-xs">
                            Total {{ submissionsCount ?? submissionRows.length }} jawaban masuk.
                        </p>
                    </div>
                </div>

                <div
                    data-testid="form-answers-card"
                    class="app-surface flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl p-0"
                >
                    <FormAnswerToolbar
                        v-model:search="answerSearch"
                        v-model:review-status="answerReviewStatus"
                        v-model:referral="answerReferral"
                        :referral-enabled="referralEnabled"
                        :has-active-filters="hasActiveAnswerFilters"
                        @reset="resetAnswerFilters"
                    >
                        <template #actions>
                            <!-- Unduhan jawaban: FileArchive mewakili paket ZIP, FileSpreadsheet mewakili CSV tabular. -->
                            <!-- Trigger native: toggle DropdownMenuTrigger tidak membuka menu di halaman ini, jadi status buka dikendalikan ref. -->
                            <DropdownMenu
                                v-if="activeTab === 'jawaban'"
                                :open="isExportMenuOpen"
                                @update:open="onExportMenuOpenChange"
                            >
                                <Button
                                    ref="exportTriggerRef"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="border-border/80 bg-background/90 text-sm font-medium shadow-sm"
                                    aria-haspopup="menu"
                                    :aria-expanded="isExportMenuOpen"
                                    aria-label="Export jawaban"
                                    @click="toggleExportMenu"
                                >
                                    <Download class="size-4 shrink-0" aria-hidden="true" />
                                    Export
                                </Button>
                                <DropdownMenuContent
                                    :align="exportMenuAlign"
                                    :side-offset="4"
                                    :reference="exportTriggerElement"
                                    class="w-60"
                                >
                                    <DropdownMenuItem as-child>
                                        <a
                                            :href="props.exportUrls.zip"
                                            download
                                            class="flex min-h-[44px] w-full cursor-pointer items-center gap-2"
                                            @click="closeExportMenu"
                                        >
                                            <FileArchive
                                                class="text-muted-foreground size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                            Download dengan file (ZIP)
                                        </a>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <a
                                            :href="props.exportUrls.csv"
                                            download
                                            class="flex min-h-[44px] w-full cursor-pointer items-center gap-2"
                                            @click="closeExportMenu"
                                        >
                                            <FileSpreadsheet
                                                class="text-muted-foreground size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                            Download biasa (CSV)
                                        </a>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </template>
                    </FormAnswerToolbar>

                    <div
                        v-if="bulkReviewEnabled && selectedCount > 0"
                        class="border-border/60 bg-primary/[0.04] flex flex-col gap-3 border-b px-4 py-3 sm:flex-row sm:items-center sm:px-5"
                    >
                        <span class="text-muted-foreground mr-auto text-sm font-medium tabular-nums sm:mr-1">
                            {{ selectedCount }} dipilih
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-10 min-w-0 flex-1 px-3 text-sm font-medium sm:flex-none"
                            :disabled="
                                isBulkReviewSubmitting ||
                                ineligibleSelectedCount > 0 ||
                                selectedCount > FORM_ANSWER_BULK_REVIEW_MAX_SELECTION
                            "
                            @click="requestBulkReview('accepted')"
                        >
                            Terima
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="border-destructive/35 text-destructive hover:bg-destructive/10 hover:text-destructive h-10 min-w-0 flex-1 px-3 text-sm font-medium sm:flex-none"
                            :disabled="
                                isBulkReviewSubmitting ||
                                ineligibleSelectedCount > 0 ||
                                selectedCount > FORM_ANSWER_BULK_REVIEW_MAX_SELECTION
                            "
                            @click="requestBulkReview('rejected')"
                        >
                            Tolak
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-10 basis-full px-2 text-sm font-medium sm:basis-auto"
                            :disabled="isBulkReviewSubmitting"
                            @click="clearSelection"
                        >
                            Batalkan pilihan
                        </Button>
                        <p
                            v-if="ineligibleSelectedCount > 0"
                            class="text-muted-foreground basis-full text-xs leading-relaxed"
                        >
                            {{ ineligibleSelectedCount }} jawaban terpilih tidak bisa diproses massal.
                        </p>
                        <p
                            v-else-if="selectedCount > FORM_ANSWER_BULK_REVIEW_MAX_SELECTION"
                            class="text-muted-foreground basis-full text-xs leading-relaxed"
                        >
                            Pilih maksimal {{ FORM_ANSWER_BULK_REVIEW_MAX_SELECTION }} jawaban sekaligus.
                        </p>
                        <p
                            v-if="showsBulkSelectionLimit"
                            class="text-muted-foreground basis-full text-xs leading-relaxed"
                        >
                            Maksimal {{ props.bulkSelectableLimit }} jawaban per proses; pilih semua mengambil
                            {{ props.bulkSelectableLimit }} terbaru.
                        </p>
                        <span class="sr-only" role="status" aria-live="polite" aria-atomic="true">
                            {{ selectedCount > 0 ? `${selectedCount} dipilih` : 'Tidak ada jawaban dipilih' }}
                        </span>
                    </div>

                    <BulkFormAnswerReviewDialog
                        :open="isBulkReviewConfirmOpen"
                        :selected-count="selectedCount"
                        :decision="bulkReviewPendingDecision"
                        :loading="isBulkReviewSubmitting"
                        :error="bulkReviewActionError"
                        @confirm="confirmBulkReview"
                        @cancel="cancelBulkReview"
                        @update:open="setBulkReviewDialogOpen"
                    />
                    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
                        <template v-if="bulkReviewActionStatus === 'success'">Review massal berhasil.</template>
                        <template v-else-if="bulkReviewActionStatus === 'error'">{{ bulkReviewActionError }}</template>
                        <template v-else-if="bulkReviewActionStatus === 'submitting'"
                            >Review massal sedang diproses.</template
                        >
                    </p>

                    <div
                        class="hidden min-h-0 flex-1 md:block [&>div]:h-full"
                        :aria-busy="answerFiltersLoading ? 'true' : undefined"
                        :class="answerFiltersLoading ? 'opacity-60' : undefined"
                    >
                        <!-- Hanya isi tabel yang bergulir: header menempel, halaman tidak ikut bergeser. -->
                        <Table>
                            <TableHeader class="bg-muted [&_th]:bg-muted [&_th]:sticky [&_th]:top-0 [&_th]:z-10">
                                <TableRow class="hover:bg-transparent">
                                    <TableHead v-if="bulkReviewEnabled" class="h-10 w-11 px-4">
                                        <Checkbox
                                            :model-value="selectableSelectionState"
                                            aria-label="Pilih semua jawaban"
                                            @update:model-value="setSelectableSelection"
                                        />
                                    </TableHead>
                                    <TableHead
                                        class="text-muted-foreground h-10 w-[210px] px-4 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Pengirim
                                    </TableHead>
                                    <TableHead
                                        class="text-muted-foreground h-10 w-[124px] px-4 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Status review
                                    </TableHead>
                                    <TableHead
                                        v-if="referralEnabled"
                                        class="text-muted-foreground h-10 w-[176px] px-4 text-center text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Referral
                                    </TableHead>
                                    <TableHead
                                        v-if="duColumnVisible"
                                        class="text-muted-foreground h-10 w-[160px] px-4 text-center text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Kode DU
                                    </TableHead>
                                    <TableHead
                                        v-for="key in tableAnswerKeys"
                                        :key="key"
                                        class="text-muted-foreground h-10 max-w-[200px] min-w-[104px] px-4 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        {{ humanizeKey(key) }}
                                    </TableHead>
                                    <TableHead
                                        class="text-muted-foreground h-10 w-[96px] px-4 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Dikirim
                                    </TableHead>
                                    <TableHead
                                        class="text-muted-foreground h-10 w-[104px] px-1.5 text-center text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="submissionRows.length === 0" class="hover:bg-transparent">
                                    <TableCell :colspan="answersTableColspan" class="p-0">
                                        <EmptyState v-bind="answersEmptyStateProps" :surface="false" class="px-4 py-10">
                                            <Button
                                                v-if="hasSubmissions"
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="h-10"
                                                @click="resetAnswerFilters"
                                            >
                                                Hapus filter
                                            </Button>
                                        </EmptyState>
                                    </TableCell>
                                </TableRow>
                                <template v-else>
                                    <TableRow
                                        v-for="(submission, index) in submissionRows"
                                        :key="submission.id"
                                        :tabindex="0"
                                        :aria-label="`Lihat detail jawaban dari ${submission.user?.name ?? 'pengirim'}`"
                                        class="group border-border/60 hover:bg-muted/40 focus-visible:ring-ring/70 cursor-pointer border-b transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                        @click="onSubmissionRowClick($event, submission)"
                                        @keydown="onSubmissionRowKeydown($event, submission)"
                                    >
                                        <TableCell v-if="bulkReviewEnabled" class="px-4 py-2">
                                            <Checkbox
                                                :model-value="isSubmissionSelected(submission.id)"
                                                :aria-label="`Pilih jawaban nomor ${rowNumberOffset + index + 1} dari ${submission.user?.name ?? 'pengirim'}`"
                                                @update:model-value="setSubmissionSelection(submission.id, $event)"
                                            />
                                        </TableCell>
                                        <TableCell class="px-4 py-2">
                                            <div class="flex items-center gap-2.5">
                                                <UserAvatarFallback
                                                    :src="submission.user?.avatar ?? null"
                                                    :seed="userAvatarSeed(submission.user)"
                                                    avatar-class="size-8 shrink-0 rounded-lg border border-border"
                                                    fallback-round-class="rounded-lg"
                                                />
                                                <div class="w-[152px] min-w-0">
                                                    <p class="text-foreground truncate text-sm font-medium">
                                                        {{ submission.user?.name ?? 'Tanpa nama' }}
                                                    </p>
                                                    <p class="text-muted-foreground truncate text-[11px]">
                                                        {{ submission.user?.email ?? '—' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell class="px-4 py-2">
                                            <Badge
                                                variant="outline"
                                                :class="[
                                                    'font-medium whitespace-nowrap',
                                                    submissionReviewBadge(submission.review_status).class,
                                                ]"
                                            >
                                                {{ submissionReviewBadge(submission.review_status).label }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell v-if="referralEnabled" class="px-4 py-2.5 text-center">
                                            <span
                                                v-if="submission.referral?.code"
                                                :title="submission.referral.code"
                                                class="border-border/70 bg-muted/50 text-foreground inline-block max-w-full truncate rounded-md border px-1.5 py-0.5 align-middle font-mono text-[11px] font-semibold"
                                            >
                                                {{ submission.referral.code }}
                                            </span>
                                            <Badge
                                                v-else
                                                variant="outline"
                                                class="text-muted-foreground/70 font-medium whitespace-nowrap"
                                            >
                                                Tanpa referral
                                            </Badge>
                                        </TableCell>
                                        <TableCell v-if="duColumnVisible" class="px-4 py-2.5 text-center">
                                            <span
                                                v-if="
                                                    submission.du &&
                                                    submission.du.issuance?.status === 'issued' &&
                                                    submission.du.issuance.code
                                                "
                                                :title="submission.du.issuance.code"
                                                class="border-border/70 bg-muted/50 text-foreground inline-block max-w-full truncate rounded-md border px-1.5 py-0.5 align-middle font-mono text-[11px] font-semibold"
                                            >
                                                {{ submission.du.issuance.code }}
                                            </span>
                                            <Badge
                                                v-else-if="submission.du && duIssuanceBadge(submission.du)"
                                                variant="outline"
                                                :class="[
                                                    'font-medium whitespace-nowrap',
                                                    duIssuanceBadge(submission.du)?.className,
                                                ]"
                                            >
                                                {{ duIssuanceBadge(submission.du)?.label }}
                                            </Badge>
                                            <span
                                                v-else
                                                class="text-muted-foreground/70 text-xs"
                                                aria-label="Tanpa data DU"
                                                >—</span
                                            >
                                        </TableCell>
                                        <TableCell
                                            v-for="key in tableAnswerKeys"
                                            :key="key"
                                            class="text-muted-foreground max-w-[200px] px-4 py-2.5 text-xs leading-relaxed"
                                        >
                                            <span
                                                v-if="fileFieldNames.has(key)"
                                                class="flex min-w-0 items-center gap-1.5"
                                                :title="fileNameOf(submission.answers?.[key])"
                                            >
                                                <FileText
                                                    class="text-muted-foreground size-3.5 shrink-0"
                                                    aria-hidden="true"
                                                />
                                                <span class="text-foreground/85 line-clamp-1 font-normal break-all">
                                                    {{ fileNameOf(submission.answers?.[key]) }}
                                                </span>
                                            </span>
                                            <span
                                                v-else
                                                class="text-foreground/85 line-clamp-2 break-words"
                                                :title="answerPreviewOf(submission.answers?.[key])"
                                            >
                                                {{ answerPreviewOf(submission.answers?.[key]) }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="text-muted-foreground px-4 py-2">
                                            <span class="block text-[11px] whitespace-nowrap">{{
                                                formatDateTime({
                                                    value: submission.submitted_at,
                                                    variant: 'date-medium',
                                                })
                                            }}</span>
                                            <span
                                                class="text-muted-foreground/80 block text-[10px] whitespace-nowrap tabular-nums"
                                                >{{
                                                    formatDateTime({
                                                        value: submission.submitted_at,
                                                        variant: 'time-short',
                                                    })
                                                }}</span
                                            >
                                        </TableCell>
                                        <TableCell class="border-border/60 px-1.5 py-2 text-center whitespace-nowrap">
                                            <div class="flex items-center justify-center gap-0.5">
                                                <Button
                                                    v-if="formSubmissionReviewIsPending(submission)"
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="text-success hover:bg-success/10 hover:text-success focus-visible:ring-success/40 rounded-md"
                                                    :aria-label="acceptLabel(submission)"
                                                    :disabled="isSubmissionReviewing(submission.id)"
                                                    @click="reviewSubmission('accept', submission)"
                                                >
                                                    <Check class="size-4" aria-hidden="true" />
                                                </Button>
                                                <Button
                                                    v-if="formSubmissionReviewIsPending(submission)"
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="text-destructive hover:bg-destructive/10 hover:text-destructive focus-visible:ring-destructive/40 rounded-md"
                                                    :aria-label="rejectLabel(submission)"
                                                    :disabled="isSubmissionReviewing(submission.id)"
                                                    @click="reviewSubmission('reject', submission)"
                                                >
                                                    <X class="size-4" aria-hidden="true" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="hover:bg-primary/10 hover:text-primary rounded-md"
                                                    :aria-label="
                                                        formSubmissionReviewActionLabel(
                                                            'detail',
                                                            false,
                                                            submission.user?.name ?? 'pengirim'
                                                        )
                                                    "
                                                    @click="openSubmissionDetail(submission)"
                                                >
                                                    <Eye class="size-4" aria-hidden="true" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                    </div>

                    <div v-if="submissionRows.length === 0" class="md:hidden">
                        <EmptyState v-bind="answersEmptyStateProps" :surface="false" class="p-4">
                            <Button
                                v-if="hasSubmissions"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-10"
                                @click="resetAnswerFilters"
                            >
                                Hapus filter
                            </Button>
                        </EmptyState>
                    </div>

                    <div
                        v-if="submissionRows.length > 0"
                        class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-3 md:hidden"
                    >
                        <div
                            v-if="bulkReviewEnabled"
                            class="border-border/60 bg-muted/20 flex items-center gap-2 rounded-lg border px-3 py-2"
                        >
                            <Checkbox
                                :model-value="selectableSelectionState"
                                aria-label="Pilih semua jawaban"
                                @update:model-value="setSelectableSelection"
                            />
                            <span class="text-muted-foreground text-xs">Pilih semua jawaban</span>
                        </div>
                        <FormAnswerMobileList
                            :submissions="submissionRows"
                            :selected-submission-ids="selectedSubmissionIds"
                            :selectable="bulkReviewEnabled"
                            @detail="openSubmissionDetail"
                            @select="setSubmissionSelection"
                        />
                    </div>

                    <AppPagination
                        v-if="props.submissionsPagination"
                        class="border-border/60 shrink-0 border-t px-4 py-3 sm:px-5"
                        :current-page="props.submissionsPagination.current_page"
                        :last-page="props.submissionsPagination.last_page"
                        :total="props.submissionsPagination.total"
                        :per-page="props.submissionsPagination.per_page"
                        :per-page-options="[10, 20, 50, 100]"
                        @page-change="goToSubmissionPage"
                        @per-page-change="changeSubmissionPerPage"
                    />
                </div>

                <FormAnswerDetailSheet
                    v-model:open="isDetailOpen"
                    :submission="selectedSubmission"
                    :answer-keys="answerKeys"
                    :fields="detailFields"
                    :humanize-key="humanizeKey"
                    :is-submission-reviewing="isSubmissionReviewing"
                    :du-catalog="duCatalog"
                    :du-mapping-url="duMappingUrl"
                    :du-issue-url="duIssueUrl"
                    @review="onDetailReview"
                    @du-changed="onDuChanged"
                />
            </TabsContent>
        </Tabs>
        <FormShareDialog v-model:open="isShareDialogOpen" :form-title="formTitle" :actions="formShareActions" />
    </div>
</template>
