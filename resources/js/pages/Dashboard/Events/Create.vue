<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { resolveBuilderImageUploadUrl } from '@/adapters/http/forms';
import { removeStorage } from '@/adapters/browser/storage';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import EventDashboardForm from '@/components/modules/dashboard/events/EventDashboardForm.vue';
import EventWizardStepper from '@/components/modules/dashboard/events/EventWizardStepper.vue';
import FormBuilderEditorTabs from '@/components/modules/builder/FormBuilderEditorTabs.vue';
import FormBuilderWorkspace from '@/components/modules/builder/FormBuilderWorkspace.vue';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import { Button } from '@/components/ui/button';
import { AutosaveStatus } from '@/components/ui/autosave-status';
import { Loader2 } from 'lucide-vue-next';
import { setTopbar } from '@/hooks/useDashboardTopbar';
import { destroy as destroyEvent } from '@/actions/App/Http/Controllers/Dashboard/Events/EventController';
import { update as updateForm } from '@/actions/App/Http/Controllers/Dashboard/Events/Forms/FormController';
import { __invoke as postFields } from '@/actions/App/Http/Controllers/Dashboard/Events/Forms/FieldOperationController';
import { useFormBuilderAutosave } from '@/hooks/useFormBuilderAutosave';
import type { IFormBuilderAutosaveEndpoints } from '@/hooks/useFormBuilderAutosave';
import { fromBackendField, type IBackendField } from '@/components/modules/builder/fieldMapping';
import { defaultFormBannerState, extractFormBannerFromBuilderFields } from '@/components/modules/builder/formBanner';
import { emptyFormRegistrationMetadata, parseFormRegistrationMetadata } from '@/lib/formRegistrationMetadata';
import type { IBuilderField } from '@/types/form-builder';
import { routes } from '@/lib/routes';

defineOptions({ layout: DashboardFocusLayout });

const { handleInertiaFormErrors, notifyAutosaveFailure, showErrorToast } = useToastNotifications();

interface WizardDraftForm {
    id: string;
    title: string;
    description: string;
    success_content: string | null;
    closed_at: string | null;
    visible_for: string[];
    banner_url: string | null;
    banner_caption: string | null;
    metadata: unknown;
    fields: IBackendField[];
}

interface WizardDraftEvent extends IEvent {
    forms: WizardDraftForm[];
}

const props = defineProps<{
    options?: {
        categories: { value: string; label: string }[];
        sessions: { value: string; label: string }[];
    };
    draftEvent?: WizardDraftEvent;
}>();

const page = usePage();

const queryStep = computed(() => new URLSearchParams(page.url.split('?')[1] ?? '').get('step') ?? 'event');
const queryDraftId = computed(() => new URLSearchParams(page.url.split('?')[1] ?? '').get('draftId') ?? '');

const step = ref<'event' | 'forms'>(
    queryStep.value === 'forms' && queryDraftId.value && props.draftEvent ? 'forms' : 'event'
);

const draftEvent = computed<WizardDraftEvent | null>(() => props.draftEvent ?? null);
const draftForm = computed<WizardDraftForm | null>(() => draftEvent.value?.forms?.[0] ?? null);
const eventFormRef = ref<InstanceType<typeof EventDashboardForm> | null>(null);

onMounted(() => {
    setTopbar({ title: 'Buat acara', subtitle: 'Detail acara & formulir pendaftaran' });
});

// ── Step event → forms: setelah POST wizard, Inertia render ulang dgn draftEvent ──
// Guard: hanya auto-pindah saat draft baru dibuat (oldId falsy), bukan saat
// user sengaja kembali ke step event via goBackToEvent (?step=event&draftId=...).
watch(
    () => props.draftEvent?.id,
    (id, oldId) => {
        if (id && !oldId && step.value === 'event') {
            const params: Record<string, string> = { step: 'forms', draftId: String(id) };
            router.get(routes.admin.events.create, params, { preserveState: false });
        }
    }
);

// ── Step forms: builder state (hydrate dari draftForm) ──────────
/** Tab step formulir: kanvas editor atau pengaturan form (tanpa tab jawaban). */
type TWizardFormTab = 'editor' | 'settings';

const activeFormTab = ref<TWizardFormTab>('editor');
/** Ref workspace: Pratinjau dipicu dari bar aksi wizard, bukan dari toolbar builder. */
const workspaceRef = ref<InstanceType<typeof FormBuilderWorkspace> | null>(null);
const formTitle = ref<string>('');
const formDescription = ref<string>('');
const successContent = ref<string>('');
const closedAt = ref<string>('');
const visibleFor = ref<string[]>([]);
const bannerState = ref(defaultFormBannerState());
const formFields = ref<IBuilderField[]>([]);
const formMetadata = ref(emptyFormRegistrationMetadata());

function hydrateBuilder(): void {
    const f = draftForm.value;
    if (!f) return;
    formTitle.value = f.title;
    formDescription.value = f.description ?? '';
    successContent.value = f.success_content ?? '';
    closedAt.value = f.closed_at ?? '';
    visibleFor.value = [...(f.visible_for ?? [])];
    formMetadata.value = parseFormRegistrationMetadata(f.metadata);

    const raw: IBackendField[] = JSON.parse(JSON.stringify(f.fields ?? []));
    raw.sort((a, b) => a.order - b.order);
    const mapped = raw.map((bf) => fromBackendField(bf));
    const { banner: syntheticBanner, canvasFields } = extractFormBannerFromBuilderFields(mapped);
    bannerState.value.id = syntheticBanner.id;
    bannerState.value.bannerUrl = f.banner_url ?? syntheticBanner.bannerUrl;
    bannerState.value.caption = f.banner_caption ?? syntheticBanner.caption;
    bannerState.value.bannerFileName = syntheticBanner.bannerFileName;
    formFields.value = canvasFields;
}

watch(
    () => draftForm.value?.id,
    () => {
        if (step.value === 'forms') hydrateBuilder();
    },
    { immediate: true }
);

// ── Autosave global (optimistik + debounce 800ms): SEMUA mutasi builder ──
// Bentuk snapshot/payload dan seluruh glue simpan ada di `useFormBuilderAutosave`
// (satu implementasi untuk halaman create, edit, dan wizard ini).
const autosaveEndpoints = computed<IFormBuilderAutosaveEndpoints | null>(() => {
    const eventId = draftEvent.value?.id;
    const formId = draftForm.value?.id;
    if (!eventId || !formId) return null;
    return {
        fieldsUrl: postFields({ event: eventId, form: formId }).url,
        settingsUrl: updateForm({ event: eventId, form: formId }).url,
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
    onError: (message) => notifyAutosaveFailure(message, () => void autosave.retry()),
});
/** Endpoint upload gambar builder `POST {form}/images` (multipart field `image`). */
const builderImageUploadUrl = computed(() => {
    const eventId = draftEvent.value?.id;
    const formId = draftForm.value?.id;
    if (!eventId || !formId) return '';
    return resolveBuilderImageUploadUrl(eventId, formId);
});

const saveState = autosave.status;
const flushPending = (): Promise<void> => autosave.flush();

/**
 * Semua jalan keluar dari step form wajib menunggu `flush()` autosave (single-flight).
 * Perubahan yang masih di debounce 800 ms tidak boleh hilang karena `router.get`/`visit`;
 * bila save gagal, navigasi diblokir agar user sempat retry dari status autosave.
 */
type TLeaveStepAction = 'back' | 'next' | 'skip' | 'finish';

const leavingFormStep = ref(false);
const activeExitAction = ref<TLeaveStepAction | null>(null);

async function afterFinalFlush(action: TLeaveStepAction, navigate: () => void): Promise<boolean> {
    if (leavingFormStep.value) return false;

    leavingFormStep.value = true;
    activeExitAction.value = action;
    try {
        await flushPending();
        if (saveState.value === 'error') {
            showErrorToast('Perubahan terakhir belum tersimpan. Periksa koneksi lalu coba lagi.', {
                title: 'Gagal menyimpan',
            });
            return false;
        }
        navigate();
        return true;
    } finally {
        leavingFormStep.value = false;
        activeExitAction.value = null;
    }
}

onUnmounted(() => {
    // Jaring pengaman untuk keluar tanpa tombol eksplisit (mis. tombol back browser).
    // No-op saat tidak ada edit tertunda, sehingga tidak menggandakan save navigasi.
    void autosave.flush();
});

// ── Navigasi ────────────────────────────────────────────────────
function goBackToEvent(): void {
    void afterFinalFlush('back', () => {
        const params: Record<string, string> = { step: 'event' };
        if (draftEvent.value?.id) params.draftId = String(draftEvent.value.id);
        router.get(routes.admin.events.create, params, { preserveState: false });
    });
}

function goToForms(): void {
    const api = eventFormRef.value as unknown as {
        validateRequired?: () => boolean;
        submitForm?: (publish: boolean) => void;
    } | null;

    if (!draftEvent.value) {
        if (api?.validateRequired && !api.validateRequired()) return;
        api?.submitForm?.(false);
        return;
    }

    if (api?.validateRequired && !api.validateRequired()) return;

    const eventId = draftEvent.value?.id;
    if (!eventId) return;
    void afterFinalFlush('next', () => {
        router.get(routes.admin.events.create, { step: 'forms', draftId: String(eventId) }, { preserveState: false });
    });
}

function clearDraftCache(): void {
    const id = draftEvent.value?.id;
    if (!id) return;
    removeStorage({ area: 'local', key: `dform:draft:form:${id}` });
}

// ── Selesai ─────────────────────────────────────────────────────
const finishing = ref(false);

function finishWizard(): void {
    const eventId = draftEvent.value?.id;
    if (!eventId || finishing.value) return;
    finishing.value = true;
    void afterFinalFlush('finish', () => {
        clearDraftCache();
        router.visit(routes.admin.events.show(eventId));
    }).finally(() => {
        finishing.value = false;
    });
}

function skipWizard(): void {
    const eventId = draftEvent.value?.id;
    if (!eventId) return;
    void afterFinalFlush('skip', () => {
        clearDraftCache();
        router.visit(routes.admin.events.show(eventId));
    });
}

// ── Batalkan ────────────────────────────────────────────────────
const showCancelModal = ref(false);
const cancelBusy = ref(false);

function confirmCancel(): void {
    const eventId = draftEvent.value?.id;
    if (!eventId || cancelBusy.value) return;
    cancelBusy.value = true;
    clearDraftCache();
    router.delete(destroyEvent({ event: eventId }).url, {
        onSuccess: () => {
            // BE redirect ke index — Inertia mengikuti; fallback bila URL belum berubah.
            if (page.url !== routes.admin.events.index) {
                router.visit(routes.admin.events.index);
            }
        },
        onError: (errors) => {
            handleInertiaFormErrors(errors, { title: 'Gagal membatalkan' });
        },
        onFinish: () => {
            cancelBusy.value = false;
            showCancelModal.value = false;
        },
    });
}

// ── Stepper header ──────────────────────────────────────────────
const steps = [
    { key: 'event', label: 'Detail acara' },
    { key: 'forms', label: 'Formulir pendaftaran' },
];
const currentIndex = computed(() => (step.value === 'forms' ? 1 : 0));

/** Sama dengan `wb.isEmpty` di workspace: kanvas belum punya field. */
const builderEmpty = computed<boolean>(() => formFields.value.length === 0);

function requestPreview(): void {
    workspaceRef.value?.showPreview();
}
</script>

<template>
    <Head title="Buat acara" />

    <div class="flex flex-col gap-5">
        <EventDashboardForm
            v-if="step === 'event'"
            ref="eventFormRef"
            :variant="draftEvent ? 'edit' : 'create'"
            :event="draftEvent ?? undefined"
            :options="options"
            :wizard-mode="true"
        >
            <template #header-leading>
                <div class="flex w-full min-w-0 flex-wrap items-center justify-between gap-3">
                    <EventWizardStepper :steps="steps" :active-index="currentIndex" />
                    <div class="flex shrink-0 items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="border-destructive/30 text-destructive hover:bg-destructive/10"
                            @click="showCancelModal = true"
                        >
                            Batalkan
                        </Button>
                        <Button size="sm" class="shrink-0" @click="goToForms"> Lanjutkan </Button>
                    </div>
                </div>
            </template>
        </EventDashboardForm>

        <template v-else-if="step === 'forms' && draftEvent">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                <div class="flex min-w-0 items-center gap-4">
                    <EventWizardStepper :steps="steps" :active-index="currentIndex" />
                    <AutosaveStatus :status="saveState" variant="inline" @retry="autosave.retry" />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" :disabled="leavingFormStep" @click="goBackToEvent">
                        <Loader2 v-if="activeExitAction === 'back'" class="size-4 animate-spin" aria-hidden="true" />
                        Kembali ke event
                    </Button>
                    <Button variant="outline" :disabled="leavingFormStep" @click="skipWizard">
                        <Loader2 v-if="activeExitAction === 'skip'" class="size-4 animate-spin" aria-hidden="true" />
                        Lewati
                    </Button>
                    <Button
                        variant="outline"
                        class="border-destructive/30 text-destructive hover:bg-destructive/10"
                        :disabled="leavingFormStep"
                        @click="showCancelModal = true"
                    >
                        Batalkan
                    </Button>
                    <Button
                        variant="outline"
                        class="border-border/80 bg-background/90"
                        :disabled="builderEmpty"
                        aria-label="Pratinjau formulir"
                        @click="requestPreview"
                    >
                        Pratinjau
                    </Button>
                    <Button :disabled="finishing || leavingFormStep" @click="finishWizard">
                        <Loader2 v-if="finishing" class="size-4 animate-spin" aria-hidden="true" />
                        <template v-else>Selesai</template>
                    </Button>
                </div>
            </div>

            <FormBuilderEditorTabs
                v-model:active-tab="activeFormTab"
                v-model:closed-at="closedAt"
                v-model:visible-for="visibleFor"
                v-model:form-metadata="formMetadata"
                id-prefix="wizard"
            >
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
                    :processing="finishing"
                    @save="finishWizard"
                    @open-settings="activeFormTab = 'settings'"
                />
            </FormBuilderEditorTabs>
        </template>
    </div>

    <ConfirmationModal
        :open="showCancelModal"
        title="Batalkan pembuatan?"
        description="Event draf akan dihapus. Tindakan ini tidak dapat dibatalkan."
        confirm-text="Hapus draf"
        cancel-text="Batal"
        variant="destructive"
        :loading="cancelBusy"
        @confirm="confirmCancel"
        @cancel="showCancelModal = false"
        @update:open="
            (v) => {
                if (!v) showCancelModal = false;
            }
        "
    />
</template>
