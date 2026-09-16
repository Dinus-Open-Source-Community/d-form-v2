<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\IndexRecruitmentPeriodRequest;
use App\Http\Requests\Recruitment\ShowRecruitmentPeriodApplicationsRequest;
use App\Http\Requests\Recruitment\StoreRecruitmentPeriodRequest;
use App\Http\Requests\Recruitment\UpdateRecruitmentPeriodRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Services\Recruitment\RecruitmentApplicationService;
use App\Services\Recruitment\RecruitmentPeriodService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentPeriodController extends Controller
{
    public function __construct(
        private readonly RecruitmentPeriodService $periodService,
        private readonly RecruitmentApplicationService $applicationService,
    ) {
    }

    public function index(IndexRecruitmentPeriodRequest $request): Response
    {
        $validated = $request->validated();
        $page = $request->integer('page', 1);

        $paginator = $this->periodService->paginate($validated, $page);
        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (RecruitmentPeriod $period) => $this->periodService->toInertiaArray($period)
            )
        );

        return Inertia::render('Dashboard/Recruitment/Periods/Index', [
            'periods' => $paginator,
            'query' => $validated,
            'statusOptions' => collect(\App\Enums\Recruitment\RecruitmentPeriodStatus::cases())
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])
                ->values()
                ->all(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', RecruitmentPeriod::class);

        return Inertia::render('Dashboard/Recruitment/Periods/Create');
    }

    public function store(StoreRecruitmentPeriodRequest $request): RedirectResponse
    {
        $period = $this->periodService->create($request->validated());

        Inertia::flash('toast', [
            'message' => 'Periode recruitment berhasil dibuat.',
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.recruitment.periods.show', $period);
    }

    public function show(ShowRecruitmentPeriodApplicationsRequest $request, RecruitmentPeriod $period): Response
    {
        $this->authorize('view', $period);

        $period->loadCount('applications');

        $validated = $request->validated();
        $canListApplications = $request->user()?->can('recruitment.applications.list') ?? false;

        $applications = null;
        $queueCounts = [];

        if ($canListApplications) {
            $paginator = $this->applicationService->paginate(
                $validated + ['period_id' => $period->id],
                $request->integer('page', 1),
            );
            $paginator->setCollection(
                $paginator->getCollection()->map(
                    fn (RecruitmentApplication $application) => $this->applicationService->toListArray($application)
                )
            );

            $applications = $paginator;
            $queueCounts = $this->applicationService->queueCounts($period->id);
        }

        return Inertia::render('Dashboard/Recruitment/Periods/Show', [
            'period' => $this->periodService->toInertiaArray($period),
            'applications' => $applications,
            'queue_counts' => (object) $queueCounts,
            'divisionOptions' => $canListApplications ? $this->applicationService->divisionOptions() : [],
            'semesterOptions' => $canListApplications ? $this->applicationService->semesterOptions($period->id) : [],
            'stageOptions' => collect(\App\Enums\Recruitment\ApplicationStage::cases())
                ->map(fn ($stage) => ['value' => $stage->value, 'label' => $stage->label()])
                ->values()
                ->all(),
            'query' => $validated,
        ]);
    }

    public function edit(RecruitmentPeriod $period): Response
    {
        $this->authorize('update', $period);

        return Inertia::render('Dashboard/Recruitment/Periods/Edit', [
            'period' => $this->periodService->toInertiaArray($period),
        ]);
    }

    public function update(UpdateRecruitmentPeriodRequest $request, RecruitmentPeriod $period): RedirectResponse
    {
        $this->authorize('update', $period);

        $this->periodService->update($period, $request->validated());

        return redirect()
            ->route('dashboard.recruitment.periods.show', $period)
            ->with('message', 'Periode recruitment berhasil diperbarui.');
    }

    public function destroy(RecruitmentPeriod $period): RedirectResponse
    {
        $this->authorize('delete', $period);

        $period->delete();

        return redirect()
            ->route('dashboard.recruitment.periods.index')
            ->with('message', 'Periode recruitment berhasil dihapus.');
    }

    public function open(RecruitmentPeriod $period): RedirectResponse
    {
        $this->authorize('open', $period);

        $this->periodService->open($period);

        return redirect()
            ->back()
            ->with('message', 'Periode recruitment dibuka untuk pendaftaran.');
    }

    public function close(RecruitmentPeriod $period): RedirectResponse
    {
        $this->authorize('close', $period);

        $this->periodService->close($period);

        return redirect()
            ->back()
            ->with('message', 'Periode recruitment ditutup.');
    }
}
