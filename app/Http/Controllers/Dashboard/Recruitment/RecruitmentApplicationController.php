<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ResendRecruitmentTrackingRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\ApplicationVerificationService;
use App\Services\Recruitment\RecruitmentTrackingResendService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecruitmentApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationVerificationService $verificationService,
        private readonly RecruitmentTrackingResendService $trackingResendService,
    ) {
    }

    public function downloadDocument(Request $request, RecruitmentApplication $application, string $type): StreamedResponse
    {
        $this->authorize('downloadDocument', $application);

        $document = $application->document;
        abort_if($document === null, 404);

        $preview = $request->boolean('preview');

        if ($type === 'cv') {
            abort_if(blank($document->cv_path), 404);

            if ($preview) {
                return Storage::disk('local')->response(
                    $document->cv_path,
                    $document->cv_original_name,
                    ['Content-Type' => 'application/pdf'],
                    'inline',
                );
            }

            return Storage::disk('local')->download(
                $document->cv_path,
                $document->cv_original_name,
                ['Content-Type' => $document->cv_mime],
            );
        }

        if ($type === 'portfolio') {
            abort_if(blank($document->portfolio_path), 404);

            if ($preview) {
                return Storage::disk('local')->response(
                    $document->portfolio_path,
                    $document->portfolio_original_name ?? 'portfolio.pdf',
                    ['Content-Type' => 'application/pdf'],
                    'inline',
                );
            }

            return Storage::disk('local')->download(
                $document->portfolio_path,
                $document->portfolio_original_name ?? 'portfolio',
                ['Content-Type' => $document->portfolio_mime ?? 'application/octet-stream'],
            );
        }

        if ($type === 'instagram_follow') {
            abort_if(blank($document->instagram_follow_path), 404);

            $contentType = $document->instagram_follow_mime ?: 'image/jpeg';

            if ($preview) {
                return Storage::disk('local')->response(
                    $document->instagram_follow_path,
                    $document->instagram_follow_original_name ?? 'instagram-follow',
                    ['Content-Type' => $contentType],
                    'inline',
                );
            }

            return Storage::disk('local')->download(
                $document->instagram_follow_path,
                $document->instagram_follow_original_name ?? 'instagram-follow',
                ['Content-Type' => $contentType],
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
            ->back()
            ->with('message', 'Pendaftaran berhasil diverifikasi.');
    }

    public function resendTracking(ResendRecruitmentTrackingRequest $request, RecruitmentApplication $application): RedirectResponse
    {
        $this->trackingResendService->resend($request->user(), $application, $request);

        return redirect()
            ->back()
            ->with('message', 'Informasi tracking telah dikirim ulang ke applicant.');
    }
}
