<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreFeedbackRequest;
use App\Services\Recruitment\FeedbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function __construct(
        private readonly FeedbackService $feedbackService,
    ) {
    }

    public function create(Request $request): Response|RedirectResponse
    {
        /** @var \App\Models\Recruitment\RecruitmentApplication $application */
        $application = $request->attributes->get('recruitment_application');

        if (! $this->feedbackService->canSubmit($application)) {
            return redirect()
                ->route('recruitment.track.show')
                ->withErrors([
                    'feedback' => 'Feedback tidak tersedia untuk pendaftaran ini.',
                ]);
        }

        return Inertia::render('OpenRecruitment/Track/Feedback', [
            'application' => [
                'full_name' => $application->full_name,
                'registration_number' => $application->registration_number,
            ],
            'storeUrl' => route('recruitment.track.feedback.store'),
            'dashboardUrl' => route('recruitment.track.show'),
        ]);
    }

    public function store(StoreFeedbackRequest $request): RedirectResponse
    {
        /** @var \App\Models\Recruitment\RecruitmentApplication $application */
        $application = $request->attributes->get('recruitment_application');

        $this->feedbackService->submit($application, $request->validated());

        return redirect()
            ->route('recruitment.track.show')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Terima kasih! Feedback kamu telah kami terima.',
            ]);
    }
}
