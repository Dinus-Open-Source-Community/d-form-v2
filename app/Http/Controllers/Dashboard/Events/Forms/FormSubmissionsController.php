<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FormField;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\BundleSubmissionGrouper;
use App\Support\FormFieldTypeMapping;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class FormSubmissionsController extends Controller
{
    /**
     * List all submissions for an event form (admin-only view).
     *
     * Authorises via the existing EventPolicy `view` ability so only users
     * with the `events.view` permission can access this endpoint.
     */
    public function __invoke(
        Request $request,
        Event $event,
        Form $form,
        BundleSubmissionGrouper $grouper
    ): Response {
        $this->authorize('view', $event);

        abort_unless($form->event_id === $event->id, 404);

        $metadata = $form->metadata ?? [];
        $registrationMode = is_array($metadata) ? ($metadata['registration_mode'] ?? 'single') : 'single';

        $fields = $form->formFields()
            ->orderBy('order')
            ->get()
            ->map(fn (FormField $f) => FormFieldTypeMapping::fieldToInertia($f))
            ->values()
            ->all();

        $baseProps = [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
            ],
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'registration_mode' => $registrationMode,
            ],
            'fields' => $fields,
        ];

        // Bundle mode: return grouped submissions
        if ($registrationMode === 'bundle') {
            $page = (int) $request->get('page', 1);
            $perPage = 25;

            // Fetch all bundle submissions (including pending members)
            $allSubmissions = FormAnswer::query()
                ->with(['user:id,name,email,avatar', 'reviewer:id,name,email'])
                ->where('form_id', $form->id)
                ->whereNotNull('group_token')
                ->orderByDesc('created_at')
                ->get();

            // Group by group_token
            $groups = $grouper->groupSubmissions($allSubmissions);

            // Sort groups by submitted_at DESC
            $groups = $groups->sortByDesc('submitted_at')->values();

            // Manual pagination of groups
            $total = $groups->count();
            $offset = ($page - 1) * $perPage;
            $groupsPage = $groups->slice($offset, $perPage)->values();

            $paginator = new LengthAwarePaginator(
                $groupsPage,
                $total,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return Inertia::render('Dashboard/Events/Forms/Submissions', array_merge($baseProps, [
                'bundleGroups' => [
                    'data' => array_values($paginator->items()),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'links' => $this->getPaginationLinks($paginator),
                ],
            ]));
        }

        // Single mode: existing behavior
        $submissions = FormAnswer::query()
            ->with(['user:id,name,email', 'reviewer:id,name,email'])
            ->where('form_id', $form->id)
            ->whereListedForOrganizerParticipantRoster()
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn (FormAnswer $answer) => [
                'id' => $answer->id,
                'user' => $answer->user
                    ? [
                        'id' => $answer->user->id,
                        'name' => $answer->user->name,
                        'email' => $answer->user->email,
                    ]
                    : null,
                'answers' => $answer->answers,
                'submitted_at' => $answer->created_at->toISOString(),
                'review_status' => $answer->review_status?->value,
                'reviewed_at' => $answer->reviewed_at?->toIso8601String(),
                'reviewed_by' => $answer->reviewed_by,
                'registration_role' => $answer->registration_role?->value,
                'member_confirmation_status' => $answer->member_confirmation_status?->value,
                'group_token' => $answer->group_token,
                'reviewer' => $answer->reviewer
                    ? [
                        'id' => $answer->reviewer->id,
                        'name' => $answer->reviewer->name,
                        'email' => $answer->reviewer->email,
                    ]
                    : null,
            ]);

        return Inertia::render('Dashboard/Events/Forms/Submissions', array_merge($baseProps, [
            'submissions' => $submissions,
        ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPaginationLinks(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->linkCollection())->map(function ($link) {
            return [
                'url' => $link['url'],
                'label' => $link['label'],
                'active' => $link['active'],
            ];
        })->all();
    }
}
