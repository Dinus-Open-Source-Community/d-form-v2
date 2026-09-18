<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\RecruitmentDivision;
use App\Services\Recruitment\RecruitmentDashboardService;
use App\Services\Recruitment\RecruitmentDivisionService;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentDashboardController extends Controller
{
    public function __construct(
        private readonly RecruitmentDashboardService $dashboardService,
        private readonly RecruitmentDivisionService $divisionService,
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
            'summary' => $summary = $this->dashboardService->summary($user),
            'periods' => $summary['periods'] ?? null,
            'query' => $summary['query'] ?? [],
            'statusOptions' => $summary['statusOptions'] ?? [],
            'divisions' => $this->divisionService->listAllOrdered()
                ->map(fn (RecruitmentDivision $d) => $this->divisionService->toInertiaArray($d)),
        ]);
    }
}
