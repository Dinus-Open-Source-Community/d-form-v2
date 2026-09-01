import type {
    IApplication,
    IRecruitmentPeriod,
    RecruitDivisionId,
} from '@/types/recruitment';

/**
 * Dummy seed data untuk modul OpenRecruitment (frontend mockup, M1–3).
 *
 * ⚠️ Data ini untuk development/mockup saja. Saat backend recruitment
 * tersedia, ganti dengan data dari controller/DB (lihat spec & plan).
 */

export const dummyPeriods: IRecruitmentPeriod[] = [
    {
        id: 'period-2026',
        name: 'OpRec 2026',
        status: 'open',
        registrationStart: '2026-09-01T00:00:00+07:00',
        registrationEnd: '2026-09-30T23:59:59+07:00',
        interviewStart: '2026-10-05T08:00:00+07:00',
        interviewEnd: '2026-10-09T17:00:00+07:00',
        finalizationEnd: '2026-10-15T23:59:59+07:00',
        divisions: ['programming', 'creative_media', 'network', 'data'],
    },
];

export const screeningReasons = [
    'Data tidak lengkap',
    'Data tidak valid',
    'Dokumen tidak sesuai',
    'Dokumen tidak dapat dibaca',
    'Informasi tidak sesuai',
    'Persyaratan tidak terpenuhi',
    'Lainnya',
];

export const divisionLabels: Record<RecruitDivisionId, string> = {
    programming: 'Pemrograman',
    creative_media: 'Creative Media',
    network: 'Jaringan',
    data: 'Data',
};

export const divisionDescriptions: Record<RecruitDivisionId, string> = {
    programming: 'Pengembangan software, web, dan mobile.',
    creative_media: 'Desain grafis, video, dan konten kreatif.',
    network: 'Infrastruktur jaringan dan server.',
    data: 'Data analysis, engineering, dan machine learning.',
};

export const dummyApplications: IApplication[] = [
    {
        id: 'app-0001',
        periodId: 'period-2026',
        registrationNumber: 'OPREC-2026-0001',
        applicant: {
            id: 'apl-0001',
            nim: 'A11.2026.00001',
            fullName: 'Budi Santoso',
            semester: 2,
            phone: '081234567890',
            personalEmail: 'budi@gmail.com',
            studentEmail: 'budi@students.dinus.ac.id',
            instagram: 'budi.santoso',
            primaryDivision: 'programming',
            secondaryDivision: 'data',
            cvFile: 'cv-budi.pdf',
            portfolioType: 'url',
            portfolioUrl: 'https://github.com/budi',
            motivation: 'Ingin mengembangkan skill programming di komunitas.',
            organizationExperience: 'Anggota UKM Robotika.',
            skills: 'PHP, JavaScript, Git',
        },
        stage: 'submitted',
        submittedAt: '2026-09-02T09:15:00+07:00',
        updatedAt: '2026-09-02T09:15:00+07:00',
        screeningHistory: [],
        correctionRequests: [],
        revisionRound: 0,
    },
    {
        id: 'app-0002',
        periodId: 'period-2026',
        registrationNumber: 'OPREC-2026-0002',
        applicant: {
            id: 'apl-0002',
            nim: 'A11.2026.00002',
            fullName: 'Siti Rahma',
            semester: 3,
            phone: '082198765432',
            personalEmail: 'siti.rahma@yahoo.com',
            studentEmail: 'siti@students.dinus.ac.id',
            instagram: 'siti.rahma',
            primaryDivision: 'creative_media',
            secondaryDivision: null,
            cvFile: 'cv-siti.pdf',
            portfolioType: 'file',
            portfolioFile: 'portfolio-siti.pdf',
            motivation: 'Tertarik di bidang desain dan media kreatif.',
            organizationExperience: 'Panitia dies natalis.',
            skills: 'Figma, Illustrator',
        },
        stage: 'screening',
        submittedAt: '2026-09-03T10:00:00+07:00',
        updatedAt: '2026-09-03T10:00:00+07:00',
        screeningHistory: [],
        correctionRequests: [],
        revisionRound: 0,
    },
    {
        id: 'app-0003',
        periodId: 'period-2026',
        registrationNumber: 'OPREC-2026-0003',
        applicant: {
            id: 'apl-0003',
            nim: 'A11.2026.00003',
            fullName: 'Rizky Pratama',
            semester: 2,
            phone: '081311122233',
            personalEmail: 'rizky.pratama@gmail.com',
            studentEmail: 'rizky@students.dinus.ac.id',
            instagram: 'rizky.pratama',
            primaryDivision: 'network',
            secondaryDivision: 'data',
            cvFile: 'cv-rizky.pdf',
            portfolioType: 'url',
            portfolioUrl: 'https://github.com/rizky',
            motivation: 'Ingin mendalami jaringan dan server.',
            organizationExperience: 'Anggota komunitas networking.',
            skills: 'Linux, Cisco, Python',
        },
        stage: 'document_passed',
        submittedAt: '2026-09-04T08:30:00+07:00',
        updatedAt: '2026-09-05T13:45:00+07:00',
        screening: {
            decision: 'pass',
            reason: 'Dokumen lengkap dan valid.',
            decidedBy: 'Admin',
            decidedAt: '2026-09-05T13:45:00+07:00',
        },
        screeningHistory: [
            {
                decision: 'pass',
                reason: 'Dokumen lengkap dan valid.',
                decidedBy: 'Admin',
                decidedAt: '2026-09-05T13:45:00+07:00',
            },
        ],
        correctionRequests: [
            {
                id: 'corr-0002',
                applicationId: 'app-0003',
                requestedBy: 'Rizky Pratama',
                reason: 'Perbaiki alamat email mahasiswa.',
                fields: ['studentEmail'],
                status: 'approved',
                requestedAt: '2026-09-06T09:00:00+07:00',
                resolvedAt: '2026-09-06T10:30:00+07:00',
                resolvedBy: 'Admin',
            },
        ],
        revisionRound: 1,
    },
    {
        id: 'app-0004',
        periodId: 'period-2026',
        registrationNumber: 'OPREC-2026-0004',
        applicant: {
            id: 'apl-0004',
            nim: 'A11.2026.00004',
            fullName: 'Dewi Lestari',
            semester: 3,
            phone: '082244556677',
            personalEmail: 'dewi.lestari@gmail.com',
            studentEmail: 'dewi@students.dinus.ac.id',
            instagram: 'dewi.lestari',
            primaryDivision: 'data',
            secondaryDivision: null,
            cvFile: 'cv-dewi.pdf',
            portfolioType: 'file',
            portfolioFile: 'portfolio-dewi.pdf',
            motivation: 'Tertarik pada data analysis dan machine learning.',
            skills: 'Python, SQL, Excel',
        },
        stage: 'revision_required',
        submittedAt: '2026-09-04T11:00:00+07:00',
        updatedAt: '2026-09-06T14:20:00+07:00',
        screening: {
            decision: 'revision',
            reason: 'Data tidak lengkap',
            notes: 'CV dan portofolio belum diunggah.',
            decidedBy: 'Admin',
            decidedAt: '2026-09-06T14:20:00+07:00',
        },
        screeningHistory: [
            {
                decision: 'revision',
                reason: 'Data tidak lengkap',
                notes: 'CV dan portofolio belum diunggah.',
                decidedBy: 'Admin',
                decidedAt: '2026-09-06T14:20:00+07:00',
            },
        ],
        correctionRequests: [
            {
                id: 'corr-0001',
                applicationId: 'app-0004',
                requestedBy: 'Dewi Lestari',
                reason: 'Tolong periksa kembali berkas saya.',
                fields: ['cvFile', 'portfolioFile'],
                status: 'pending',
                requestedAt: '2026-09-07T08:00:00+07:00',
            },
        ],
        revisionRound: 1,
    },
];
