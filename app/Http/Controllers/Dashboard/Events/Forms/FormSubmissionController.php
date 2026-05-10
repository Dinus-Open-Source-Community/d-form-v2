<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Jobs\SendRegistrationConfirmationJob;
use App\Jobs\SendTeamInvitationJob;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use App\Services\Form\FormAccessGuard;
use App\Services\Form\RulesBuilder;
use App\Services\Registration\BundleRegistrationSubmitter;
use App\Services\Registration\BundleSubmissionRules;
use App\Services\Registration\TeamRegistrationSubmitter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class FormSubmissionController extends Controller
{
    public function __construct(
        private TeamRegistrationSubmitter $teamRegistrationSubmitter,
        private BundleRegistrationSubmitter $bundleRegistrationSubmitter,
    ) {
    }

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

        $isTeam   = TeamRegistrationSubmitter::isTeamForm($form);
        $isBundle = BundleRegistrationSubmitter::isBundleForm($form);

        if ($isTeam && $isBundle) {
            Inertia::flash('toast', [
                'type'    => 'error',
                'message' => __('This form has an invalid registration configuration. Contact the organizer.'),
            ]);

            return redirect()->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form]);
        }

        if ($isBundle) {
            $teamSize = TeamRegistrationSubmitter::resolveTeamSize($form);
            if ($teamSize < 2) {
                return redirect()
                    ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                    ->withErrors(['team_member_emails' => __('This bundle form requires a team size of at least 2 in form settings.')])
                    ->withInput();
            }
            $extraSlots = $teamSize - 1;
            $laravelRules = BundleSubmissionRules::buildForFill($fields, $extraSlots);
        } else {
            $rawRules     = RulesBuilder::extractRulesFromFields($fields);
            $laravelRules = RulesBuilder::build($rawRules);
        }

        $validator = Validator::make(
            array_merge($request->all(), $request->allFiles()),
            $laravelRules
        );

        if ($validator->fails()) {
            return redirect()
                ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                ->withErrors($validator)
                ->withInput();
        }

        $isAdmin = $user->can('events.list');

        if ($isBundle) {
            return $this->submitBundleRegistration($request, $event, $form, $user, $fields, $isAdmin);
        }

        $answers = $this->buildAnswers($request, $fields, $form);

        if ($isTeam) {
            return $this->submitTeamRegistration(
                $request,
                $event,
                $form,
                $user,
                $answers,
                $isAdmin
            );
        }

        $submission = DB::transaction(function () use ($answers, $form, $user, $event, $isAdmin): FormAnswer {
            $lockedEvent = Event::query()->lockForUpdate()->find($event->id);

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

        return $this->successRedirect($user, $event);
    }

    private function submitBundleRegistration(
        Request $request,
        Event $event,
        Form $form,
        User $leaderUser,
        $fields,
        bool $isAdmin,
    ): RedirectResponse {
        $teamSize = TeamRegistrationSubmitter::resolveTeamSize($form);

        if ($teamSize < 2) {
            return redirect()
                ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                ->withErrors(['team_member_emails' => __('This bundle form requires a team size of at least 2 in form settings.')])
                ->withInput();
        }

        $memberSlots = $teamSize - 1;

        $teamValidator = Validator::make($request->all(), [
            'team_member_emails'   => ['required', 'array', "size:{$memberSlots}"],
            'team_member_emails.*' => ['required', 'string', 'email:rfc'],
        ]);

        if ($teamValidator->fails()) {
            return redirect()
                ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                ->withErrors($teamValidator)
                ->withInput();
        }

        /** @var list<string|mixed> $rawEmails */
        $rawEmails = $request->input('team_member_emails', []);
        $emails    = [];

        foreach ($rawEmails as $i => $value) {
            $email = mb_strtolower(trim((string) $value));
            $emails[$i] = $email;
        }

        $leaderLower = mb_strtolower(trim((string) $leaderUser->email));
        foreach ($emails as $i => $email) {
            if ($email === $leaderLower) {
                return redirect()
                    ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                    ->withErrors(["team_member_emails.{$i}" => __('You cannot list yourself as a team member.')])
                    ->withInput();
            }
        }

        if (count(array_unique($emails)) !== count($emails)) {
            return redirect()
                ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                ->withErrors(['team_member_emails' => __('Each team member email must be unique.')])
                ->withInput();
        }

        $memberUsers = [];

        foreach ($emails as $i => $email) {
            $memberUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($memberUser === null) {
                return redirect()
                    ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                    ->withErrors(["team_member_emails.{$i}" => __('No account exists for this email. The teammate must register first.')])
                    ->withInput();
            }

            $memberUsers[] = $memberUser;
        }

        $built  = $this->buildBundleAnswers($request, $fields, $form, $memberSlots);
        $result = $this->bundleRegistrationSubmitter->submit(
            $form,
            $event,
            $leaderUser,
            $built['leader'],
            $built['members'],
            $memberUsers,
            $isAdmin,
        );

        SendRegistrationConfirmationJob::dispatch($result['leader']->id)->afterCommit();

        $delaySeconds = (int) config('registration.email_send_delay_seconds', 7);

        foreach ($result['members'] as $index => $memberAnswer) {
            SendTeamInvitationJob::dispatch($memberAnswer->id)
                ->delay(now()->addSeconds($index * $delaySeconds))
                ->afterCommit();
        }

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => __('Your bundle registration has been submitted. Invitations were sent to participants.'),
        ]);

        return $this->successRedirect($leaderUser, $event);
    }

    private function submitTeamRegistration(
        Request $request,
        Event $event,
        Form $form,
        User $leaderUser,
        array $answers,
        bool $isAdmin,
    ): RedirectResponse {
        $teamSize = TeamRegistrationSubmitter::resolveTeamSize($form);

        if ($teamSize < 2) {
            return redirect()
                ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                ->withErrors(['team_member_emails' => __('This team form requires a team size of at least 2 in form settings.')])
                ->withInput();
        }

        $memberSlots = $teamSize - 1;

        $teamValidator = Validator::make($request->all(), [
            'team_member_emails'   => ['required', 'array', "size:{$memberSlots}"],
            'team_member_emails.*' => ['required', 'string', 'email:rfc'],
        ]);

        if ($teamValidator->fails()) {
            return redirect()
                ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                ->withErrors($teamValidator)
                ->withInput();
        }

        /** @var list<string|mixed> $rawEmails */
        $rawEmails = $request->input('team_member_emails', []);
        $emails    = [];

        foreach ($rawEmails as $i => $value) {
            $email = mb_strtolower(trim((string) $value));
            $emails[$i] = $email;
        }

        $leaderLower = mb_strtolower(trim((string) $leaderUser->email));
        foreach ($emails as $i => $email) {
            if ($email === $leaderLower) {
                return redirect()
                    ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                    ->withErrors(["team_member_emails.{$i}" => __('You cannot list yourself as a team member.')])
                    ->withInput();
            }
        }

        if (count(array_unique($emails)) !== count($emails)) {
            return redirect()
                ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                ->withErrors(['team_member_emails' => __('Each team member email must be unique.')])
                ->withInput();
        }

        $memberUsers = [];

        foreach ($emails as $i => $email) {
            $memberUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($memberUser === null) {
                return redirect()
                    ->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form])
                    ->withErrors(["team_member_emails.{$i}" => __('No account exists for this email. The teammate must register first.')])
                    ->withInput();
            }

            $memberUsers[] = $memberUser;
        }

        $result = $this->teamRegistrationSubmitter->submit(
            $form,
            $event,
            $leaderUser,
            $answers,
            $memberUsers,
            $isAdmin,
        );

        SendRegistrationConfirmationJob::dispatch($result['leader']->id)->afterCommit();

        $delaySeconds = (int) config('registration.email_send_delay_seconds', 7);

        foreach ($result['members'] as $index => $memberAnswer) {
            SendTeamInvitationJob::dispatch($memberAnswer->id)
                ->delay(now()->addSeconds($index * $delaySeconds))
                ->afterCommit();
        }

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => __('Your team registration has been submitted. Invitations were sent to team members.'),
        ]);

        return $this->successRedirect($leaderUser, $event);
    }

    private function successRedirect(User $user, Event $event): RedirectResponse
    {
        if ($user->can('events.view')) {
            return redirect()->route('dashboard.events.show', ['event' => $event]);
        }

        return redirect()->route('dashboard.user.events.show', ['event_segment' => $event->slug]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, FormField>  $fields
     * @return array{leader: array<string, mixed>, members: list<array<string, mixed>>}
     */
    private function buildBundleAnswers(Request $request, $fields, Form $form, int $extraSlots): array
    {
        $dupSet = array_flip(BundleSubmissionRules::duplicatableFieldNames($fields));
        $leader = [];
        /** @var list<array<string, mixed>> $members */
        $members = [];
        for ($i = 0; $i < $extraSlots; $i++) {
            $members[] = [];
        }

        foreach ($fields as $field) {
            if (BundleSubmissionRules::fieldIsDisplayOnly($field)) {
                continue;
            }

            $name = $field->name;

            if (isset($dupSet[$name])) {
                $leader[$name] = $this->extractFieldAnswer($request, $field, $form, $name);
                for ($i = 0; $i < $extraSlots; $i++) {
                    $slot = BundleSubmissionRules::slotInputName($name, $i);
                    $members[$i][$name] = $this->extractFieldAnswer($request, $field, $form, $slot);
                }
            } else {
                $val = $this->extractFieldAnswer($request, $field, $form, $name);
                $leader[$name] = $val;
                for ($i = 0; $i < $extraSlots; $i++) {
                    $members[$i][$name] = $val;
                }
            }
        }

        return ['leader' => $leader, 'members' => $members];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, FormField>  $fields
     */
    private function buildAnswers(Request $request, $fields, Form $form): array
    {
        $answers = [];

        foreach ($fields as $field) {
            if (BundleSubmissionRules::fieldIsDisplayOnly($field)) {
                continue;
            }

            $answers[$field->name] = $this->extractFieldAnswer($request, $field, $form, $field->name);
        }

        return $answers;
    }

    private function extractFieldAnswer(Request $request, FormField $field, Form $form, string $inputName): mixed
    {
        $metadata = $field->metadata;
        $meta     = $metadata instanceof \Illuminate\Support\Collection
            ? $metadata->all()
            : (array) $metadata;

        if ($field->input_type === 'fileUpload') {
            $file = $request->file($inputName);

            return $file !== null
                ? $file->store("form-uploads/{$form->id}", 'public')
                : null;
        }

        if ($field->input_type === 'checkbox') {
            return $request->input($inputName, []);
        }

        if ($field->input_type === 'selectInput' && ! empty($meta['is_multiple'])) {
            return $request->input($inputName, []);
        }

        return $request->input($inputName);
    }
}
