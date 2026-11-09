/**
 * Public seam for stateful hooks.
 *
 * Callers should keep importing the concrete module (`@/hooks/useXxx`) so the
 * bundler keeps route-level code splitting. This barrel exists for stable
 * shared consumers only; importing it pulls every hook module.
 */
export { useApplicantEmailing } from './useApplicantEmailing';
export type { IApplicantEmailingState } from './useApplicantEmailing';

export { useAuth } from './useAuth';
export { useAuthHistoryGuard } from './useAuthHistoryGuard';
export { useAuthTabs } from './useAuthTabs';

export { useAutosaveSync } from './useAutosaveSync';
export type {
    TAutosaveStatus,
    IAutosaveStorage,
    IUseAutosaveSyncOptions,
    IUseAutosaveSyncResult,
} from './useAutosaveSync';

export {
    builderPendingUploads,
    cleanStoredImageUrl,
    createLocalImagePreviewUrl,
    isBlobUrl,
    isBuilderUploading,
    isDataUrl,
    isLocalOnlyImageUrl,
    resolveBuilderImageUploadUrl,
    revokeLocalImagePreviewUrl,
    sanitizeBuilderSnapshot,
    stripLocalImageUrlsDeep,
    useBuilderImageUpload,
} from './useBuilderImageUpload';
export type { IBuilderImageUploadResult, IUseBuilderImageUploadOptions } from './useBuilderImageUpload';

export { useDashboardEventShowPage } from './useDashboardEventShowPage';
export { clearTopbar, setTopbar, useTopbar } from './useDashboardTopbar';
export { useEmailAvailability } from './useEmailAvailability';
export { useEventRegistrantsPage } from './useEventRegistrantsPage';
export type { IRegistrantsStatCardModel } from './useEventRegistrantsPage';
export { useEventSearch } from './useEventSearch';
export { useFormAnswerMobileView } from './useFormAnswerMobileView';
export { useFormBuilderDemoPage } from './useFormBuilderDemoPage';
export { useFormBuilderWorkspace } from './useFormBuilderWorkspace';
export type {
    TFormBuilderInspectorMode,
    TFormBuilderMobileTab,
    IFormBuilderValidationIssue,
    IFormBuilderWorkspaceModels,
} from './useFormBuilderWorkspace';
export { useFormFillPage } from './useFormFillPage';
export { useFormSubmissionsPage } from './useFormSubmissionsPage';
export { useGlobalHttpStatusToast } from './useGlobalHttpStatusToast';
export { useGlobalQrScanPage } from './useGlobalQrScanPage';
export type { IGlobalScanSummary, IGlobalScanTargetOption, IGlobalScanTargets } from './useGlobalQrScanPage';
export { useHttpErrorToast } from './useHttpErrorToast';
export { useOtpCodeInput } from './useOtpCodeInput';
export { useOtpCountdown } from './useOtpCountdown';
export { useOtpVerification } from './useOtpVerification';
export { usePageFlashToast } from './usePageFlashToast';
