<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Enums\FormAnswerReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\FormAnswer;
use App\Services\Event\EventService;
use App\Services\Event\UserPortalEventResolver;
use App\Services\Registration\RegistrationAnswersSummarizer;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserEventRegistrationController extends Controller
{
    public function __invoke(
        Request $request,
        string $event_segment,
        UserPortalEventResolver $resolver,
        EventService $eventService,
        RegistrationAnswersSummarizer $summarizer,
        RegistrationQrPngGenerator $qrGenerator,
    ): Response {
        $event = $resolver->resolvePublished($event_segment);

        $answer = FormAnswer::query()
            ->with(['form'])
            ->where('user_id', (string) $request->user()->id)
            ->whereHas('form', static fn ($q) => $q->where('event_id', $event->id))
            ->excludeTerminatedInvitationMembers()
            ->orderByDesc('created_at')
            ->first();

        abort_if($answer === null, 404);

        $answersSummary = $summarizer->summarizeForPortal($answer);

        $qrBase64 = null;
        if ($answer->review_status === FormAnswerReviewStatus::Accepted) {
            $png = $qrGenerator->pngForSubmission($answer->id);
            $qrBase64 = base64_encode($png);
        }

        $form = $answer->form;
        $registrationMode = null;
        if ($form !== null && is_array($form->metadata)) {
            $mode = $form->metadata['registration_mode'] ?? null;
            $registrationMode = is_string($mode) ? strtolower($mode) : null;
            if (! in_array($registrationMode, ['single', 'bundle', 'team'], true)) {
                $registrationMode = null;
            }
        }

        return Inertia::render('Dashboard/User/EventRegistration', [
            'event' => $eventService->eventToInertiaArray($event),
            'form' => $form === null ? null : [
                'id' => $form->id,
                'title' => $form->title,
                'registration_mode' => $registrationMode,
            ],
            'registration' => [
                'review_status' => $answer->review_status->value,
                'submitted_at' => $answer->created_at->toIso8601String(),
                'reviewed_at' => $answer->reviewed_at?->toIso8601String(),
                'registration_code' => $answer->review_status === FormAnswerReviewStatus::Accepted
                    ? $answer->registration_code
                    : null,
                'registration_role' => $answer->registration_role?->value,
                'answers_summary' => $answersSummary,
                'qr_base64' => $qrBase64,
            ],
        ]);
    }
}
