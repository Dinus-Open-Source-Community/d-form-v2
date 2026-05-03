<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Jobs\SendRegistrationConfirmationJob;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Services\Form\FormAccessGuard;
use App\Services\Form\RulesBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class FormSubmissionController extends Controller
{
    public function __invoke(Request $request, Event $event, Form $form): RedirectResponse
    {
        abort_unless($form->event_id === $event->id, 404);

        $user   = $request->user();
        $status = FormAccessGuard::check($form, $event, $user);

        if ($status->isBlocked()) {
            Inertia::flash('toast', [
                'type'    => 'error',
                'message' => $status->message(),
            ]);

            return redirect()->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form]);
        }

        $fields = FormField::query()
            ->where('form_id', $form->id)
            ->orderBy('order')
            ->get(['id', 'name', 'input_type', 'metadata']);

        $rawRules  = RulesBuilder::extractRulesFromFields($fields);
        $validator = Validator::make(
            array_merge($request->all(), $request->allFiles()),
            RulesBuilder::build($rawRules)
        );

        if ($validator->fails()) {
            return redirect()
                ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                ->withErrors($validator)
                ->withInput();
        }

        $isAdmin = $user->can('events.list');
        $answers = $this->buildAnswers($request, $fields, $form);

        $submission = DB::transaction(function () use ($answers, $form, $user, $event, $isAdmin): FormAnswer {
            // Lock the event row to prevent concurrent over-quota submissions.
            $lockedEvent = Event::query()->lockForUpdate()->find($event->id);

            // Re-check quota inside the transaction to handle race conditions.
            // Admins are exempt — they may submit even when the event is full.
            if (! $isAdmin
                && $lockedEvent->quota !== null
                && $lockedEvent->quota > 0
                && $lockedEvent->registered_count >= $lockedEvent->quota
            ) {
                throw new \App\Exceptions\QuotaExceededException();
            }

            $submission = FormAnswer::create([
                'answers' => $answers,
                'form_id' => $form->id,
                'user_id' => (string) $user->id,
            ]);

            $lockedEvent->increment('registered_count');

            return $submission;
        });

        SendRegistrationConfirmationJob::dispatch($submission->id)->afterCommit();

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => 'Your registration has been submitted successfully.',
        ]);

        return redirect()->route('dashboard.events.show', ['event' => $event]);
    }

    /**
     * Map validated request data to the `answers` JSON payload.
     *
     * Field storage rules:
     *  - fileUpload  → store on `public` disk, save relative path.
     *  - checkbox    → store as array (may be empty).
     *  - selectInput with is_multiple → store as array.
     *  - everything else → store as scalar string / null.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, FormField>  $fields
     */
    private function buildAnswers(Request $request, $fields, Form $form): array
    {
        $answers = [];

        foreach ($fields as $field) {
            $name     = $field->name;
            $metadata = $field->metadata;
            $meta     = $metadata instanceof \Illuminate\Support\Collection
                ? $metadata->all()
                : (array) $metadata;

            if ($field->input_type === 'fileUpload') {
                $file = $request->file($name);
                $answers[$name] = $file !== null
                    ? $file->store("form-uploads/{$form->id}", 'public')
                    : null;

            } elseif ($field->input_type === 'checkbox') {
                $answers[$name] = $request->input($name, []);

            } elseif ($field->input_type === 'selectInput' && ! empty($meta['is_multiple'])) {
                $answers[$name] = $request->input($name, []);

            } else {
                $answers[$name] = $request->input($name);
            }
        }

        return $answers;
    }
}
