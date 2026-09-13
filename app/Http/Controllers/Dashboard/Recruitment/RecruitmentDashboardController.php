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

    public function __invoke(): Response|\Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->can('recruitment.dashboard.view'), 403);

        if ($this->dashboardService->isInterviewerOnly($user)) {
            return redirect()->route('dashboard.recruitment.my-interviews.index');
        }

        return Inertia::render('Dashboard/Recruitment/Index', [
            'summary' => $this->dashboardService->summary($user),
        ]);
    }
}
