import { reactive } from 'vue';
import type {
    IApplication,
    IApplicant,
    ICorrectionRequest,
    IRecruitmentScreening,
    IRecruitmentStore,
    RecruitScreeningDecision,
    RecruitStage,
} from '@/types/recruitment';
import { dummyApplications, dummyPeriods } from './dummyRecruitment';

/**
 * State singleton reactive untuk mockup OpenRecruitment.
 * Data di-share antar halaman (applicant submit → staff → tracking).
 * Saat backend tersedia, modul ini dibuang dan data datang dari controller.
 */

const state = reactive<IRecruitmentStore>({
    periods: [...dummyPeriods],
    applications: [...dummyApplications],
});

let registrationCounter = dummyApplications.length;

function isoNow(): string {
    return new Date().toISOString();
}

function nextRegistrationNumber(): string {
    registrationCounter += 1;
    const year = new Date().getFullYear();
    return `OPREC-${year}-${String(registrationCounter).padStart(4, '0')}`;
}

function getApplicationByRegistrationNumber(regNumber: string): IApplication | undefined {
    return state.applications.find((a) => a.registrationNumber === regNumber);
}

function getApplicationById(id: string): IApplication | undefined {
    return state.applications.find((a) => a.id === id);
}

function addApplication(applicant: IApplicant, periodId: string): IApplication {
    const now = isoNow();
    const application: IApplication = {
        id: `app-${Date.now()}`,
        periodId,
        registrationNumber: nextRegistrationNumber(),
        applicant,
        stage: 'submitted',
        submittedAt: now,
        updatedAt: now,
        screeningHistory: [],
        correctionRequests: [],
        revisionRound: 0,
    };
    state.applications.unshift(application);
    return application;
}

function screeningDecision(
    applicationId: string,
    decision: RecruitScreeningDecision,
    reason: string,
    notes?: string,
    decidedBy = 'Admin',
): void {
    const app = state.applications.find((a) => a.id === applicationId);
    if (!app) {
        return;
    }
    const entry: IRecruitmentScreening = {
        decision,
        reason,
        notes,
        decidedBy,
        decidedAt: isoNow(),
    };
    app.screening = entry;
    app.screeningHistory.push(entry); // audit: tidak menimpa
    if (decision === 'pass') {
        app.stage = 'document_passed';
    } else if (decision === 'reject') {
        app.stage = 'document_rejected';
    } else {
        app.stage = 'revision_required';
    }
    app.updatedAt = isoNow();
}

function resubmitApplication(applicationId: string, applicant: IApplicant): void {
    const app = state.applications.find((a) => a.id === applicationId);
    if (!app) {
        return;
    }
    app.applicant = applicant;
    app.stage = 'screening';
    app.screening = undefined;
    app.revisionRound += 1;
    app.updatedAt = isoNow();
}

function requestCorrection(
    applicationId: string,
    reason: string,
    fields: string[],
): ICorrectionRequest {
    const app = state.applications.find((a) => a.id === applicationId);
    const request: ICorrectionRequest = {
        id: `corr-${Date.now()}`,
        applicationId,
        requestedBy: app?.applicant.fullName ?? 'Applicant',
        reason,
        status: 'pending',
        fields,
        requestedAt: isoNow(),
    };
    app?.correctionRequests.push(request);
    return request;
}

function approveCorrection(requestId: string, decidedBy = 'Admin'): void {
    for (const app of state.applications) {
        const req = app.correctionRequests.find((r) => r.id === requestId);
        if (req) {
            req.status = 'approved';
            req.resolvedAt = isoNow();
            req.resolvedBy = decidedBy;
            if (app.stage === 'document_passed' || app.stage === 'document_rejected') {
                app.stage = 'revision_required';
                app.updatedAt = isoNow();
            }
            return;
        }
    }
}

function rejectCorrection(requestId: string, reason: string, decidedBy = 'Admin'): void {
    if (!reason.trim()) {
        return; // alasan wajib non-empty
    }
    for (const app of state.applications) {
        const req = app.correctionRequests.find((r) => r.id === requestId);
        if (req) {
            req.status = 'rejected';
            req.resolvedAt = isoNow();
            req.resolvedBy = decidedBy;
            req.resolutionNote = reason.trim();
            // stage TIDAK berubah
            return;
        }
    }
}

function resetRecruitmentStore(): void {
    state.periods = [...dummyPeriods];
    state.applications = [...dummyApplications];
    registrationCounter = dummyApplications.length;
}

export const recruitmentStore = {
    state,
    addApplication,
    getApplicationByRegistrationNumber,
    getApplicationById,
    screeningDecision,
    resubmitApplication,
    requestCorrection,
    approveCorrection,
    rejectCorrection,
    resetRecruitmentStore,
    nextRegistrationNumber,
};

/** @internal — untuk testing store logic bila perlu. */
export type { RecruitStage };
