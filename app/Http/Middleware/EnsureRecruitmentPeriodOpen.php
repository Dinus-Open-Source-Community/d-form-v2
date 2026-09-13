<?php

namespace App\Http\Middleware;

use App\Models\Recruitment\RecruitmentPeriod;
use App\Services\Recruitment\RecruitmentPeriodRegistrationGate;
use Closure;
use Illuminate\Http\Request;
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
            return back()
                ->withInput()
                ->withErrors([
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
