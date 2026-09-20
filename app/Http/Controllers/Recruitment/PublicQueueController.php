<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Services\Recruitment\QueueService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicQueueController extends Controller
{
    public function __construct(
        private readonly QueueService $queueService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('OpenRecruitment/QueueIndex', [
            'sessions' => $this->queueService->activeSessionsForPublic(),
        ]);
    }

    public function show(RecruitmentInterviewSession $session): Response
    {
        $this->ensureVisible($session);

        return Inertia::render('OpenRecruitment/QueueDisplay', [
            'snapshot' => $this->queueService->publicSnapshot($session),
            'pollUrl' => route('recruitment.queue.poll', $session),
        ]);
    }

    public function poll(RecruitmentInterviewSession $session): JsonResponse
    {
        $this->ensureVisible($session);

        return response()->json($this->queueService->publicSnapshot($session));
    }

    private function ensureVisible(RecruitmentInterviewSession $session): void
    {
        if (! $session->isVisibleToPublic()) {
            abort(404);
        }
    }
}
