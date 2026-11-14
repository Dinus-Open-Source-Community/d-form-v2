<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Enums\EventStatus;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Models\Event;
use App\Models\FormAnswer;
use App\Services\Event\EventService;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Response as InertiaResponse;

class UserJoinedEventsController
{
    public function __invoke(EventService $eventService): InertiaResponse
    {
        $userId = auth()->id();
        $joinedEventsQuery = fn () => Event::query()
            ->where('status', EventStatus::Published)
            ->whereHas('forms', function (Builder $query) use ($userId): void {
                $query->whereHas('formAnswers', function (Builder $answerQuery) use ($userId): void {
                    $answerQuery->where('user_id', $userId)
                        ->excludeTerminatedInvitationMembers();
                });
            });

        $totalJoined = $joinedEventsQuery()->count();

        $events = $joinedEventsQuery()
            ->forListPage()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(EventService::USER_JOINED_LIMIT)
            ->get();

        $pendingInviteRows = FormAnswer::query()
            ->join('forms', 'forms.id', '=', 'form_answers.form_id')
            ->where('form_answers.user_id', $userId)
            ->where('form_answers.registration_role', RegistrationRole::Member->value)
            ->where('form_answers.member_confirmation_status', MemberConfirmationStatus::Pending->value)
            ->whereNotNull('form_answers.invitation_token')
            ->select(['form_answers.invitation_token', 'forms.event_id'])
            ->get();

        $inviteUrlByEventId = [];
        foreach ($pendingInviteRows as $row) {
            $inviteUrlByEventId[$row->event_id] = route('dashboard.user.team-invitations.show', ['token' => $row->invitation_token], false);
        }

        $payload = $events
            ->map(function (Event $event) use ($eventService, $inviteUrlByEventId): array {
                $data = $eventService->eventToInertiaArray($event);
                if (isset($inviteUrlByEventId[$event->id])) {
                    $data['pending_team_invitation_url'] = $inviteUrlByEventId[$event->id];
                }

                return $data;
            })
            ->values()
            ->all();

        return inertia('Dashboard/User/Events', [
            'events' => $payload,
            'totalJoined' => $totalJoined,
            'truncated' => $totalJoined > count($payload),
            'listMode' => 'mine',
        ]);
    }
}
