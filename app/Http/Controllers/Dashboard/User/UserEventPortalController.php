<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Enums\FormAccessStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\FormPurpose;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Event\EventService;
use App\Services\Event\UserPortalEventResolver;
use App\Services\Form\FormAccessGuard;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Response as InertiaResponse;

class UserEventPortalController
{
    /** Render the member event detail and preserve registration/QR payload mapping. */
    public function __invoke(
        string $eventSegment,
        EventService $eventService,
        RegistrationQrPngGenerator $qrGenerator,
    ): InertiaResponse {
        $event = app(UserPortalEventResolver::class)->resolvePublished($eventSegment);

        $user = auth()->user();
        $registrationBase = FormAnswer::query()
            ->where('user_id', $user->id)
            ->whereHas('form', function (Builder $query) use ($event): void {
                $query->where('event_id', $event->id)
                    ->where(function (Builder $purposeQuery): void {
                        $purposeQuery
                            ->whereNull('metadata')
                            ->orWhereNull('metadata->purpose')
                            ->orWhere('metadata->purpose', FormPurpose::Registration->value);
                    });
            })
            ->excludeTerminatedInvitationMembers();

        $activeRegistration = (clone $registrationBase)
            ->excludeRejectedSubmissions()
            ->orderByDesc('created_at')
            ->first();

        $registration = $activeRegistration
            ?? (clone $registrationBase)->orderByDesc('created_at')->first();

        $hasPendingTeamInvitation = $registration?->isMemberPendingInvitation() ?? false;
        $pendingTeamInvitationUrl = null;
        if ($hasPendingTeamInvitation) {
            $token = $registration->invitation_token;
            if (is_string($token) && $token !== '') {
                $pendingTeamInvitationUrl = route('dashboard.user.team-invitations.show', ['token' => $token], false);
            }
        }

        $isPortalRegistered = $activeRegistration !== null && ! $hasPendingTeamInvitation;

        $qrBase64 = null;
        $registrationCode = null;
        if ($isPortalRegistered && $activeRegistration->review_status === FormAnswerReviewStatus::Accepted) {
            $registrationCode = $activeRegistration->registration_code;
            $png = $qrGenerator->pngForSubmission($activeRegistration->id);
            $qrBase64 = base64_encode($png);
        }

        $requiredFormIds = Form::query()
            ->where('event_id', $event->id)
            ->where('metadata->purpose', FormPurpose::Other->value)
            ->published()
            ->get()
            ->map(fn (Form $form) => $form->requiresFormId())
            ->filter()
            ->unique()
            ->values()
            ->all();

        $requiredTitles = $requiredFormIds === []
            ? []
            : Form::query()
                ->whereIn('id', $requiredFormIds)
                ->published()
                ->pluck('title', 'id')
                ->all();

        $participantForms = Form::query()
            ->where('event_id', $event->id)
            ->where('metadata->purpose', FormPurpose::Other->value)
            ->published()
            ->orderBy('title')
            ->get()
            ->map(function (Form $form) use ($event, $user, $requiredTitles): array {
                $status = FormAccessGuard::check($form, $event, $user);
                $requiresId = $form->requiresFormId();

                return [
                    'id' => $form->id,
                    'title' => $form->title,
                    'description' => $form->description,
                    'fill_url' => route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false),
                    'access_status' => $status->value,
                    'access_message' => $status->message(),
                    'can_start' => $status === FormAccessStatus::Allowed,
                    'requires_form_title' => $requiresId !== null
                        ? ($requiredTitles[$requiresId] ?? null)
                        : null,
                ];
            })
            ->values()
            ->all();

        return inertia('Dashboard/User/EventDetail', [
            'event' => $eventService->eventToInertiaArray($event),
            'isRegistered' => $isPortalRegistered,
            'pendingTeamInvitationUrl' => $pendingTeamInvitationUrl,
            'registrationStatus' => $isPortalRegistered ? $activeRegistration?->review_status?->value : null,
            'qr_base64' => $qrBase64,
            'registration_code' => $registrationCode,
            'participantForms' => $participantForms,
        ]);
    }
}
