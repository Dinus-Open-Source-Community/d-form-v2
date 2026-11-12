<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';
import { requestFormDraft, resolveBuilderImageUploadUrl, updateFormSettings } from '@/adapters/http/forms';
import { isHttpRequestError } from '@/adapters/http/httpError';
import { getFieldError, humanizeErrorMessage, type TValidationErrors } from '@/lib/error-message';
import { resolveDraftAccessDecision, type TDraftAccess } from '@/lib/formDraftAccess';
import { cleanStoredImageUrl } from '@/lib/builderImageSanitizer';
import { toFormMetadataPayload } from '@/lib/formRegistrationMetadata';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Button } from '@/components/ui/button';
import { AutosaveStatus } from '@/components/ui/autosave-status';
import FormBuilderEditorTabs from '@/components/modules/builder/FormBuilderEditorTabs.vue';
import FormBuilderWorkspace from '@/components/modules/builder/FormBuilderWorkspace.vue';
import { defaultFormBannerState } from '@/components/modules/builder/formBanner';
import { hydrateFormBuilderState } from '@/components/modules/builder/formBuilderHydration';
import { builderPendingUploads } from '@/hooks/useBuilderImageUpload';
import { useFormBuilderAutosave } from '@/hooks/useFormBuilderAutosave';
import type { IFormBuilderAutosaveEndpoints } from '@/hooks/useFormBuilderAutosave';
import type { TAutosaveStatus } from '@/hooks/useAutosaveSync';
import type { IBackendField, IBuilderField } from '@/types/form-builder';
import type { ICreateDashboardFormPayload, IFormSiblingOption } from '@/types/form';
import { emptyFormRegistrationMetadata } from '@/lib/formRegistrationMetadata';
import { routes } from '@/lib/routes';
import { setTopbar } from '@/hooks/useDashboardTopbar';
import { update as updateForm } from '@/actions/App/Http/Controllers/Dashboard/Events/Forms/FormController';
import { __invoke as postFields } from '@/actions/App/Http/Controllers/Dashboard/Events/Forms/FieldOperationController';

/** Tab halaman create: kanvas editor atau pengaturan form (tidak ada tab jawaban). */
type TCreateFormTab = 'editor' | 'settings';

/** Error validasi header/settings dari publish (422), dipetakan ke tab pemiliknya. */
type TCreateFormFieldErrors = Partial<Record<'title' | 'description' | 'closed_at' | 'visible_for', string>>;

/** Field yang tampil di tab Editor (judul & deskripsi kanvas). */
const EDITOR_ERROR_KEYS: ReadonlySet<string> = new Set(['title', 'description']);

/**
 * Payload simpan lama (`POST /forms`) saat draf tidak tersedia untuk user ini.
 * `fields` sengaja `object[]`: Inertia tidak bisa menurunkan `metadata` builder.
 */
type TStoreFormPayload = Omit<ICreateDashboardFormPayload, 'fields'> & { fields: object[] };

defineOptions({ layout: DashboardLayout });

const props = defineProps<{
    event: { id: string; title: string };
    siblingForms?: IFormSiblingOption[];
}>();

const { handleInertiaFormErrors, notifyAutosaveFailure, showErrorToast, showSuccessToast, showValidationErrorToast } =
    useToastNotifications();

const activeTab = ref<TCreateFormTab>('editor');

/** Ref workspace: aksi Pratinjau/Simpan hidup di header tab, bukan di toolbar builder. */
const workspaceRef = ref<InstanceType<typeof FormBuilderWorkspace> | null>(null);

const formTitle = ref<string>('');
const formDescription = ref<string>('');
const successContent = ref<string>('');
const closedAt = ref<string>('');
const visibleFor = ref<string[]>([]);
const bannerState = ref(defaultFormBannerState());
const formFields = ref<IBuilderField[]>([]);
const formMetadata = ref(emptyFormRegistrationMetadata());

/** Sama dengan `wb.isEmpty` di workspace: kanvas belum punya field. */
const builderEmpty = computed<boolean>(() => formFields.value.length === 0);

// ── Draf server-side: satu-satunya form id untuk autosave & publish ──
const draftFormId = ref<string | null>(null);
const draftEditUrl = ref<string | null>(null);
/**
 * Hasil endpoint draf: `available` (autosave + publish), `forbidden` (draf milik
 * pembuat acara → jalur simpan lama), `retryable-error` (indikator error + retry).
 */
const draftAccess = ref<TDraftAccess>('available');
/** Autosave baru diaktifkan setelah snapshot awal terpasang (tanpa save tambahan). */
const autosaveReady = ref<boolean>(false);
const isPublishing = ref<boolean>(false);
let draftRequest: Promise<string | null> | null = null;

/**
 * True selama builder belum disentuh user. Hidrasi hanya mengisi builder kosong:
 * ketikan yang terjadi sebelum respons draf tiba tidak boleh tertimpa.
 */
function isBuilderPristine(): boolean {
    return (
        formTitle.value.trim() === '' &&
        formDescription.value.trim() === '' &&
        successContent.value.trim() === '' &&
        closedAt.value === '' &&
        visibleFor.value.length === 0 &&
        formFields.value.length === 0
    );
}

/** Petakan snapshot server (header + fields) ke model builder; satu fungsi bersama halaman edit. */
function applyDraftSnapshot(snapshot: { form: IForm; fields: IBackendField[] }): void {
    const hydrated = hydrateFormBuilderState({ form: snapshot.form, fields: snapshot.fields });

    formTitle.value = hydrated.title;
    formDescription.value = hydrated.description;
    successContent.value = hydrated.successContent;
    closedAt.value = hydrated.closedAt;
    visibleFor.value = hydrated.visibleFor;
    bannerState.value = hydrated.banner;
    formMetadata.value = hydrated.metadata;
    formFields.value = hydrated.fields;
}

/**
 * Buat atau ambil draf milik user untuk event ini. Endpoint idempoten dan mengembalikan
 * snapshot lengkap, jadi reload melanjutkan isi draf; pemanggilan bersamaan memakai
 * satu request. `null` berarti draf belum ada (autosave menunggu).
 */
async function ensureDraftForm(): Promise<string | null> {
    if (draftFormId.value !== null) return draftFormId.value;
    if (draftAccess.value === 'forbidden') return null;
    if (draftRequest !== null) return draftRequest;

    draftRequest = (async (): Promise<string | null> => {
        try {
            const draft = await requestFormDraft(routes.admin.events.forms.draft(props.event.id));

            if (isBuilderPristine()) applyDraftSnapshot(draft);
            draftEditUrl.value = draft.editUrl;
            draftAccess.value = 'available';
            draftFormId.value = draft.formId;

            // Tunggu watcher snapshot melihat state hasil hidrasi sebelum autosave aktif,
            // sehingga hidrasi tidak memicu save tambahan.
            await nextTick();
            autosaveReady.value = true;

            return draft.formId;
        } catch (error: unknown) {
            const forbidden = isHttpRequestError(error) && error.status === 403;

            if (forbidden) {
                // Draf milik pembuat acara: bukan error yang bisa di-retry. Halaman tetap
                // bisa dipakai lewat jalur simpan manual (POST form baru).
                draftAccess.value = 'forbidden';
                return null;
            }

            draftAccess.value = 'retryable-error';
            showErrorToast('Autosave belum aktif karena draf form gagal disiapkan. Simpan manual tetap bisa dipakai.', {
                title: 'Autosave tidak aktif',
            });
            return null;
        } finally {
            draftRequest = null;
        }
    })();

    return draftRequest;
}

/** Endpoint upload gambar builder `POST {form}/images`; kosong sampai draf siap. */
const builderImageUploadUrl = computed<string>(() => {
    const formId = draftFormId.value;

    return formId === null ? '' : resolveBuilderImageUploadUrl(props.event.id, formId);
});

const autosaveEndpoints = computed<IFormBuilderAutosaveEndpoints | null>(() => {
    const formId = draftFormId.value;
    if (formId === null) return null;

    return {
        fieldsUrl: postFields({ event: props.event.id, form: formId }).url,
        settingsUrl: updateForm({ event: props.event.id, form: formId }).url,
    };
});

const autosave = useFormBuilderAutosave({
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
    // Halaman create selalu mengisi draf.
    isDraft: true,
    enabled: computed<boolean>(() => autosaveReady.value),
    onError: (message) => notifyAutosaveFailure(message, () => retryAutosave()),
});

/** Keputusan UI (autosave aktif? jalur simpan mana?) dari hasil endpoint draf. */
const draftDecision = computed(() => resolveDraftAccessDecision(draftAccess.value));

/** Status indikator: error hanya untuk kegagalan yang bisa dicoba lagi. */
const autosaveStatus = computed<TAutosaveStatus>(() =>
    draftDecision.value.status === 'error' ? 'error' : autosave.status.value
);

function retryAutosave(): void {
    if (draftAccess.value !== 'available') {
        void ensureDraftForm();
        return;
    }
    void autosave.retry();
}

const fieldErrors = ref<TCreateFormFieldErrors>({});

/** Ambil seluruh error header dari bag validasi; satu bentuk untuk kedua jalur simpan. */
function readFormFieldErrors(bag: TValidationErrors): TCreateFormFieldErrors {
    return {
        title: getFieldError(bag, 'title'),
        description: getFieldError(bag, 'description'),
        closed_at: getFieldError(bag, 'closed_at'),
        visible_for: getFieldError(bag, 'visible_for'),
    };
}

/** Tab yang memuat error pertama (urutan key bag = urutan field backend). */
function tabOfFirstError(errors: TCreateFormFieldErrors): TCreateFormTab {
    const firstKey = Object.keys(errors).find((key) => errors[key as keyof TCreateFormFieldErrors] !== undefined);

    return firstKey !== undefined && EDITOR_ERROR_KEYS.has(firstKey) ? 'editor' : 'settings';
}

/** Terapkan error field + pindah ke tab yang memuat error pertama. */
function applyFieldErrors(errors: TCreateFormFieldErrors): void {
    fieldErrors.value = errors;

    if (Object.values(errors).some((message) => message !== undefined)) {
        activeTab.value = tabOfFirstError(errors);
    }
}

function requestPreview(): void {
    workspaceRef.value?.showPreview();
}

/** Jalur simpan lama untuk user tanpa akses draf: POST form baru (status default backend). */
const storeForm = useForm<TStoreFormPayload>({
    title: '',
    description: '',
    success_content: '',
    closed_at: '',
    visible_for: [],
    banner_url: '',
    banner_caption: '',
    metadata: null,
    fields: [],
});

function submitStoreForm(): void {
    if (isPublishing.value) return;

    isPublishing.value = true;
    storeForm
        .transform((): TStoreFormPayload => ({
            title: formTitle.value,
            description: formDescription.value,
            success_content: successContent.value,
            closed_at: closedAt.value,
            visible_for: visibleFor.value,
            banner_url: cleanStoredImageUrl(bannerState.value.bannerUrl),
            banner_caption: bannerState.value.caption,
            metadata: toFormMetadataPayload(formMetadata.value),
            fields: autosave.buildFieldsPayload(),
        }))
        .post(routes.admin.events.forms.store(props.event.id), {
            onSuccess: () => showSuccessToast(humanizeErrorMessage('Form created successfully!')),
            onError: (errors) => {
                applyFieldErrors(readFormFieldErrors(errors));
                handleInertiaFormErrors(errors, { title: 'Gagal menyimpan form' });
            },
            onFinish: () => {
                isPublishing.value = false;
            },
        });
}

/** Publish draf: satu PUT atomik (settings + fields + `publish`), lalu pindah ke halaman edit. */
async function publishDraft(): Promise<void> {
    await autosave.waitForIdle();

    const endpoints = autosaveEndpoints.value;
    if (endpoints === null) return;

    await updateFormSettings(endpoints.settingsUrl, {
        ...autosave.buildSettingsPayload({ publish: true }),
        fields: autosave.buildFieldsPayload(),
    });

    fieldErrors.value = {};
    autosave.markSaved();
    showSuccessToast(humanizeErrorMessage('Form created successfully!'));

    const editUrl = draftEditUrl.value;
    if (editUrl !== null) router.visit(editUrl);
    else router.visit(routes.admin.events.show(props.event.id));
}

/**
 * Tampilkan kegagalan publish tanpa meninggalkan halaman.
 * Bag validasi diprioritaskan (satu pesan per field, sudah diterjemahkan); ringkasan
 * `Please enter … (and N more errors)` dari Laravel tidak pernah dipakai saat bag ada.
 */
function applyPublishError(error: unknown): void {
    if (isHttpRequestError(error) && error.validationErrors !== null) {
        applyFieldErrors(readFormFieldErrors(error.validationErrors));
        showValidationErrorToast(error.validationErrors, { title: 'Gagal menyimpan form' });
        return;
    }

    showErrorToast(humanizeErrorMessage(error instanceof Error ? error.message : 'Gagal menyimpan form.'), {
        title: 'Gagal menyimpan form',
    });
}

/**
 * Jalur simpan utama.
 * - Draf tersedia → publish draf (single PUT, autosave dibekukan selama proses).
 * - Draf tidak tersedia untuk user ini (403) → jalur simpan lama `POST /forms`.
 */
async function publishForm(): Promise<void> {
    if (isPublishing.value) return;
    if (builderPendingUploads.value > 0) {
        showErrorToast('Masih ada gambar yang diunggah. Tunggu sebentar lalu simpan lagi.');
        return;
    }

    if (draftDecision.value.saveMode === 'store-form') {
        submitStoreForm();
        return;
    }

    isPublishing.value = true;
    autosave.suspend();

    try {
        const formId = await ensureDraftForm();

        if (formId === null) {
            // 403 bisa baru terungkap saat ensureDraftForm() dipanggil dari tombol simpan.
            const forbidden = draftAccess.value === 'forbidden';

            autosave.resume();
            isPublishing.value = false;

            if (forbidden) {
                submitStoreForm();
                return;
            }

            showErrorToast('Draf form belum bisa disiapkan. Periksa koneksi lalu coba lagi.', {
                title: 'Gagal menyimpan form',
            });
            return;
        }

        await publishDraft();
    } catch (error) {
        applyPublishError(error);
    } finally {
        autosave.resume();
        isPublishing.value = false;
    }
}

onMounted(() => {
    // Topbar builder: judul + konteks acara, tanpa strip breadcrumb (konsisten dengan tab).
    setTopbar({ title: 'Buat form', subtitle: props.event.title });
    // Siapkan draf sekali di awal supaya autosave punya form id sejak edit pertama.
    void ensureDraftForm();
});
</script>

<template>
    <Head title="Create Form" />

    <div class="flex h-full min-h-0 min-w-0 flex-col">
        <FormBuilderEditorTabs
            v-model:active-tab="activeTab"
            v-model:closed-at="closedAt"
            v-model:visible-for="visibleFor"
            v-model:form-metadata="formMetadata"
            id-prefix="create"
            :field-errors="fieldErrors"
            :sibling-forms="siblingForms ?? []"
        >
            <template #actions>
                <span
                    v-if="draftAccess === 'forbidden'"
                    class="text-muted-foreground hidden text-xs sm:inline"
                    data-testid="autosave-unavailable"
                >
                    Autosave tidak tersedia untuk acara ini (draf milik pembuat acara).
                </span>
                <AutosaveStatus
                    v-else-if="draftDecision.autosaveEnabled || draftAccess === 'retryable-error'"
                    :status="autosaveStatus"
                    variant="inline"
                    @retry="retryAutosave"
                />
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
                    :disabled="isPublishing"
                    @click="publishForm"
                >
                    <span class="sm:hidden">Simpan</span>
                    <span class="hidden sm:inline">Save Form</span>
                </Button>
            </template>

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
                :processing="isPublishing"
                :field-errors="fieldErrors"
                :image-upload-url="builderImageUploadUrl"
                @save="publishForm"
                @open-settings="activeTab = 'settings'"
            />
        </FormBuilderEditorTabs>
    </div>
</template>
