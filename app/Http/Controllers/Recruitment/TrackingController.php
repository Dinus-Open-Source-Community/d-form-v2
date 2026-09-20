<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\AuthenticateTrackingRequest;
use App\Http\Requests\Recruitment\UpdateApplicationRequest;
use App\Services\Recruitment\ApplicationEditGate;
use App\Services\Recruitment\ApplicationEditor;
use App\Services\Recruitment\RecruitmentDivisionService;
use App\Services\Recruitment\RecruitmentTrackingAuthenticator;
use App\Services\Recruitment\RecruitmentTrackingSession;
use App\Services\Recruitment\TrackingPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackingController extends Controller
{
    public function __construct(
        private readonly RecruitmentTrackingAuthenticator $authenticator,
        private readonly RecruitmentTrackingSession $trackingSession,
        private readonly TrackingPresenter $presenter,
        private readonly ApplicationEditGate $editGate,
        private readonly ApplicationEditor $applicationEditor,
        private readonly RecruitmentDivisionService $divisionService,
    ) {
    }

    public function login(): Response|RedirectResponse
    {
        if ($this->trackingSession->isValid()) {
            return redirect()->route('recruitment.track.show');
        }

        return Inertia::render('OpenRecruitment/Track/Login', [
            'authenticateUrl' => route('recruitment.track.authenticate'),
        ]);
    }

    public function authenticate(AuthenticateTrackingRequest $request): RedirectResponse
    {
        $application = $this->authenticator->attempt(
            $request->string('registration_number')->toString(),
            $request->string('tracking_token')->toString(),
        );

        if ($application === null) {
            return back()
                ->withInput($request->only('registration_number'))
                ->withErrors([
                    'credentials' => RecruitmentTrackingAuthenticator::INVALID_CREDENTIALS_MESSAGE,
                ]);
        }

        $this->trackingSession->store($application->id);

        return redirect()->route('recruitment.track.show');
    }

    public function show(Request $request): Response
    {
        /** @var \App\Models\Recruitment\RecruitmentApplication $application */
        $application = $request->attributes->get('recruitment_application');

        return Inertia::render('OpenRecruitment/Track/Show', [
            'tracking' => $this->presenter->present($application),
            'logoutUrl' => route('recruitment.track.logout'),
            'editUrl' => route('recruitment.track.edit'),
            'correctionUrl' => route('recruitment.track.correction'),
            'feedbackStoreUrl' => route('recruitment.track.feedback.store'),
        ]);
    }

    public function edit(Request $request): Response|RedirectResponse
    {
        /** @var \App\Models\Recruitment\RecruitmentApplication $application */
        $application = $request->attributes->get('recruitment_application');
        $application->loadMissing(['document', 'primaryDivision', 'secondaryDivision']);

        if (! $this->editGate->canEdit($application)) {
            return redirect()
                ->route('recruitment.track.show')
                ->withErrors([
                    'edit' => 'Pendaftaran tidak dapat diedit saat ini.',
                ]);
        }

        $document = $application->document;

        return Inertia::render('OpenRecruitment/Track/Edit', [
            'application' => [
                'full_name' => $application->full_name,
                'nim' => $application->nim,
                'semester' => $application->semester,
                'phone' => $application->phone,
                'personal_email' => $application->personal_email,
                'student_email' => $application->student_email,
                'instagram_username' => $application->instagram_username,
                'primary_division_id' => $application->primary_division_id,
                'secondary_division_id' => $application->secondary_division_id,
                'portfolio_type' => $document?->portfolio_type ?? 'url',
                'portfolio_url' => $document?->portfolio_url,
                'cv_original_name' => $document?->cv_original_name,
                'portfolio_original_name' => $document?->portfolio_original_name,
                'instagram_follow_original_name' => $document?->instagram_follow_original_name,
                'twibbon_url' => $document?->twibbon_url,
            ],
            'divisions' => $this->divisionService->listActiveOrdered()
                ->map(fn ($division) => $this->divisionService->toInertiaArray($division))
                ->values()
                ->all(),
            'updateUrl' => route('recruitment.track.update'),
            'dashboardUrl' => route('recruitment.track.show'),
        ]);
    }

    public function update(UpdateApplicationRequest $request): RedirectResponse
    {
        /** @var \App\Models\Recruitment\RecruitmentApplication $application */
        $application = $request->attributes->get('recruitment_application');

        $this->applicationEditor->update(
            $application,
            $request->validatedPayload(),
            $request->file('cv'),
            $request->file('portfolio_file'),
            $request->file('instagram_follow_proof'),
        );

        return redirect()
            ->route('recruitment.track.show')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Perubahan pendaftaran berhasil disimpan.',
            ]);
    }

    public function logout(): RedirectResponse
    {
        $this->trackingSession->flush();

        return redirect()
            ->route('recruitment.track.login')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Sesi tracking berakhir.',
            ]);
    }
}
