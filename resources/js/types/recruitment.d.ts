export type RecruitPeriodStatus = 'draft' | 'open' | 'closed' | 'archived';
export type RecruitStage =
    | 'submitted' | 'screening' | 'revision_required'
    | 'document_passed' | 'document_rejected'
    | 'interview_scheduled' | 'waiting_attendance' | 'queued' | 'interviewing'
    | 'interviewed' | 'final_review' | 'accepted' | 'rejected' | 'cancelled';

export type RecruitDivisionId = 'programming' | 'creative_media' | 'network' | 'data';

export interface IRecruitmentPeriod {
    id: string;
    name: string;
    status: RecruitPeriodStatus;
    registrationStart: string;
    registrationEnd: string;
    interviewStart?: string;
    interviewEnd?: string;
    finalizationEnd?: string;
    divisions: RecruitDivisionId[];
}

export interface IApplicant {
    id: string;
    nim: string;
    fullName: string;
    semester: 1 | 2 | 3;
    phone: string;
    personalEmail: string;
    studentEmail: string;
    instagram: string;
    primaryDivision: RecruitDivisionId;
    secondaryDivision: RecruitDivisionId | null;
    cvFile: string | null;
    portfolioType: 'url' | 'file' | null;
    portfolioUrl?: string | null;
    portfolioFile?: string | null;
    motivation: string;
    organizationExperience?: string;
    skills?: string;
}

export type RecruitScreeningDecision = 'pass' | 'revision' | 'reject';

export interface IRecruitmentScreening {
    decision: RecruitScreeningDecision;
    reason: string;
    notes?: string;
    decidedBy: string;
    decidedAt: string;
}

export interface ICorrectionRequest {
    id: string;
    applicationId: string;
    requestedBy: string;
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
    fields: string[];
    requestedAt: string;
    resolvedAt?: string;
    resolvedBy?: string;      // actor yang memutuskan
    resolutionNote?: string;  // alasan wajib saat reject
}

export interface IApplication {
    id: string;
    periodId: string;
    registrationNumber: string;
    applicant: IApplicant;
    stage: RecruitStage;
    submittedAt: string;
    updatedAt: string;
    screening?: IRecruitmentScreening;
    screeningHistory: IRecruitmentScreening[]; // audit: semua keputusan (lama→baru)
    correctionRequests: ICorrectionRequest[];
    revisionRound: number;
    cancellation?: { reason: string; at: string };
}

export interface IRecruitmentStore {
    periods: IRecruitmentPeriod[];
    applications: IApplication[];
}
