<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use Illuminate\Http\Request;
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
    public function __invoke(Request $request, Event $event, Form $form): Response
    {
        $this->authorize('view', $event);

        abort_unless($form->event_id === $event->id, 404);

        $submissions = FormAnswer::query()
            ->with('user:id,name,email')
            ->where('form_id', $form->id)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn (FormAnswer $answer) => [
                'id'           => $answer->id,
                'user'         => $answer->user
                    ? [
                        'id'    => $answer->user->id,
                        'name'  => $answer->user->name,
                        'email' => $answer->user->email,
                    ]
                    : null,
                'answers'      => $answer->answers,
                'submitted_at' => $answer->created_at->toISOString(),
            ]);

        return Inertia::render('Dashboard/Events/Forms/Submissions', [
            'event'       => [
                'id'    => $event->id,
                'title' => $event->title,
            ],
            'form'        => [
                'id'    => $form->id,
                'title' => $form->title,
            ],
            'submissions' => $submissions,
        ]);
    }
}
