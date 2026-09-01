import { reactive } from 'vue';
import type {
    IApplication,
    IApplicant,
    ICorrectionRequest,
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
    const now = new Date().toISOString();
    const application: IApplication = {
        id: `app-${Date.now()}`,
        periodId,
        registrationNumber: nextRegistrationNumber(),
        applicant,
        stage: 'submitted',
        submittedAt: now,
        updatedAt: now,
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
): void {
    const app = state.applications.find((a) => a.id === applicationId);
    if (!app) {
        return;
    }
    app.screening = {
        decision,
        reason,
        notes,
        decidedBy: 'Admin',
        decidedAt: new Date().toISOString(),
    };
    if (decision === 'pass') {
        app.stage = 'document_passed';
    } else if (decision === 'reject') {
        app.stage = 'document_rejected';
    } else {
        app.stage = 'revision_required';
    }
    app.updatedAt = new Date().toISOString();
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
    app.updatedAt = new Date().toISOString();
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
        requestedAt: new Date().toISOString(),
    };
    app?.correctionRequests.push(request);
    return request;
}

function approveCorrection(requestId: string): void {
    for (const app of state.applications) {
        const req = app.correctionRequests.find((r) => r.id === requestId);
        if (req) {
            req.status = 'approved';
            req.resolvedAt = new Date().toISOString();
            if (app.stage === 'document_passed' || app.stage === 'document_rejected') {
                app.stage = 'revision_required';
                app.updatedAt = new Date().toISOString();
            }
            return;
        }
    }
}

function rejectCorrection(requestId: string): void {
    for (const app of state.applications) {
        const req = app.correctionRequests.find((r) => r.id === requestId);
        if (req) {
            req.status = 'rejected';
            req.resolvedAt = new Date().toISOString();
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
