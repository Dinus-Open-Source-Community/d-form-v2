<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller dummy untuk halaman staff OpenRecruitment (frontend mockup M1–3).
 *
 * Props `applications`/`applicationId` sengaja kosong/dummy karena data
 * dinamis dibaca dari `recruitmentStore` di sisi Vue. Saat backend tersedia,
 * isi props dari query DB (lihat spec & plan).
 */
class RecruitmentStaffController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Dashboard/Recruitment/Index', [
            'stats' => [
                'total' => 0,
                'pendingScreening' => 0,
                'passed' => 0,
                'revision' => 0,
                'rejected' => 0,
            ],
        ]);
    }

    public function applicants(): InertiaResponse
    {
        return Inertia::render('Dashboard/Recruitment/Applicants', [
            'applications' => [],
        ]);
    }

    public function applicantDetail(string $applicationId): InertiaResponse
    {
        return Inertia::render('Dashboard/Recruitment/ApplicantDetail', [
            'applicationId' => $applicationId,
        ]);
    }

    public function corrections(): InertiaResponse
    {
        return Inertia::render('Dashboard/Recruitment/CorrectionRequests', [
            'corrections' => [],
        ]);
    }
}
