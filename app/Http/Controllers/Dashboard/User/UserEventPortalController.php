<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Enums\FormAccessStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\FormPurpose;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use App\Services\Event\EventService;
use App\Services\Event\UserPortalEventResolver;
use App\Services\Form\FormAccessGuard;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Response as InertiaResponse;

class UserEventPortalController
{
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
                            ->orWhereIn('metadata->purpose', FormPurpose::registrationValues());
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

        $checkInAttendance = $isPortalRegistered && $activeRegistration !== null
            ? EventAttendance::query()
                ->where('form_answer_id', $activeRegistration->id)
                ->latest('scanned_at')
                ->first()
            : null;
        $checkedInAt = $checkInAttendance?->scanned_at?->toIso8601String();
        $checkInFormTitle = $activeRegistration?->form?->title;

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

        $registrationForms = Form::query()
            ->where('event_id', $event->id)
            ->where(function (Builder $purposeQuery): void {
                $purposeQuery
                    ->whereNull('metadata')
                    ->orWhereNull('metadata->purpose')
                    ->orWhereIn('metadata->purpose', FormPurpose::registrationValues());
            })
            ->published()
            ->orderBy('title')
            ->get()
            ->map(fn (Form $form): array => $this->formSummary($form, $event, $user))
            ->values()
            ->all();

        $participantForms = Form::query()
            ->where('event_id', $event->id)
            ->where('metadata->purpose', FormPurpose::Other->value)
            ->published()
            ->orderBy('title')
            ->get()
            ->map(function (Form $form) use ($event, $user, $requiredTitles): array {
                $summary = $this->formSummary($form, $event, $user);
                $requiresId = $form->requiresFormId();

                return array_merge($summary, [
                    'requires_form_title' => $requiresId !== null
                        ? ($requiredTitles[$requiresId] ?? null)
                        : null,
                ]);
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
            'registrationForms' => $registrationForms,
            'participantForms' => $participantForms,
            'checked_in_at' => $checkedInAt,
            'check_in_form_title' => $checkInFormTitle,
        ]);
    }

    /** Build the shared form summary used by registration and participant lists. */
    private function formSummary(Form $form, Event $event, User $user): array
    {
        $status = FormAccessGuard::check($form, $event, $user);

        return [
            'id' => $form->id,
            'title' => $form->title,
            'description' => $form->description,
            'fill_url' => route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false),
            'access_status' => $status->value,
            'can_start' => $status === FormAccessStatus::Allowed,
            'requires_form_title' => null,
            'purpose' => $this->formPurpose($form),
        ];
    }

    /** Form tanpa metadata purpose diperlakukan sebagai form pendaftaran, sama seperti query di atas. */
    private function formPurpose(Form $form): string
    {
        $metadata = $form->metadata;
        $purpose = is_array($metadata) ? ($metadata['purpose'] ?? null) : null;

        if (is_string($purpose) && FormPurpose::tryFrom($purpose) !== null) {
            return $purpose;
        }

        return FormPurpose::Registration->value;
    }
}
