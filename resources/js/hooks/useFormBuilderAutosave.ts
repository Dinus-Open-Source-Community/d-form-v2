import { computed, type ComputedRef, type Ref } from 'vue';
import { persistFormSnapshotKeepalive, saveFormFields, updateFormSettings } from '@/adapters/http/forms';
import { describeRequestErrorMessage, isHttpRequestError } from '@/adapters/http/httpError';
import { useAutosaveSync, type TAutosaveStatus } from '@/hooks/useAutosaveSync';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import { builderPendingUploads } from '@/hooks/useBuilderImageUpload';
import { toBackendFields } from '@/components/modules/builder/fieldMapping';
import { prependFormBannerToBackendPayload, type IFormBannerState } from '@/components/modules/builder/formBanner';
import { classifyAutosaveFailure } from '@/lib/autosaveFailure';
import {
    buildFieldsKeepalivePayload,
    buildFormBuilderSettingsPayload,
    buildHeaderAutosavePayload,
    buildHeaderKeepalivePayload,
    isFormBuilderHeaderComplete,
    serializeFieldsSnapshot,
    serializeHeaderSnapshot,
    type IFormBuilderDraftState,
} from '@/lib/formBuilderAutosave';
import type { IBackendField, IBuilderField } from '@/types/form-builder';
import type { IFormRegistrationMetadata } from '@/types/form';

/** Model builder yang dibaca hook setiap kali menyusun payload. */
export interface IFormBuilderAutosaveModels {
    formTitle: Ref<string>;
    formDescription: Ref<string>;
    successContent: Ref<string>;
    closedAt: Ref<string>;
    visibleFor: Ref<string[]>;
    banner: Ref<IFormBannerState>;
    formFields: Ref<IBuilderField[]>;
    formMetadata: Ref<IFormRegistrationMetadata>;
}

/** Endpoint autosave satu form; `null` selama form (draf) belum ada. */
export interface IFormBuilderAutosaveEndpoints {
    fieldsUrl: string;
    settingsUrl: string;
}

export interface IUseFormBuilderAutosaveOptions {
    models: IFormBuilderAutosaveModels;
    endpoints: ComputedRef<IFormBuilderAutosaveEndpoints | null>;
    /**
     * `true` bila form yang disunting masih draf: autosave memakai `autosave: true`
     * (validasi parsial). `false` (default) untuk form published: header wajib lengkap.
     */
    isDraft?: boolean | ComputedRef<boolean>;
    debounceMs?: number;
    /** Gerbang tambahan: autosave baru aktif setelah snapshot awal terpasang. */
    enabled?: ComputedRef<boolean>;
    onError: (message: string) => void;
}

export interface IUseFormBuilderAutosaveResult {
    status: Ref<TAutosaveStatus>;
    hasUnsavedChanges: Ref<boolean>;
    flush: () => Promise<void>;
    retry: () => Promise<void>;
    waitForIdle: () => Promise<void>;
    suspend: () => void;
    resume: () => void;
    markSaved: () => void;
    schedule: () => void;
    /** Snapshot header (judul + deskripsi) dan fields (fields + sections). */
    buildSnapshot: () => string;
    /** Payload settings lengkap; `publish: true`/`fields` dipakai jalur simpan manual. */
    buildSettingsPayload: (options?: {
        publish?: boolean;
        autosave?: boolean;
        fields?: unknown[];
    }) => Record<string, unknown>;
    /** Payload fields backend (banner sudah di-prepend). */
    buildFieldsPayload: () => IBackendField[];
    /** Header builder valid untuk PUT settings mode full. */
    isHeaderComplete: () => boolean;
}

/** Prioritas status gabungan: satu indikator untuk dua kanal. */
const STATUS_PRIORITY: Record<TAutosaveStatus, number> = {
    idle: 0,
    saved: 1,
    saving: 2,
    uploading: 3,
    error: 4,
};

/** Ambil status dengan prioritas tertinggi (satu-satunya status yang ditampilkan). */
export function aggregateAutosaveStatus(statuses: readonly TAutosaveStatus[]): TAutosaveStatus {
    return statuses.reduce<TAutosaveStatus>(
        (worst, current) => (STATUS_PRIORITY[current] > STATUS_PRIORITY[worst] ? current : worst),
        'idle'
    );
}

/**
 * Autosave form builder dengan **dua kanal terpisah** di atas `useAutosaveSync`
 * (debounce, single-flight, retry, keepalive tetap milik engine itu):
 *
 * - **Header**: judul + deskripsi saja (`PUT {form}`), tanpa field/section.
 * - **Fields**: daftar field saja (`POST {form}/fields`), tanpa data header.
 *
 * Konsekuensi: **struktur section tidak pernah di-autosave** (kanal fields tidak
 * membawa metadata); section hanya tersimpan lewat simpan manual/publish.
 * Kegagalan terminal (404/410/419/403) dari kanal mana pun menghentikan keduanya.
 */
export function useFormBuilderAutosave(options: IUseFormBuilderAutosaveOptions): IUseFormBuilderAutosaveResult {
    const { notifyAutosaveTerminalFailure } = useToastNotifications();

    /** Terminal sudah ditangani: jangan lempar toast retry lagi. */
    let terminalFailureHandled = false;

    function isDraftForm(): boolean {
        const flag = options.isDraft;

        if (flag === undefined) return false;

        return typeof flag === 'boolean' ? flag : flag.value;
    }

    function readState(): IFormBuilderDraftState {
        const m = options.models;

        return {
            title: m.formTitle.value,
            description: m.formDescription.value,
            successContent: m.successContent.value,
            closedAt: m.closedAt.value,
            visibleFor: m.visibleFor.value,
            banner: m.banner.value,
            fields: m.formFields.value,
            metadata: m.formMetadata.value,
        };
    }

    /** Banner dipetakan di hook (layer builder) karena `lib/` tidak boleh impor komponen. */
    const buildFieldsPayload = (): IBackendField[] =>
        toBackendFields(
            prependFormBannerToBackendPayload([...options.models.formFields.value], options.models.banner.value)
        );

    const isHeaderComplete = (): boolean => isFormBuilderHeaderComplete(readState());

    function buildSettingsPayload(
        settingsOptions: { publish?: boolean; autosave?: boolean; fields?: unknown[] } = {}
    ): Record<string, unknown> {
        return buildFormBuilderSettingsPayload(readState(), settingsOptions);
    }

    const baseEnabled = computed<boolean>(() => options.endpoints.value !== null && (options.enabled?.value ?? true));

    /** Kedua kanal aktif selama endpoint tersedia; gate-nya ada di sumber masing-masing. */
    const headerEnabled = baseEnabled;
    const fieldsEnabled = baseEnabled;

    /** Tangani kegagalan satu kanal: terminal menghentikan keduanya, selain itu retryable. */
    function handleChannelFailure(error: unknown): void {
        const status = isHttpRequestError(error) ? error.status : null;
        const body = isHttpRequestError(error) ? error.body : null;
        const decision = classifyAutosaveFailure(status, body);

        if (decision.kind === 'terminal') {
            terminalFailureHandled = true;
            headerSync.fail();
            fieldsSync.fail();
            notifyAutosaveTerminalFailure(decision);
            throw new Error(decision.message);
        }

        throw new Error(describeRequestErrorMessage(error, 'Gagal menyimpan form otomatis.'));
    }

    async function saveHeader(): Promise<void> {
        const endpoints = options.endpoints.value;
        if (endpoints === null) return;

        try {
            if (isDraftForm()) {
                // Draf: hanya key header yang dikirim (backend memvalidasi parsial).
                await updateFormSettings(endpoints.settingsUrl, buildHeaderAutosavePayload(readState()));
                return;
            }

            if (!isHeaderComplete()) return;

            await updateFormSettings(endpoints.settingsUrl, buildSettingsPayload());
        } catch (error) {
            handleChannelFailure(error);
        }
    }

    async function saveFields(): Promise<void> {
        const endpoints = options.endpoints.value;
        if (endpoints === null) return;

        try {
            // Hanya endpoint fields; daftar kosong tetap dikirim agar penghapusan
            // field terakhir ikut tersimpan (backend menerima `fields: []`).
            await saveFormFields(endpoints.fieldsUrl, buildFieldsPayload());
        } catch (error) {
            handleChannelFailure(error);
        }
    }

    function keepaliveHeader(): void {
        const endpoints = options.endpoints.value;
        if (endpoints === null) return;

        persistFormSnapshotKeepalive({
            settingsUrl: endpoints.settingsUrl,
            ...buildHeaderKeepalivePayload(readState(), endpoints, { isDraft: isDraftForm() }),
        });
    }

    function keepaliveFields(): void {
        const endpoints = options.endpoints.value;
        if (endpoints === null) return;

        persistFormSnapshotKeepalive(buildFieldsKeepalivePayload(endpoints, buildFieldsPayload()));
    }

    const headerSync = useAutosaveSync(() => serializeHeaderSnapshot(readState()), saveHeader, {
        debounceMs: options.debounceMs ?? 800,
        enabled: headerEnabled,
        isBlocked: () => builderPendingUploads.value > 0,
        onError: (message) => {
            if (terminalFailureHandled) return;
            options.onError(message);
        },
        saveOnUnload: keepaliveHeader,
    });

    const fieldsSync = useAutosaveSync(() => serializeFieldsSnapshot(readState()), saveFields, {
        debounceMs: options.debounceMs ?? 800,
        enabled: fieldsEnabled,
        isBlocked: () => builderPendingUploads.value > 0,
        onError: (message) => {
            if (terminalFailureHandled) return;
            options.onError(message);
        },
        saveOnUnload: keepaliveFields,
    });

    const status = computed<TAutosaveStatus>(() =>
        aggregateAutosaveStatus([headerSync.status.value, fieldsSync.status.value])
    );

    const hasUnsavedChanges = computed<boolean>(
        () => headerSync.hasUnsavedChanges.value || fieldsSync.hasUnsavedChanges.value
    );

    /** Kedua kanal disusulkan berurutan agar request tidak saling mendahului. */
    async function flush(): Promise<void> {
        await headerSync.flush();
        await fieldsSync.flush();
    }

    function retry(): Promise<void> {
        return flush();
    }

    async function waitForIdle(): Promise<void> {
        await Promise.all([headerSync.waitForIdle(), fieldsSync.waitForIdle()]);
    }

    function suspend(): void {
        headerSync.suspend();
        fieldsSync.suspend();
    }

    function resume(): void {
        headerSync.resume();
        fieldsSync.resume();
    }

    function markSaved(): void {
        headerSync.markSaved();
        fieldsSync.markSaved();
    }

    function schedule(): void {
        headerSync.schedule();
        fieldsSync.schedule();
    }

    return {
        status,
        hasUnsavedChanges,
        flush,
        retry,
        waitForIdle,
        suspend,
        resume,
        markSaved,
        schedule,
        buildSnapshot: () => serializeHeaderSnapshot(readState()),
        buildSettingsPayload,
        buildFieldsPayload,
        isHeaderComplete,
    };
}
