<?php

namespace App\Http\Middleware;

use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\RecruitmentTrackingSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrackingSession
{
    public function __construct(
        private readonly RecruitmentTrackingSession $trackingSession,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->trackingSession->isValid()) {
            $this->trackingSession->flush();

            return redirect()
                ->route('open-recruitment.track.login')
                ->withErrors([
                    'tracking' => 'Sesi tracking habis. Masukkan kembali nomor pendaftaran dan token.',
                ]);
        }

        $application = RecruitmentApplication::query()->find($this->trackingSession->applicationId());

        if ($application === null) {
            $this->trackingSession->flush();

            return redirect()
                ->route('open-recruitment.track.login')
                ->withErrors([
                    'tracking' => 'Sesi tracking tidak valid.',
                ]);
        }

        $request->attributes->set('recruitment_application', $application);

        return $next($request);
    }
}
