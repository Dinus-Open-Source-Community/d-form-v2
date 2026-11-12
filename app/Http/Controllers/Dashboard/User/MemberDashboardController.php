<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FormAnswer;
use App\Services\Event\EventService;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

class MemberDashboardController extends Controller
{
    /** Batas event kalender; menjaga payload tetap terukur pada riwayat panjang. */
    private const CALENDAR_EVENT_LIMIT = 100;

    public function __construct(
        private readonly EventService $eventService,
    ) {
    }

    public function __invoke(Request $request): InertiaResponse
    {
        $userId = auth()->id();
        $today = now()->toDateString();

        // Kolom eksplisit: `answers` JSON tidak dipakai dashboard dan mahal untuk riwayat panjang.
        $formAnswers = FormAnswer::query()
            ->where('user_id', $userId)
            ->excludeTerminatedInvitationMembers()
            ->excludeRejectedSubmissions()
            ->with(['form:id,event_id,title', 'form.event'])
            ->get([
                'id',
                'form_id',
                'review_status',
                'member_confirmation_status',
                'registration_role',
                'invitation_token',
                'created_at',
            ]);

        // Event diambil dari relasi yang sudah di-eager; tidak ada query Event kedua.
        $events = $formAnswers
            ->map(fn (FormAnswer $answer): ?Event => $answer->form?->event)
            ->filter(fn (?Event $event): bool => $event !== null && $event->status === EventStatus::Published)
            ->unique('id')
            ->values();

        $pendingRegistrationsCount = 0;
        $acceptedRegistrationsCount = 0;

        foreach ($formAnswers as $answer) {
            if ($answer->review_status === FormAnswerReviewStatus::Pending
                || $answer->member_confirmation_status === MemberConfirmationStatus::Pending) {
                $pendingRegistrationsCount++;
            }

            if ($answer->review_status === FormAnswerReviewStatus::Accepted) {
                $acceptedRegistrationsCount++;
            }
        }

        $upcomingEvents = $events
            ->filter(fn (Event $event): bool => $event->start_date > $today)
            ->sortBy('start_date')
            ->take(5)
            ->values();

        $upcomingEventsArray = $upcomingEvents
            ->map(fn (Event $event): array => $this->eventService->eventToInertiaArray($event))
            ->all();

        $pendingInvitations = [];
        foreach ($formAnswers as $answer) {
            $event = $answer->form?->event;

            $isPendingInvitation = $answer->registration_role === RegistrationRole::Member
                && $answer->member_confirmation_status === MemberConfirmationStatus::Pending
                && ! empty($answer->invitation_token);

            if (! $isPendingInvitation || $event === null || ! $events->contains('id', $event->id)) {
                continue;
            }

            $pendingInvitations[] = [
                'event' => $this->eventService->eventToInertiaArray($event),
                'invitationUrl' => route('dashboard.user.team-invitations.show', ['token' => $answer->invitation_token], false),
            ];
        }

        $calendarEvents = $events
            ->sortBy('start_date')
            ->take(self::CALENDAR_EVENT_LIMIT)
            ->map(fn (Event $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'start_date' => $event->start_date,
                'end_date' => $event->end_date,
                'category' => $event->category,
                'href' => route('dashboard.user.events.show', ['event_segment' => $event->slug], false),
            ])
            ->values()
            ->all();

        return inertia('Dashboard/User/Index', [
            'stats' => [
                'eventsJoined' => $events->count(),
                'upcomingEvents' => $upcomingEvents->count(),
                'pendingRegistrations' => $pendingRegistrationsCount,
                'acceptedRegistrations' => $acceptedRegistrationsCount,
            ],
            'upcomingEvents' => $upcomingEventsArray,
            'pendingInvitations' => $pendingInvitations,
            'calendarEvents' => $calendarEvents,
        ]);
    }
}
