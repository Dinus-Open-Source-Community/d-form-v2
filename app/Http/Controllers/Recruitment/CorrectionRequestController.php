<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreCorrectionRequestRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\CorrectionRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CorrectionRequestController extends Controller
{
    public function __construct(
        private readonly CorrectionRequestService $correctionRequestService,
    ) {
    }

    public function store(StoreCorrectionRequestRequest $request): RedirectResponse
    {
        /** @var RecruitmentApplication $application */
        $application = $request->attributes->get('recruitment_application');

        $this->correctionRequestService->store(
            $application,
            $request->string('request_message')->toString(),
            $request,
        );

        return redirect()
            ->route('open-recruitment.track.show')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Permintaan koreksi berhasil dikirim. Tim akan meninjau segera.',
            ]);
    }
}
