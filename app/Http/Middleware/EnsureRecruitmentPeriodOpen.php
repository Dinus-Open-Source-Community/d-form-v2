<?php

namespace App\Http\Middleware;

use App\Models\Recruitment\RecruitmentPeriod;
use App\Services\Recruitment\RecruitmentPeriodRegistrationGate;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecruitmentPeriodOpen
{
    public function __construct(
        private readonly RecruitmentPeriodRegistrationGate $registrationGate,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $period = $this->registrationGate->findOpenPeriod();

        if ($period === null) {
            // Dijadikan ValidationException agar error memakai jalur validasi
            // standar dengan key 'period'. Request Inertia/non-Inertia sama-sama
            // di-redirect balik (302) dengan error pada session, sehingga
            // onError di frontend menerima key yang sama.
            throw ValidationException::withMessages([
                'period' => $this->registrationGate->closedMessage(
                    RecruitmentPeriod::query()
                        ->where('status', 'open')
                        ->orderByDesc('created_at')
                        ->first(),
                ),
            ]);
        }

        $request->attributes->set('recruitment_period', $period);

        return $next($request);
    }
}
