<script setup lang="ts">
import { ref, watch, computed, onUnmounted } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { useAutosaveSync } from '@/utils/composables/useAutosaveSync';
import { getFieldError, handleInertiaFormErrors, humanizeErrorMessage } from '@/lib/error-message';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import FormBuilderWorkspace from '@/components/modules/builder/FormBuilderWorkspace.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { AutosaveStatus } from '@/components/ui/autosave-status';
import { Check, Eye, FileText, Inbox, PenLine, X } from 'lucide-vue-next';
import {
    fromBackendField,
    toBackendFields,
    type BackendField,
    type BuilderField,
} from '@/components/modules/builder/fieldMapping';
import {
    defaultFormBannerState,
    prependFormBannerToBackendPayload,
    extractFormBannerFromBuilderFields,
} from '@/components/modules/builder/formBanner';
import { emptyFormRegistrationMetadata, parseFormRegistrationMetadata, toFormMetadataPayload } from '@/types/form';
import type { FormSiblingOption } from '@/types/form';
import {
    answerPreview,
    formatSubmissionDate,
    formSubmissionReviewIsPending,
    humanizeSubmissionKey,
    submissionFileUrl,
    submissionReviewBadge,
} from '@/lib/formSubmissionsUi';
import {
    parseApiErrorMessage,
    showErrorToast,
    showHttpErrorToast,
} from '@/lib/error-message';
import FormAnswerReviewController from '@/actions/App/Http/Controllers/Dashboard/Events/Forms/FormAnswerReviewController';
import FormAnswerDetailSheet from '@/components/modules/dashboard/FormAnswerDetailSheet.vue';
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue';
import { userAvatarSeed } from '@/lib/userAvatarFallback';

defineOptions({ layout: DashboardLayout });

type ShowTab = 'editor' | 'jawaban';
const props = defineProps<{
    event: { id: string; title: string };
    form: IForm;
    fields: BackendField[];
    siblingForms?: FormSiblingOption[];
    saveFieldsUrl: string;
    updateFormUrl: string;
    submissions?: IFormSubmission[];
    submissionsCount?: number;
}>();

const page = usePage();

/** Baca tab dari query string agar deep-link ?tab=jawaban bekerja saat refresh/back. */
function tabFromQuery(): ShowTab {
    const raw = new URLSearchParams(page.url.split('?')[1] ?? '').get('tab');
    return raw === 'jawaban' ? 'jawaban' : 'editor';
}
const activeTab = ref<ShowTab>(tabFromQuery());

/** Sinkronkan tab ke query string (?tab=jawaban) agar deep-link tetap bertahan saat refresh. */
watch(activeTab, (tab) => {
    const url = new URL(page.url, window.location.origin);
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
const formFields = ref<BuilderField[]>([]);
const formMetadata = ref(emptyFormRegistrationMetadata());

// ── Autosave global: semua mutasi builder (fields + header/settings) ──
function buildShowSnapshot(): string {
    return JSON.stringify({
        fields: formFields.value,
        title: settingsForm.title,
        description: settingsForm.description,
        bannerUrl: bannerState.value.bannerUrl,
        bannerCaption: bannerState.value.caption,
        success: settingsForm.success_content ?? '',
        closedAt: settingsForm.closed_at ?? '',
        visibleFor: settingsForm.visible_for,
        metadata: formMetadata.value,
    });
}

async function saveShowSnapshot(): Promise<void> {
    const merged = prependFormBannerToBackendPayload(formFields.value, bannerState.value);
    const backendFields = toBackendFields(merged);
    await axios.post(
        props.saveFieldsUrl,
        { fields: backendFields },
        { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
    );
    await axios.put(
        props.updateFormUrl,
        {
            title: settingsForm.title,
            description: settingsForm.description,
            success_content: settingsForm.success_content ?? '',
            closed_at: settingsForm.closed_at ?? '',
            visible_for: settingsForm.visible_for,
            banner_url: bannerState.value.bannerUrl || null,
            banner_caption: bannerState.value.caption || null,
            fields: backendFields,
            metadata: toFormMetadataPayload(formMetadata.value),
        },
        { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
    );
}

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

const fieldErrors = computed(() => ({
    title: getFieldError(settingsForm.errors, 'title'),
    description: getFieldError(settingsForm.errors, 'description'),
    closed_at: getFieldError(settingsForm.errors, 'closed_at'),
    visible_for: getFieldError(settingsForm.errors, 'visible_for'),
}));

function syncFieldsFromProps(): void {
    const raw: BackendField[] = JSON.parse(JSON.stringify(props.fields || []));
    raw.sort((a, b) => a.order - b.order);
    const mapped = raw.map((f) => fromBackendField(f));
    const { banner: syntheticBanner, canvasFields } = extractFormBannerFromBuilderFields(mapped);

    bannerState.value.id = syntheticBanner.id;
    bannerState.value.bannerUrl = props.form.banner_url ?? syntheticBanner.bannerUrl;
    bannerState.value.caption = props.form.banner_caption ?? syntheticBanner.caption;
    bannerState.value.bannerFileName = syntheticBanner.bannerFileName;

    formFields.value = canvasFields;
}

watch(
    () => props.fields,
    () => syncFieldsFromProps(),
    { immediate: true, deep: true }
);
watch(
    () => props.form,
    (f) => {
        if (!f) return;
        settingsForm.title = f.title;
        settingsForm.description = f.description;
        settingsForm.success_content = f.success_content ?? '';
        settingsForm.closed_at = f.closed_at ?? '';
        settingsForm.visible_for = [...f.visible_for];
        settingsForm.banner_url = f.banner_url ?? '';
        settingsForm.banner_caption = f.banner_caption ?? '';
        formMetadata.value = parseFormRegistrationMetadata(f.metadata);
    },
    { deep: true, immediate: true }
);

// ── Autosave global: di-instantiasi SETELAH watcher hydrate di atas, agar snapshot
// awal menangkap state yang sudah terisi → tidak ada job save-redundan saat mount. ──
const showAutosave = useAutosaveSync(buildShowSnapshot, saveShowSnapshot, {
    debounceMs: 800,
    onError: () => toast.error('Gagal menyimpan otomatis. Perubahan tetap ada di kanvas.'),
});
const showSaveState = showAutosave.status;

onUnmounted(() => {
    void showAutosave.flush();
});

function onSave(): void {
    settingsForm.banner_url = bannerState.value.bannerUrl;
    settingsForm.banner_caption = bannerState.value.caption;

    const merged = prependFormBannerToBackendPayload(formFields.value, bannerState.value);
    const backendFields = toBackendFields(merged);

    settingsForm
        .transform((data) => ({
            ...data,
            fields: backendFields,
            metadata: toFormMetadataPayload(formMetadata.value),
        }))
        .put(props.updateFormUrl, {
            preserveScroll: true,
            onSuccess: () => toast.success(humanizeErrorMessage('Form and fields saved successfully.')),
            onError: (errors) => {
                handleInertiaFormErrors(errors, { title: 'Gagal menyimpan form' });
            },
        });
}

/** Jawaban terurut mengikuti urutan field di builder; sisa key (legacy) di akhir. */
const answerKeys = computed(() => {
    const orderMap = new Map<string, number>();
    props.fields.forEach((f) => orderMap.set(f.name, f.order));

    const keys = new Set<string>();
    props.fields.forEach((f) => keys.add(f.name));
    for (const submission of props.submissions ?? []) {
        Object.keys(submission.answers ?? {}).forEach((key) => keys.add(key));
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

function backendFieldBuilderType(field: BackendField): string {
    const metadata = (field.metadata ?? {}) as Record<string, unknown>;
    const builderType = metadata.builderType;
    return typeof builderType === 'string' ? builderType : '';
}

/** True bila field adalah unggahan berkas/foto (banner dikecualikan — bukan jawaban). */
function isFileBackendField(field: BackendField): boolean {
    if (field.name === 'form_banner' || backendFieldBuilderType(field) === 'banner') return false;
    if (FILE_FIELD_TYPE_NAMES.has(field.type)) return true;
    return FILE_FIELD_TYPE_NAMES.has(backendFieldBuilderType(field));
}

const fileFieldNames = computed(
    () => new Set((props.fields ?? []).filter(isFileBackendField).map((f) => f.name)),
);

/**
 * Kolom jawaban untuk tabel: answerKeys tanpa field berkas/foto.
 * Berkas/foto hanya tampil di drawer detail. Key legacy tanpa definisi field tetap tampil.
 */
const tableAnswerKeys = computed(() => answerKeys.value.filter((key) => !fileFieldNames.value.has(key)));

const submissionRows = computed(() => props.submissions ?? []);
const submissionLabelMap = computed(() => {
    const map: Record<string, string> = {};
    props.fields.forEach((f) => {
        map[f.name] = f.label;
    });
    return map;
});
const humanizeKey = (key: string): string => humanizeSubmissionKey(submissionLabelMap.value, key);
const formatDate = (value: string): string => formatSubmissionDate(value);
const submissionFileUrlOf = (value: unknown): string | null => submissionFileUrl(value);
const answerPreviewOf = (value: unknown): string => answerPreview(value);

/** Nama file polos untuk sel lampiran — tanpa link, tanpa membuka tab baru. */
function fileNameOf(value: unknown): string {
    const raw = typeof value === 'string' ? value.trim() : '';
    if (!raw) return 'Lampiran';
    try {
        const path = new URL(raw, window.location.origin).pathname;
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
    })),
);

// ── Detail drawer + review jawaban ──
const selectedSubmission = ref<IFormSubmission | null>(null);
const isDetailOpen = ref(false);
const reviewingIds = ref<Set<string>>(new Set());

function isSubmissionReviewing(submissionId: string): boolean {
    return reviewingIds.value.has(submissionId);
}

function openSubmissionDetail(submission: IFormSubmission): void {
    selectedSubmission.value = submission;
    isDetailOpen.value = true;
}

function readXsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match?.[1] ? decodeURIComponent(match[1]) : null;
}

function submitSubmissionReview(action: 'accept' | 'reject', submission: IFormSubmission): void {
    if (!formSubmissionReviewIsPending(submission) || isSubmissionReviewing(submission.id)) return;

    const review_status = action === 'accept' ? 'accepted' : 'rejected';
    const id = submission.id;
    reviewingIds.value = new Set(reviewingIds.value).add(id);

    const clearReviewing = (): void => {
        const next = new Set(reviewingIds.value);
        next.delete(id);
        reviewingIds.value = next;
    };

    const { url, method } = FormAnswerReviewController.patch({
        event: props.event.id,
        form: props.form.id,
        formAnswer: submission.id,
    });

    void (async () => {
        try {
            const token = readXsrfToken();
            const res = await fetch(url, {
                method: method.toUpperCase(),
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-XSRF-TOKEN': token } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ review_status }),
            });

            const body = (await res.json().catch(() => ({}))) as { message?: string };

            if (!res.ok) {
                showHttpErrorToast(res.status, body, {
                    409: parseApiErrorMessage(body, 'Jawaban ini sudah pernah direview.'),
                    422: parseApiErrorMessage(body, 'Status review tidak valid.'),
                    403: 'Anda tidak punya izin untuk mereview jawaban ini.',
                    404: 'Jawaban tidak ditemukan.',
                });
                if (res.status === 409 || res.status === 422) {
                    router.reload({ only: ['submissions'] });
                }
                return;
            }

            toast.success(action === 'accept' ? 'Jawaban diterima.' : 'Jawaban ditolak.');
            router.reload({
                only: ['submissions'],
                onSuccess: () => {
                    const next = (props.submissions ?? []).find((s) => s.id === id) ?? null;
                    if (next && selectedSubmission.value?.id === id) {
                        selectedSubmission.value = next;
                    }
                },
            });
        } catch {
            showErrorToast('Tidak dapat menghubungi server. Coba lagi.');
        } finally {
            clearReviewing();
        }
    })();
}

function onDetailReview(payload: { action: 'accept' | 'reject'; submission: IFormSubmission }): void {
    submitSubmissionReview(payload.action, payload.submission);
}

function acceptLabel(submission: IFormSubmission): string {
    if (isSubmissionReviewing(submission.id)) return 'Memproses...';
    if (submission.review_status === 'accepted') return 'Sudah diterima';
    return 'Terima';
}

function rejectLabel(submission: IFormSubmission): string {
    if (isSubmissionReviewing(submission.id)) return 'Memproses...';
    if (submission.review_status === 'rejected') return 'Sudah ditolak';
    return 'Tolak';
}
</script>

<template>
    <Head :title="`Edit: ${form.title}`" />

    <div class="flex min-w-0 flex-col gap-4">
        <Tabs v-model="activeTab" class="flex w-full flex-col gap-4" :unmount-on-hide="false" aria-label="Konten form">
            <div class="border-border/60 flex items-center justify-between gap-3 border-b pb-3">
                <div class="flex min-w-0 items-center gap-4">
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
                    </TabsList>
                    <AutosaveStatus :status="showSaveState" variant="inline" />
                </div>

                <div class="flex shrink-0 items-center gap-2">
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
                        :disabled="settingsForm.processing"
                        @click="requestSaveAll"
                    >
                        <span class="sm:hidden">Simpan</span>
                        <span class="hidden sm:inline">Save All</span>
                    </Button>
                </div>
            </div>

            <TabsContent value="editor" class="mt-0">
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
                    :event="event"
                    :sibling-forms="siblingForms ?? []"
                    :toolbar-subtitle="`Edit form · ${event.title}`"
                    save-label="Save All"
                    hide-toolbar
                    :processing="settingsForm.processing"
                    :field-errors="fieldErrors"
                    @save="onSave"
                />
            </TabsContent>

            <TabsContent value="jawaban" class="mt-0">
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
                    <div class="border-border/60 flex items-center gap-2.5 border-b px-5 py-4">
                        <div class="bg-primary/10 text-primary grid size-9 place-items-center rounded-full">
                            <Inbox class="size-4" aria-hidden="true" />
                        </div>
                        <div>
                            <h2 class="text-foreground text-sm font-semibold">Daftar jawaban</h2>
                            <p class="text-muted-foreground text-xs">
                                Total {{ submissionsCount ?? submissionRows.length }} jawaban masuk.
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <TooltipProvider>
                        <Table>
                            <TableHeader>
                                <TableRow class="hover:bg-transparent">
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
                                    v-for="submission in submissionRows"
                                    :key="submission.id"
                                    class="border-border/60 hover:bg-muted/30 border-b transition-colors"
                                >
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
                                        {{ formatDate(submission.submitted_at) }}
                                    </TableCell>
                                    <TableCell class="px-5 py-3.5 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <Tooltip v-if="formSubmissionReviewIsPending(submission)">
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        radius="icon"
                                                        size="icon-sm"
                                                        class="text-success hover:bg-success/10 hover:text-success"
                                                        :aria-label="`${acceptLabel(submission)} jawaban dari ${submission.user?.name ?? 'pengirim'}`"
                                                        :disabled="isSubmissionReviewing(submission.id)"
                                                        @click="submitSubmissionReview('accept', submission)"
                                                    >
                                                        <Check class="size-4" aria-hidden="true" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{{ acceptLabel(submission) }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                            <Tooltip v-if="formSubmissionReviewIsPending(submission)">
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        radius="icon"
                                                        size="icon-sm"
                                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                        :aria-label="`${rejectLabel(submission)} jawaban dari ${submission.user?.name ?? 'pengirim'}`"
                                                        :disabled="isSubmissionReviewing(submission.id)"
                                                        @click="submitSubmissionReview('reject', submission)"
                                                    >
                                                        <X class="size-4" aria-hidden="true" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{{ rejectLabel(submission) }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        radius="icon"
                                                        size="icon-sm"
                                                        class="hover:bg-primary/10 hover:text-primary"
                                                        :aria-label="`Lihat detail jawaban dari ${submission.user?.name ?? 'pengirim'}`"
                                                        @click="openSubmissionDetail(submission)"
                                                    >
                                                        <Eye class="size-4" aria-hidden="true" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Lihat detail</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        </TooltipProvider>
                    </div>
                </div>

                <FormAnswerDetailSheet
                    v-model:open="isDetailOpen"
                    :submission="selectedSubmission"
                    :answer-keys="answerKeys"
                    :fields="detailFields"
                    :format-date="formatDate"
                    :humanize-key="humanizeKey"
                    :is-submission-reviewing="isSubmissionReviewing"
                    @review="onDetailReview"
                />
            </TabsContent>
        </Tabs>
    </div>
</template>
