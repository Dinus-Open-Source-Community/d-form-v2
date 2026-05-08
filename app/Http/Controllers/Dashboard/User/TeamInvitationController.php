<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Http\Controllers\Controller;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Enums\RegistrationRole;
use App\Services\Form\RulesBuilder;
use App\Services\Registration\TeamRegistrationSubmitter;
use App\Support\FormFieldTypeMapping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class TeamInvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $answer = FormAnswer::query()
            ->where('invitation_token', $token)
            ->with([
                'form.event',
                'form.formFields' => fn ($q) => $q->orderBy('order'),
                'teamLeaderSubmission.user',
                'user',
            ])
            ->firstOrFail();

        abort_unless((string) $answer->user_id === (string) $request->user()->id, 403);
        abort_unless($answer->registration_role === RegistrationRole::Member, 404);

        $form = $answer->form;
        abort_unless($form !== null && TeamRegistrationSubmitter::isTeamForm($form), 404);

        $fields = $form->formFields
            ->map(fn (FormField $f) => $this->fieldToInertia($f))
            ->values()
            ->all();

        $leaderUser = $answer->teamLeaderSubmission?->user;

        return Inertia::render('Dashboard/User/TeamInvitation', [
            'event'       => [
                'id'    => $form->event->id,
                'slug'  => $form->event->slug,
                'title' => $form->event->title,
            ],
            'form'        => [
                'id'    => $form->id,
                'title' => $form->title,
            ],
            'fields'      => $fields,
            'answers'     => $answer->answers ?? [],
            'leader'      => [
                'name'  => $leaderUser?->name ?? '',
                'email' => $leaderUser?->email ?? '',
            ],
            'alreadyConfirmed' => (bool) $answer->status_confirmation_member,
            'confirmUrl'       => route('dashboard.user.team-invitations.update', ['token' => $token], false),
        ]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $answer = FormAnswer::query()
            ->where('invitation_token', $token)
            ->with(['form.formFields' => fn ($q) => $q->orderBy('order')])
            ->firstOrFail();

        abort_unless((string) $answer->user_id === (string) $request->user()->id, 403);
        abort_unless($answer->registration_role === RegistrationRole::Member, 404);

        $form = $answer->form;
        abort_unless($form !== null && TeamRegistrationSubmitter::isTeamForm($form), 404);
        $form->loadMissing('event');

        if ($answer->status_confirmation_member) {
            return redirect()->route('dashboard.user.team-invitations.show', ['token' => $token]);
        }

        $appendableFields = $form->formFields->where('is_append', true)->values();

        if ($appendableFields->isEmpty()) {
            $answer->forceFill(['status_confirmation_member' => true])->save();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Thank you — your team registration is confirmed.'),
            ]);

            return redirect()->route('dashboard.user.events.show', ['event_segment' => $form->event->slug]);
        }

        $names = $appendableFields->pluck('name')->all();

        $answers = is_array($answer->answers) ? $answer->answers : [];

        $rawRules     = RulesBuilder::extractRulesFromFields($appendableFields);
        $laravelRules = RulesBuilder::build($rawRules);
        $laravelRules = $this->relaxFileRulesWhenPathExists($appendableFields, $laravelRules, $answers);

        $validator = Validator::make(
            array_merge($request->only($names), $request->allFiles()),
            $laravelRules
        );

        if ($validator->fails()) {
            return redirect()->route('dashboard.user.team-invitations.show', ['token' => $token])
                ->withErrors($validator)
                ->withInput();
        }

        foreach ($appendableFields as $field) {
            $name     = $field->name;
            $metadata = $field->metadata;
            $meta     = $metadata instanceof \Illuminate\Support\Collection
                ? $metadata->all()
                : (array) $metadata;

            if ($field->input_type === 'fileUpload') {
                $file = $request->file($name);
                if ($file !== null) {
                    $answers[$name] = $file->store("form-uploads/{$form->id}", 'public');
                }
            } elseif ($field->input_type === 'checkbox') {
                $answers[$name] = $request->input($name, []);
            } elseif ($field->input_type === 'selectInput' && ! empty($meta['is_multiple'])) {
                $answers[$name] = $request->input($name, []);
            } else {
                $answers[$name] = $request->input($name);
            }
        }

        $answer->forceFill([
            'answers' => $answers,
            'status_confirmation_member' => true,
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Thank you — your team registration is confirmed.'),
        ]);

        return redirect()->route('dashboard.user.events.show', ['event_segment' => $form->event->slug]);
    }

    /**
     * If the leader already stored a file path, allow confirming without re-uploading.
     *
     * @param  \Illuminate\Support\Collection<int, FormField>  $appendableFields
     * @param  array<string, array<int, string>>  $laravelRules
     * @param  array<string, mixed>  $answers
     * @return array<string, array<int, string>>
     */
    private function relaxFileRulesWhenPathExists($appendableFields, array $laravelRules, array $answers): array
    {
        foreach ($appendableFields as $field) {
            if ($field->input_type !== 'fileUpload') {
                continue;
            }

            $path = $answers[$field->name] ?? null;
            if (! is_string($path) || $path === '') {
                continue;
            }

            $name = $field->name;
            if (! isset($laravelRules[$name])) {
                continue;
            }

            $laravelRules[$name] = array_map(
                fn (string $rule) => $rule === 'required' ? 'nullable' : $rule,
                $laravelRules[$name]
            );
        }

        return $laravelRules;
    }

    private function fieldToInertia(FormField $f): array
    {
        $meta = $f->metadata;
        if ($meta instanceof \Illuminate\Support\Collection) {
            $meta = $meta->all();
        } elseif (is_object($meta) && method_exists($meta, 'toArray')) {
            $meta = $meta->toArray();
        } else {
            $meta = (array) $meta;
        }

        return [
            'id'          => $f->id,
            'type'        => FormFieldTypeMapping::toApiType($f->input_type),
            'label'       => $f->label,
            'description' => $f->description,
            'name'        => $f->name,
            'order'       => $f->order,
            'metadata'    => $meta,
            'is_append'   => (bool) $f->is_append,
        ];
    }
}
