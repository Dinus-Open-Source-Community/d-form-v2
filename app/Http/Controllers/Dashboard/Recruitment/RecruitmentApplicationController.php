<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Enums\Recruitment\MembershipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\IndexRecruitmentApplicationRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\ApplicationVerificationService;
use App\Services\Recruitment\RecruitmentApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecruitmentApplicationController extends Controller
{
    public function __construct(
        private readonly RecruitmentApplicationService $applicationService,
        private readonly ApplicationVerificationService $verificationService,
    ) {
    }

    public function index(IndexRecruitmentApplicationRequest $request): Response
    {
        $validated = $request->validated();
        $page = $request->integer('page', 1);

        $paginator = $this->applicationService->paginate($validated, $page);
        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (RecruitmentApplication $application) => $this->applicationService->toListArray($application)
            )
        );

        $periodId = isset($validated['period_id']) ? (string) $validated['period_id'] : null;

        return Inertia::render('Dashboard/Recruitment/Applications/Index', [
            'applications' => $paginator,
            'query' => $validated,
            'queue_counts' => $this->applicationService->queueCounts($periodId),
            'periodOptions' => $this->applicationService->periodOptions(),
            'divisionOptions' => $this->applicationService->divisionOptions(),
            'stageOptions' => collect(\App\Enums\Recruitment\ApplicationStage::cases())
                ->map(fn ($stage) => ['value' => $stage->value, 'label' => $stage->label()])
                ->values()
                ->all(),
        ]);
    }

    public function show(RecruitmentApplication $application): Response
    {
        $this->authorize('view', $application);

        return Inertia::render('Dashboard/Recruitment/Applications/Show', [
            'application' => $this->applicationService->toShowArray($application),
            'screeningReasonOptions' => \App\Enums\Recruitment\ScreeningReason::options(),
            'divisionOptions' => $this->applicationService->divisionOptions(),
            'membershipTypeOptions' => MembershipType::options(),
        ]);
    }

    public function downloadDocument(RecruitmentApplication $application, string $type): StreamedResponse
    {
        $this->authorize('downloadDocument', $application);

        $document = $application->document;
        abort_if($document === null, 404);

        if ($type === 'cv') {
            abort_if(blank($document->cv_path), 404);

            return Storage::disk('local')->download(
                $document->cv_path,
                $document->cv_original_name,
                ['Content-Type' => $document->cv_mime],
            );
        }

        if ($type === 'portfolio') {
            abort_if(blank($document->portfolio_path), 404);

            return Storage::disk('local')->download(
                $document->portfolio_path,
                $document->portfolio_original_name ?? 'portfolio',
                ['Content-Type' => $document->portfolio_mime ?? 'application/octet-stream'],
            );
        }

        abort(404);
    }

    public function verify(Request $request, RecruitmentApplication $application): RedirectResponse
    {
        $this->authorize('view', $application);
        abort_unless($request->user()?->can('recruitment.screening.review'), 403);

        $this->verificationService->verify($request->user(), $application, $request);

        return redirect()
            ->route('dashboard.recruitment.applications.show', $application)
            ->with('message', 'Pendaftaran berhasil diverifikasi.');
    }
}
