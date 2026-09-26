<?php

namespace App\Services\User;

use App\Enums\FormAnswerReviewStatus;
use App\Enums\MemberConfirmationStatus;
use App\Models\Event;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UserManagementService
{
    /**
     * Roles that can be assigned via user management UI.
     *
     * @var list<string>
     */
    public const ASSIGNABLE_ROLES = [
        'admin',
        'member',
        'recruitment-staff',
        'recruitment-interviewer',
    ];

    /**
     * @param  array{search?: string|null, role?: string|null, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForAdminIndex(array $filters, int $page = 1): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = $filters['role'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 10);

        $query = User::query()
            ->with('roles:id,name')
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (is_string($role) && $role !== '') {
            $query->role($role);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (User $user) => $this->toInertiaArray($user))
        );

        return $paginator;
    }

    /**
     * @return array{id: string, name: string, email: string, roles: list<string>, created_at: string|null, deleted_at: string|null}
     */
    public function toInertiaArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
            'created_at' => $user->created_at?->toIso8601String(),
            'deleted_at' => $user->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * Full admin detail payload for a managed user.
     *
     * @return array{
     *     user: array<string, mixed>,
     *     stats: array<string, int>,
     *     registrations: list<array<string, mixed>>,
     *     events_created: list<array<string, mixed>>,
     *     recruitment_applications: list<array<string, mixed>>,
     *     staff: array<string, mixed>,
     *     permissions: array{can_edit: bool, can_delete: bool}
     * }
     */
    public function toDetailPayload(User $user, User $actor, ?Request $request = null): array
    {
        $user->loadMissing('roles:id,name');

        $formAnswers = FormAnswer::query()
            ->where('user_id', $user->id)
            ->excludeTerminatedInvitationMembers()
            ->excludeRejectedSubmissions()
            ->with([
                'form:id,title,event_id',
                'form.event:id,title,slug,start_date,status',
                'attendances' => fn ($q) => $q->orderBy('scanned_at')->limit(1),
            ])
            ->latest()
            ->limit(50)
            ->get();

        $registrations = $formAnswers->map(function (FormAnswer $answer): array {
            $event = $answer->form?->event;
            $firstAttendance = $answer->attendances->first();

            return [
                'form_answer_id' => $answer->id,
                'registration_code' => $answer->registration_code,
                'review_status' => $answer->review_status?->value,
                'registration_role' => $answer->registration_role?->value,
                'member_confirmation_status' => $answer->member_confirmation_status?->value,
                'created_at' => $answer->created_at?->toIso8601String(),
                'attended_at' => $firstAttendance?->scanned_at?->toIso8601String(),
                'event' => $event ? [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'start_date' => $event->start_date?->toDateString(),
                    'status' => $event->status?->value ?? $event->status,
                ] : null,
                'form' => $answer->form ? [
                    'id' => $answer->form->id,
                    'title' => $answer->form->title,
                ] : null,
            ];
        })->values()->all();

        $eventsJoined = $formAnswers
            ->pluck('form.event_id')
            ->filter()
            ->unique()
            ->count();

        $pendingRegistrations = $formAnswers->filter(function (FormAnswer $answer): bool {
            return $answer->review_status === FormAnswerReviewStatus::Pending
                || $answer->member_confirmation_status === MemberConfirmationStatus::Pending;
        })->count();

        $acceptedRegistrations = $formAnswers->filter(
            fn (FormAnswer $answer): bool => $answer->review_status === FormAnswerReviewStatus::Accepted
        )->count();

        $attendancesAsParticipant = $formAnswers->filter(
            fn (FormAnswer $answer): bool => $answer->attendances->isNotEmpty()
        )->count();

        $eventsCreated = Event::query()
            ->where('created_by', $user->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'title', 'slug', 'status', 'start_date', 'created_at'])
            ->map(fn (Event $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'status' => $event->status?->value ?? $event->status,
                'start_date' => $event->start_date?->toDateString(),
                'created_at' => $event->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $email = strtolower(trim((string) $user->email));
        $recruitmentApplications = RecruitmentApplication::query()
            ->with(['period:id,name', 'primaryDivision:id,name'])
            ->where(function ($q) use ($email): void {
                $q->whereRaw('LOWER(personal_email) = ?', [$email])
                    ->orWhereRaw('LOWER(student_email) = ?', [$email]);
            })
            ->orderByDesc('submitted_at')
            ->limit(20)
            ->get()
            ->map(function (RecruitmentApplication $app) use ($email): array {
                $match = strtolower((string) $app->personal_email) === $email
                    ? 'personal_email'
                    : 'student_email';

                return [
                    'id' => $app->id,
                    'registration_number' => $app->registration_number,
                    'full_name' => $app->full_name,
                    'stage' => $app->stage?->value,
                    'result' => $app->result?->value,
                    'submitted_at' => $app->submitted_at?->toIso8601String(),
                    'match' => $match,
                    'period' => $app->period ? [
                        'id' => $app->period->id,
                        'name' => $app->period->name,
                    ] : null,
                    'primary_division' => $app->primaryDivision?->name,
                ];
            })
            ->values()
            ->all();

        $interviewerDivisions = RecruitmentInterviewerDivision::query()
            ->with('division:id,name,code')
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (RecruitmentInterviewerDivision $row): array => [
                'id' => $row->division?->id,
                'name' => $row->division?->name,
                'code' => $row->division?->code,
            ])
            ->filter(fn (array $row): bool => filled($row['id']))
            ->values()
            ->all();

        $interviewsAssigned = RecruitmentInterview::query()
            ->where('interviewer_id', $user->id)
            ->count();

        $scansRecorded = $user->attendanceScansRecorded()->count();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => UserAvatarService::resolvePublicUrl($user->avatar, $request),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
                'deleted_at' => $user->deleted_at?->toIso8601String(),
                'roles' => $user->getRoleNames()->values()->all(),
                'has_local_password' => filled($user->getRawOriginal('password')),
                'oauth' => [
                    'google' => filled($user->google_id),
                    'github' => filled($user->github_id),
                ],
            ],
            'stats' => [
                'events_joined' => $eventsJoined,
                'registrations_pending' => $pendingRegistrations,
                'registrations_accepted' => $acceptedRegistrations,
                'attendances_as_participant' => $attendancesAsParticipant,
                'events_created' => count($eventsCreated),
                'recruitment_applications' => count($recruitmentApplications),
                'scans_recorded' => $scansRecorded,
                'interviews_assigned' => $interviewsAssigned,
            ],
            'registrations' => $registrations,
            'events_created' => $eventsCreated,
            'recruitment_applications' => $recruitmentApplications,
            'staff' => [
                'interviewer_divisions' => $interviewerDivisions,
                'interviews_assigned_count' => $interviewsAssigned,
                'scans_recorded_count' => $scansRecorded,
            ],
            'permissions' => [
                'can_edit' => Gate::forUser($actor)->allows('update', $user),
                'can_delete' => Gate::forUser($actor)->allows('delete', $user),
            ],
        ];
    }

    /**
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->syncRoles([$data['role']]);

            return $user->load('roles:id,name');
        });
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, role: string}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            $user->update($payload);
            $user->syncRoles([$data['role']]);

            return $user->fresh()->load('roles:id,name');
        });
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function roleOptions(): array
    {
        $labels = [
            'admin' => 'Admin',
            'member' => 'Member',
            'recruitment-staff' => 'Recruitment Staff',
            'recruitment-interviewer' => 'Recruitment Interviewer',
        ];

        return array_map(
            fn (string $role) => [
                'value' => $role,
                'label' => $labels[$role] ?? $role,
            ],
            self::ASSIGNABLE_ROLES
        );
    }
}
