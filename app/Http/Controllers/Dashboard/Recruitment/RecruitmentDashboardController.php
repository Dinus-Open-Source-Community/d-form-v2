<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\Recruitment\RecruitmentDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentDashboardController extends Controller
{
    public function __construct(
        private readonly RecruitmentDashboardService $dashboardService,
    ) {
    }

    public function __invoke(): Response
    {
        abort_unless(auth()->user()?->can('recruitment.dashboard.view'), 403);

        return Inertia::render('Dashboard/Recruitment/Index', [
            'summary' => $this->dashboardService->summary(auth()->user()),
        ]);
    }
}
