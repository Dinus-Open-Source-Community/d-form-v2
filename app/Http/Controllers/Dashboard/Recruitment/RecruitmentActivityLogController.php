<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\RecruitmentActivityLog;
use App\Models\Recruitment\RecruitmentPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('recruitment.activity.view'), 403);

        $periodId = $request->string('period_id')->toString() ?: null;
        $action = $request->string('action')->toString() ?: null;

        $query = RecruitmentActivityLog::query()
            ->with(['actor:id,name', 'application:id,recruitment_period_id,registration_number,full_name'])
            ->orderByDesc('created_at');

        if ($periodId !== null && $periodId !== '') {
            $query->where('recruitment_period_id', $periodId);
        }

        if ($action !== null && $action !== '') {
            $query->where('action', 'like', '%'.$action.'%');
        }

        $paginator = $query->paginate(30)->withQueryString();

        return Inertia::render('Dashboard/Recruitment/ActivityLogs/Index', [
            'logs' => $paginator->through(fn (RecruitmentActivityLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'actor_type' => $log->actor_type,
                'actor' => $log->actor ? ['id' => $log->actor->id, 'name' => $log->actor->name] : null,
                'application' => $log->application ? [
                    'id' => $log->application->id,
                    'recruitment_period_id' => $log->application->recruitment_period_id,
                    'registration_number' => $log->application->registration_number,
                    'full_name' => $log->application->full_name,
                ] : null,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'periodOptions' => RecruitmentPeriod::query()
                ->orderByDesc('created_at')
                ->get(['id', 'name'])
                ->map(fn (RecruitmentPeriod $period): array => ['id' => $period->id, 'name' => $period->name])
                ->all(),
            'query' => [
                'period_id' => $periodId,
                'action' => $action,
            ],
        ]);
    }
}
