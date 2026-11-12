<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { resolveBuilderImageUploadUrl } from '@/adapters/http/forms';
import { currentOrigin } from '@/adapters/browser/navigation';
import { useFormBuilderAutosave } from '@/hooks/useFormBuilderAutosave';
import type { IFormBuilderAutosaveEndpoints } from '@/hooks/useFormBuilderAutosave';
import { builderPendingUploads } from '@/hooks/useBuilderImageUpload';
import { readFieldMetadata, readMetaText, readMetadataBag } from '@/lib/formFieldMetadata';
import { getFieldError, humanizeErrorMessage } from '@/lib/error-message';
import { toggleVisibilitySelection } from '@/lib/formVisibility';
import { emptyFormRegistrationMetadata } from '@/lib/formRegistrationMetadata';

import { useToastNotifications } from '@/hooks/useToastNotifications';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import FormBuilderWorkspace from '@/components/modules/builder/FormBuilderWorkspace.vue';
import FormSettingsPanel from '@/components/modules/builder/FormSettingsPanel.vue';
import { FORM_VISIBILITY_OPTIONS } from '@/components/modules/builder/formBuilderPalette';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { AutosaveStatus } from '@/components/ui/autosave-status';
import { Check, Eye, FileText, Inbox, PenLine, Settings, Share2, X } from 'lucide-vue-next';
import {
    isDisplayOnlyBuilderType,
    type IBackendField,
    type IBuilderField,
} from '@/components/modules/builder/fieldMapping';
import { defaultFormBannerState } from '@/components/modules/builder/formBanner';
import { hydrateFormBuilderState } from '@/components/modules/builder/formBuilderHydration';
import type { IFormSiblingOption } from '@/types/form';
import {
    answerPreview,
    formSubmissionReviewActionLabel,
    formSubmissionReviewIsPending,
    humanizeSubmissionKey,
    submissionFileUrl,
    submissionReviewBadge,
} from '@/lib/formSubmissionsUi';
import FormAnswerDetailSheet from '@/components/modules/dashboard/FormAnswerDetailSheet.vue';
import BulkFormAnswerReviewDialog from '@/components/modules/dashboard/BulkFormAnswerReviewDialog.vue';
import FormAnswersViewToggle from '@/components/modules/dashboard/FormAnswersViewToggle.vue';
import FormAnswerMobileCard from '@/components/modules/dashboard/FormAnswerMobileCard.vue';
import FormAnswerMobileList from '@/components/modules/dashboard/FormAnswerMobileList.vue';
import { useFormAnswerMobileView } from '@/hooks/useFormAnswerMobileView';
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
import { FORM_ANSWER_BULK_REVIEW_MAX_SELECTION } from '@/constants/formAnswerReview';

defineOptions({ layout: DashboardLayout });

type TShowTab = 'editor' | 'jawaban' | 'settings';
const props = defineProps<{
    event: { id: string; title: string };
    form: IForm;
    formShare: IFormSharePayload;
    fields: IBackendField[];
    siblingForms?: IFormSiblingOption[];
    saveFieldsUrl: string;
    updateFormUrl: string;
    submissions?: IFormSubmission[];
    submissionsCount?: number;
}>();

const page = usePage();
const { handleInertiaFormErrors, notifyAutosaveFailure, showErrorToast, showSuccessToast } = useToastNotifications();

/** Baca tab dari query string agar deep-link ?tab=jawaban bekerja saat refresh/back. */
function tabFromQuery(): TShowTab {
    const raw = new URLSearchParams(page.url.split('?')[1] ?? '').get('tab');
    if (raw === 'jawaban') return 'jawaban';
    return raw === 'settings' ? 'settings' : 'editor';
}
const activeTab = ref<TShowTab>(tabFromQuery());
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
                    showSuccessToast(humanizeErrorMessage('Form and fields saved successfully.'));
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
                    showSuccessToast(
                        nextStatus === 'published' ? 'Form berhasil dipublish.' : 'Form dikembalikan menjadi draft.'
                    );
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

/**
 * Kolom jawaban untuk tabel: answerKeys tanpa field berkas/foto.
 * Berkas/foto hanya tampil di drawer detail. Key legacy tanpa definisi field tetap tampil.
 */
const tableAnswerKeys = computed(() => answerKeys.value.filter((key) => !fileFieldNames.value.has(key)));

const submissionRows = computed(() => props.submissions ?? []);
const {
    selectedSubmissionIds,
    selectedCount,
    visibleSelectionState,
    ineligibleSelectedCount,
    isSubmitting: isBulkReviewSubmitting,
    actionStatus: bulkReviewActionStatus,
    actionError: bulkReviewActionError,
    isConfirmOpen: isBulkReviewConfirmOpen,
    pendingDecision: bulkReviewPendingDecision,
    isSelected: isSubmissionSelected,
    setVisibleSelection,
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
    reloadOnly: ['submissions'],
});
const mobileAnswerView = useFormAnswerMobileView();
const mobileAnswerKeys = computed(() => answerKeys.value);
const mobileFileFieldNames = computed(() => [...fileFieldNames.value]);
const submissionLabelMap = computed(() => {
    const map: Record<string, string> = {};
    props.fields.forEach((f) => {
        map[f.name] = f.label;
    });
    return map;
});
const humanizeKey = (key: string): string => humanizeSubmissionKey(submissionLabelMap.value, key);
const submissionFileUrlOf = (value: unknown): string | null => submissionFileUrl(value);
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
                    <FormAnswersViewToggle
                        v-if="activeTab === 'jawaban' && submissionRows.length > 0"
                        :view="mobileAnswerView.view.value"
                        @change="mobileAnswerView.setView"
                    />
                    <AutosaveStatus :status="showSaveState" variant="inline" @retry="showAutosave.retry" />
                </div>

                <div class="flex shrink-0 items-center gap-2">
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
                    v-model:success-content="successContent"
                    v-model:closed-at="closedAt"
                    v-model:visible-for="visibleFor"
                    v-model:banner="bannerState"
                    v-model:form-fields="formFields"
                    v-model:form-metadata="formMetadata"
                    :image-upload-url="builderImageUploadUrl"
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
                        id-prefix="tab"
                        :field-errors="fieldErrors"
                        :visibility-options="FORM_VISIBILITY_OPTIONS"
                        :sibling-forms="siblingForms ?? []"
                        @toggle-visibility="onToggleVisibility"
                    />
                </div>
            </TabsContent>

            <TabsContent value="jawaban" class="mt-0 min-h-0 flex-1 overflow-y-auto">
                <div
                    v-if="submissionRows.length === 0"
                    class="border-border/70 bg-muted/10 flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed px-6 py-20 text-center"
                >
                    <div class="border-border bg-card grid size-14 place-items-center rounded-full border shadow-xs">
                        <Inbox class="text-muted-foreground size-6" aria-hidden="true" />
                    </div>
                    <h2 class="font-display text-foreground mt-2 text-lg font-bold tracking-[-0.02em]">
                        Belum ada jawaban
                    </h2>
                    <p class="text-muted-foreground max-w-md text-sm leading-relaxed">
                        Saat ada yang mengisi dan mengirim formulir ini, daftar jawaban akan muncul di sini beserta
                        status review-nya.
                    </p>
                </div>

                <div v-else class="app-surface overflow-hidden rounded-2xl p-0">
                    <div
                        class="border-border/60 flex flex-col gap-3 border-b px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-5"
                    >
                        <div class="flex min-w-0 items-center gap-2.5">
                            <div
                                class="bg-primary/10 text-primary grid size-9 shrink-0 place-items-center rounded-full"
                            >
                                <Inbox class="size-4" aria-hidden="true" />
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-foreground text-sm font-semibold">Daftar jawaban</h2>
                                <p class="text-muted-foreground text-xs">
                                    Total {{ submissionsCount ?? submissionRows.length }} jawaban masuk.
                                </p>
                            </div>
                        </div>
                        <div v-if="selectedCount > 0" class="flex min-w-0 flex-wrap items-center gap-2">
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
                        </div>
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

                    <div v-if="mobileAnswerView.isDesktop.value" class="overflow-x-auto md:block">
                        <Table>
                            <TableHeader>
                                <TableRow class="hover:bg-transparent">
                                    <TableHead class="bg-muted/40 h-11 w-12 px-4">
                                        <Checkbox
                                            :model-value="visibleSelectionState"
                                            aria-label="Pilih semua jawaban di halaman ini"
                                            @update:model-value="setVisibleSelection"
                                        />
                                    </TableHead>
                                    <TableHead
                                        class="bg-muted/40 text-muted-foreground h-11 w-14 px-3 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        No.
                                    </TableHead>
                                    <TableHead
                                        class="bg-muted/40 text-muted-foreground h-11 px-5 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Pengirim
                                    </TableHead>
                                    <TableHead
                                        class="bg-muted/30 text-muted-foreground h-11 px-5 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Status review
                                    </TableHead>
                                    <TableHead
                                        v-for="key in tableAnswerKeys"
                                        :key="key"
                                        class="bg-muted/30 text-muted-foreground h-11 min-w-[160px] px-5 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        {{ humanizeKey(key) }}
                                    </TableHead>
                                    <TableHead
                                        class="bg-muted/30 text-muted-foreground h-11 px-5 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Dikirim
                                    </TableHead>
                                    <TableHead
                                        class="bg-muted/30 text-muted-foreground h-11 px-5 text-right text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="(submission, index) in submissionRows"
                                    :key="submission.id"
                                    :tabindex="0"
                                    :aria-label="`Lihat detail jawaban dari ${submission.user?.name ?? 'pengirim'}`"
                                    class="border-border/60 hover:bg-muted/30 focus-visible:ring-ring/70 cursor-pointer border-b transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                    @click="onSubmissionRowClick($event, submission)"
                                    @keydown="onSubmissionRowKeydown($event, submission)"
                                >
                                    <TableCell class="border-border/60 bg-card border-r px-4 py-3.5">
                                        <Checkbox
                                            :model-value="isSubmissionSelected(submission.id)"
                                            :aria-label="`Pilih jawaban nomor ${index + 1} dari ${submission.user?.name ?? 'pengirim'}`"
                                            @update:model-value="setSubmissionSelection(submission.id, $event)"
                                        />
                                    </TableCell>
                                    <TableCell class="text-muted-foreground px-3 py-3.5 text-xs tabular-nums">
                                        {{ index + 1 }}
                                    </TableCell>
                                    <TableCell class="border-border/60 bg-card border-r px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <UserAvatarFallback
                                                :src="submission.user?.avatar ?? null"
                                                :seed="userAvatarSeed(submission.user)"
                                                avatar-class="size-8 rounded-lg border border-border"
                                                fallback-round-class="rounded-lg"
                                            />
                                            <div class="min-w-0">
                                                <p
                                                    class="text-foreground truncate text-sm font-semibold tracking-[-0.005em]"
                                                >
                                                    {{ submission.user?.name ?? 'Tanpa nama' }}
                                                </p>
                                                <p class="text-muted-foreground truncate text-[10px]">
                                                    {{ submission.user?.email ?? '—' }}
                                                </p>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="px-5 py-3.5 whitespace-nowrap">
                                        <Badge
                                            variant="outline"
                                            :class="[
                                                'font-medium',
                                                submissionReviewBadge(submission.review_status).class,
                                            ]"
                                        >
                                            {{ submissionReviewBadge(submission.review_status).label }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell
                                        v-for="key in tableAnswerKeys"
                                        :key="key"
                                        class="text-muted-foreground max-w-[220px] px-5 py-3.5 text-xs leading-relaxed"
                                    >
                                        <span
                                            v-if="submissionFileUrlOf(submission.answers?.[key])"
                                            class="flex items-center gap-1.5"
                                            :title="fileNameOf(submission.answers?.[key])"
                                        >
                                            <FileText
                                                class="text-muted-foreground size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            <span class="text-foreground/85 line-clamp-2 font-normal break-all">
                                                {{ fileNameOf(submission.answers?.[key]) }}
                                            </span>
                                        </span>
                                        <span v-else class="text-foreground/85 line-clamp-2">
                                            {{ answerPreviewOf(submission.answers?.[key]) }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground px-5 py-3.5 text-[11px] whitespace-nowrap">
                                        {{
                                            formatDateTime({
                                                value: submission.submitted_at,
                                                variant: 'date-time-medium',
                                            })
                                        }}
                                    </TableCell>
                                    <TableCell class="px-5 py-3.5 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1">
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
                            </TableBody>
                        </Table>
                    </div>

                    <div v-else class="space-y-4 p-3 md:hidden">
                        <div class="border-border/60 bg-muted/20 flex items-center gap-2 rounded-lg border px-3 py-2">
                            <Checkbox
                                :model-value="visibleSelectionState"
                                aria-label="Pilih semua jawaban di halaman ini"
                                @update:model-value="setVisibleSelection"
                            />
                            <span class="text-muted-foreground text-xs">Pilih semua jawaban di halaman ini</span>
                        </div>
                        <div v-if="mobileAnswerView.view.value === 'card'" class="space-y-3">
                            <FormAnswerMobileCard
                                v-for="(submission, index) in submissionRows"
                                :key="submission.id"
                                :row-number="index + 1"
                                :submission="submission"
                                :answer-keys="mobileAnswerKeys"
                                :file-field-names="mobileFileFieldNames"
                                :humanize-key="humanizeKey"
                                :file-name-of="fileNameOf"
                                :reviewing="isSubmissionReviewing(submission.id)"
                                :selected="isSubmissionSelected(submission.id)"
                                @detail="openSubmissionDetail"
                                @review="onDetailReview"
                                @select="setSubmissionSelection(submission.id, $event)"
                            />
                        </div>
                        <FormAnswerMobileList
                            v-else
                            :submissions="submissionRows"
                            :selected-submission-ids="selectedSubmissionIds"
                            @detail="openSubmissionDetail"
                            @select="setSubmissionSelection"
                        />
                    </div>
                </div>

                <FormAnswerDetailSheet
                    v-model:open="isDetailOpen"
                    :submission="selectedSubmission"
                    :answer-keys="answerKeys"
                    :fields="detailFields"
                    :humanize-key="humanizeKey"
                    :is-submission-reviewing="isSubmissionReviewing"
                    @review="onDetailReview"
                />
            </TabsContent>
        </Tabs>
        <FormShareDialog v-model:open="isShareDialogOpen" :form-title="formTitle" :actions="formShareActions" />
    </div>
</template>
