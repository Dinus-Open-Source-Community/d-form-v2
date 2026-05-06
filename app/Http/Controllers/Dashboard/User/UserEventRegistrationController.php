<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Enums\FormAnswerReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Form;
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

        $form = Form::query()
            ->where('event_id', $event->id)
            ->orderBy('title')
            ->first();

        abort_if($form === null, 404);

        $answer = FormAnswer::query()
            ->where('form_id', $form->id)
            ->where('user_id', (string) $request->user()->id)
            ->first();

        abort_if($answer === null, 404);

        $answersSummary = $summarizer->summarize($answer);

        $qrBase64 = null;
        if ($answer->review_status === FormAnswerReviewStatus::Accepted) {
            $png = $qrGenerator->pngForSubmission($answer->id);
            $qrBase64 = base64_encode($png);
        }

        return Inertia::render('Dashboard/User/EventRegistration', [
            'event' => $eventService->eventToInertiaArray($event),
            'registration' => [
                'review_status' => $answer->review_status->value,
                'submitted_at' => $answer->created_at->toIso8601String(),
                'reviewed_at' => $answer->reviewed_at?->toIso8601String(),
                'registration_code' => $answer->review_status === FormAnswerReviewStatus::Accepted
                    ? $answer->registration_code
                    : null,
                'answers_summary' => $answersSummary,
                'qr_base64' => $qrBase64,
            ],
        ]);
    }
}
