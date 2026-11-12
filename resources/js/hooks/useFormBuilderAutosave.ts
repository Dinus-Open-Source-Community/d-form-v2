import { computed, type ComputedRef, type Ref } from 'vue';
import { persistFormSnapshotKeepalive, saveFormFields, updateFormSettings } from '@/adapters/http/forms';
import { describeRequestErrorMessage, isHttpRequestError } from '@/adapters/http/httpError';
import { useAutosaveSync, type TAutosaveStatus } from '@/hooks/useAutosaveSync';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import { builderPendingUploads } from '@/hooks/useBuilderImageUpload';
import { toBackendFields } from '@/components/modules/builder/fieldMapping';
import type { IFormBannerState } from '@/components/modules/builder/formBanner';
import { classifyAutosaveFailure } from '@/lib/autosaveFailure';
import {
    buildFormBuilderSettingsPayload,
    buildHeaderAutosavePayload,
    buildHeaderKeepalivePayload,
    isFormBuilderHeaderComplete,
    type IFormBuilderDraftState,
} from '@/lib/formBuilderAutosave';
import type { IBackendField, IBuilderField } from '@/types/form-builder';
import type { IFormRegistrationMetadata } from '@/types/form';

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

export interface IFormBuilderAutosaveEndpoints {
    fieldsUrl: string;
    settingsUrl: string;
}

export interface IUseFormBuilderAutosaveOptions {
    models: IFormBuilderAutosaveModels;
    endpoints: ComputedRef<IFormBuilderAutosaveEndpoints | null>;
    isDraft?: boolean | ComputedRef<boolean>;
    debounceMs?: number;
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
    buildSnapshot: () => string;
    buildSettingsPayload: (options?: {
        publish?: boolean;
        autosave?: boolean;
        fields?: unknown[];
    }) => Record<string, unknown>;
    buildFieldsPayload: () => IBackendField[];
    isHeaderComplete: () => boolean;
}

const STATUS_PRIORITY: Record<TAutosaveStatus, number> = {
    idle: 0,
    saved: 1,
    saving: 2,
    uploading: 3,
    error: 4,
};

/** Prioritaskan status terburuk untuk indikator autosave tunggal. */
export function aggregateAutosaveStatus(statuses: readonly TAutosaveStatus[]): TAutosaveStatus {
    return statuses.reduce<TAutosaveStatus>(
        (worst, current) => (STATUS_PRIORITY[current] > STATUS_PRIORITY[worst] ? current : worst),
        'idle'
    );
}

/** Satu autosave untuk snapshot builder: fields dikirim dulu, header kemudian. */
export function useFormBuilderAutosave(options: IUseFormBuilderAutosaveOptions): IUseFormBuilderAutosaveResult {
    const { notifyAutosaveTerminalFailure } = useToastNotifications();
    let terminalFailureHandled = false;

    function isDraftForm(): boolean {
        const flag = options.isDraft;
        return flag === undefined ? false : typeof flag === 'boolean' ? flag : flag.value;
    }

    function readState(): IFormBuilderDraftState {
        const models = options.models;
        return {
            title: models.formTitle.value,
            description: models.formDescription.value,
            successContent: models.successContent.value,
            closedAt: models.closedAt.value,
            visibleFor: models.visibleFor.value,
            banner: models.banner.value,
            fields: models.formFields.value,
            metadata: models.formMetadata.value,
        };
    }

    /** Header snapshot dipisahkan dari fields agar autosave field-only tidak mengulang PUT settings. */
    function buildHeaderSnapshot(state: IFormBuilderDraftState): string {
        return JSON.stringify({
            title: state.title,
            description: state.description,
            successContent: state.successContent,
            closedAt: state.closedAt,
            visibleFor: state.visibleFor,
            banner: state.banner,
            metadata: state.metadata,
        });
    }

    /** Banner punya endpoint/form-header sendiri; field sync hanya mengirim canvas fields. */
    const buildFieldsPayload = (): IBackendField[] => toBackendFields([...options.models.formFields.value]);
    const isHeaderComplete = (): boolean => isFormBuilderHeaderComplete(readState());
    const buildSettingsPayload = (
        settingsOptions: { publish?: boolean; autosave?: boolean; fields?: unknown[] } = {}
    ) => buildFormBuilderSettingsPayload(readState(), settingsOptions);
    const enabled = computed<boolean>(() => options.endpoints.value !== null && (options.enabled?.value ?? true));
    let savedHeaderSnapshot = buildHeaderSnapshot(readState());

    function handleFailure(error: unknown): void {
        const status = isHttpRequestError(error) ? error.status : null;
        const body = isHttpRequestError(error) ? error.body : null;
        const decision = classifyAutosaveFailure(status, body);
        if (decision.kind === 'terminal') {
            terminalFailureHandled = true;
            sync.fail();
            notifyAutosaveTerminalFailure(decision);
            throw new Error(decision.message);
        }
        throw new Error(describeRequestErrorMessage(error, 'Gagal menyimpan form otomatis.'));
    }

    async function saveSnapshot(): Promise<void> {
        const endpoints = options.endpoints.value;
        if (endpoints === null) return;

        try {
            await saveFormFields(endpoints.fieldsUrl, buildFieldsPayload());
            if (isDraftForm()) {
                await updateFormSettings(endpoints.settingsUrl, buildHeaderAutosavePayload(readState()));
            } else {
                const state = readState();
                const headerSnapshot = buildHeaderSnapshot(state);

                if (headerSnapshot !== savedHeaderSnapshot && isFormBuilderHeaderComplete(state)) {
                    await updateFormSettings(endpoints.settingsUrl, buildFormBuilderSettingsPayload(state));
                    savedHeaderSnapshot = headerSnapshot;
                }
            }
        } catch (error) {
            handleFailure(error);
        }
    }

    function saveOnUnload(): void {
        const endpoints = options.endpoints.value;
        if (endpoints === null) return;
        const state = readState();
        persistFormSnapshotKeepalive({
            fieldsUrl: endpoints.fieldsUrl,
            fields: buildFieldsPayload(),
            settingsUrl: endpoints.settingsUrl,
            ...(isDraftForm()
                ? buildHeaderKeepalivePayload(state, endpoints, { isDraft: true })
                : buildHeaderSnapshot(state) !== savedHeaderSnapshot
                  ? buildHeaderKeepalivePayload(state, endpoints, { isDraft: false })
                  : {}),
        });
    }

    function markSaved(): void {
        savedHeaderSnapshot = buildHeaderSnapshot(readState());
        sync.markSaved();
    }

    const sync = useAutosaveSync(() => JSON.stringify(readState()), saveSnapshot, {
        debounceMs: options.debounceMs ?? 800,
        enabled,
        isBlocked: () => builderPendingUploads.value > 0,
        onError: (message) => {
            if (!terminalFailureHandled) options.onError(message);
        },
        saveOnUnload,
    });

    return {
        status: sync.status,
        hasUnsavedChanges: sync.hasUnsavedChanges,
        flush: sync.flush,
        retry: sync.retry,
        waitForIdle: sync.waitForIdle,
        suspend: sync.suspend,
        resume: sync.resume,
        markSaved,
        schedule: sync.schedule,
        buildSnapshot: () => JSON.stringify(readState()),
        buildSettingsPayload,
        buildFieldsPayload,
        isHeaderComplete,
    };
}
