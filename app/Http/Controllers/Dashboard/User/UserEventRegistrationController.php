<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Enums\FormAnswerReviewStatus;
use App\Enums\FormPurpose;
use App\Enums\RegistrationRole;
use App\Http\Controllers\Controller;
use App\Models\FormAnswer;
use App\Services\Event\EventService;
use App\Services\Event\UserPortalEventResolver;
use App\Services\Registration\BundleGuestDisplayNameResolver;
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
        BundleGuestDisplayNameResolver $displayNameResolver,
    ): Response {
        $event = $resolver->resolvePublished($event_segment);

        $answer = FormAnswer::query()
            ->with(['form'])
            ->where('user_id', (string) $request->user()->id)
            ->whereHas('form', static function ($q) use ($event): void {
                $q->where('event_id', $event->id)
                    ->where(function ($purposeQuery): void {
                        $purposeQuery
                            ->whereNull('metadata')
                            ->orWhereNull('metadata->purpose')
                            ->orWhere('metadata->purpose', FormPurpose::Registration->value);
                    });
            })
            ->excludeTerminatedInvitationMembers()
            ->excludeRejectedSubmissions()
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

        $bundleParticipants = [];
        if ($registrationMode === 'bundle'
            && $answer->registration_role === RegistrationRole::Leader
            && is_string($answer->group_token)
            && $answer->group_token !== '') {
            $members = FormAnswer::query()
                ->with('user')
                ->where('form_id', $answer->form_id)
                ->where('group_token', $answer->group_token)
                ->where('registration_role', RegistrationRole::Member)
                ->orderBy('created_at')
                ->get();

            /** @var FormAnswer $member */
            foreach ($members as $member) {
                $memberQrBase64 = null;
                $memberCode = null;

                if ($member->review_status === FormAnswerReviewStatus::Accepted) {
                    $memberCode = $member->registration_code;
                    $png = $qrGenerator->pngForSubmission($member->id);
                    $memberQrBase64 = base64_encode($png);
                }

                $bundleParticipants[] = [
                    'invited_email' => $member->invited_email ?? '',
                    'display_name' => $displayNameResolver->resolve($member),
                    'review_status' => $member->review_status?->value ?? 'pending',
                    'registration_code' => $memberCode,
                    'qr_base64' => $memberQrBase64,
                ];
            }
        }

        $successContent = $form?->success_content;
        if (! is_string($successContent) || trim($successContent) === '' || trim($successContent) === '<p></p>') {
            $successContent = null;
        }

        return Inertia::render('Dashboard/User/EventRegistration', [
            'event' => $eventService->eventToInertiaArray($event),
            'form' => $form === null ? null : [
                'id' => $form->id,
                'title' => $form->title,
                'registration_mode' => $registrationMode,
                'success_content' => $successContent,
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
            'bundle_participants' => $bundleParticipants,
        ]);
    }
}
