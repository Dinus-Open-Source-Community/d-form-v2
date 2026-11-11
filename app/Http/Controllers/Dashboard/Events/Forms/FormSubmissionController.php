<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\FormSubmissionMode;
use App\Http\Controllers\Controller;
use App\Jobs\SendRegistrationConfirmationJob;
use App\Jobs\SendTeamInvitationJob;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use App\Services\Form\FormAccessGuard;
use App\Services\Registration\BundleGuestDuplicateChecker;
use App\Services\Registration\BundleRegistrationSubmitter;
use App\Services\Registration\EventRegistrationCounter;
use App\Services\Registration\FormAnswerInputMapper;
use App\Services\Registration\FormAnswerWriter;
use App\Services\Registration\FormSubmissionRules;
use App\Services\Registration\MemberEmailListRules;
use App\Services\Registration\TeamRegistrationSubmitter;
use App\Support\SecretAnswerInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FormSubmissionController extends Controller
{
    public function __construct(
        private TeamRegistrationSubmitter $teamRegistrationSubmitter,
        private BundleRegistrationSubmitter $bundleRegistrationSubmitter,
        private EventRegistrationCounter $registrationCounter,
        private BundleGuestDuplicateChecker $bundleGuestDuplicateChecker,
        private FormAnswerWriter $formAnswerWriter,
        private FormAnswerInputMapper $formAnswerInputMapper,
    ) {
    }

    public function __invoke(Request $request, Event $event, Form $form): RedirectResponse
    {
        abort_unless($form->event_id === $event->id, 404);

        $user = $request->user();
        FormAccessGuard::assertEventAvailable($event, $user);

        $status = FormAccessGuard::check($form, $event, $user);

        if ($status->isBlocked()) {
            Inertia::flash('toast', [
                'type'    => 'error',
                'message' => $status->message(),
            ]);

            return $this->fillRedirect($event, $form);
        }

        $mode = FormSubmissionRules::modeFor($form);

        if ($mode === null) {
            Inertia::flash('toast', [
                'type'    => 'error',
                'message' => __('This form has an invalid registration configuration. Contact the organizer.'),
            ]);

            return $this->fillRedirect($event, $form);
        }

        $fields = FormField::query()
            ->where('form_id', $form->id)
            ->orderBy('order')
            ->get(['id', 'name', 'input_type', 'metadata']);

        $teamSize = in_array($mode, [FormSubmissionMode::Team, FormSubmissionMode::Bundle], true)
            ? TeamRegistrationSubmitter::resolveTeamSize($form)
            : 0;

        $resolvedGuestIdsByEmail = null;

        try {
            $validated = FormSubmissionRules::validate($request->all(), $fields, $mode, $teamSize);

            if ($mode === FormSubmissionMode::Team) {
                $answers = $this->formAnswerInputMapper->map($validated, $fields, $form);
            }

            if ($mode === FormSubmissionMode::Bundle || $mode === FormSubmissionMode::Team) {
                $participantEmails = MemberEmailListRules::resolve(
                    $request->input('team_member_emails'),
                    $user->email,
                    $teamSize - 1,
                );
            }

            if ($mode === FormSubmissionMode::Bundle) {
                // The common duplicate case is rejected here, before the mapper
                // stores appendable uploads on the private disk. A concurrent
                // duplicate detected later by the in-transaction race guard may
                // leave the just-stored upload behind; the form-upload storage
                // sweep owns cleanup of those orphans, not this request.
                $resolvedGuestIdsByEmail = $this->bundleGuestDuplicateChecker
                    ->assertEmailsAvailableForForm($form, $participantEmails);
                $answers = $this->formAnswerInputMapper->map($validated, $fields, $form, $teamSize - 1);
            }

            if ($mode === FormSubmissionMode::Single || $mode === FormSubmissionMode::Other) {
                $answers = $this->formAnswerInputMapper->map($validated, $fields, $form);
            }
        } catch (ValidationException $exception) {
            return $this->fillRedirect($event, $form)
                ->withErrors($exception->errors())
                ->withInput(SecretAnswerInput::flashable($request, $fields, $this->extraMemberSlots($mode, $teamSize)));
        }

        $isAdmin = $user->can('events.list');

        if ($mode === FormSubmissionMode::Bundle) {
            // The guest duplicate check lives once, inside the submitter's
            // transaction (race guard); its ValidationException keeps the same
            // `team_member_emails.{i}` keys and first-fail order as the redirect
            // this path used to build from a duplicated outer check.
            try {
                return $this->submitBundleRegistration(
                    $answers,
                    $participantEmails,
                    $event,
                    $form,
                    $user,
                    $isAdmin,
                    $resolvedGuestIdsByEmail,
                );
            } catch (ValidationException $exception) {
                return $this->fillRedirect($event, $form)
                    ->withErrors($exception->errors())
                    ->withInput(SecretAnswerInput::flashable($request, $fields, $this->extraMemberSlots($mode, $teamSize)));
            }
        }

        if ($mode === FormSubmissionMode::Team) {
            return $this->submitTeamRegistration(
                $answers['leader'],
                $participantEmails,
                $event,
                $form,
                $user,
                $isAdmin,
                SecretAnswerInput::flashable($request, $fields, 0),
            );
        }

        if ($mode === FormSubmissionMode::Other) {
            return $this->submitOtherForm($answers['leader'], $form, $user, $event);
        }

        $submission = DB::transaction(function () use ($answers, $form, $user, $event, $isAdmin): FormAnswer {
            $lockedEvent = Event::query()->lockForUpdate()->find($event->id);

            if ($lockedEvent === null) {
                throw new \RuntimeException('Event not found.');
            }

            if (FormAnswer::query()
                ->where('form_id', $form->id)
                ->where('user_id', $user->id)
                ->excludeRejectedSubmissions()
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'form' => __('You have already submitted this form.'),
                ]);
            }

            $this->registrationCounter->assertCanReserve($lockedEvent, 1, $isAdmin);

            $submission = $this->formAnswerWriter->create([
                'answers' => $answers['leader'],
                'form_id' => $form->id,
                'user_id' => (string) $user->id,
            ]);

            $this->registrationCounter->reserveLocked($lockedEvent, 1);

            return $submission;
        });

        SendRegistrationConfirmationJob::dispatch($submission->id)->afterCommit();

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => 'Your registration has been submitted successfully.',
        ]);

        return $this->successRedirect($user, $event, $form);
    }

    /**
     * Non-registration forms: single submission, no quota / confirmation email.
     *
     * @param  array<string, mixed>  $answers
     */
    private function submitOtherForm(array $answers, Form $form, User $user, Event $event): RedirectResponse
    {
        DB::transaction(function () use ($answers, $form, $user): void {
            if (FormAnswer::query()
                ->where('form_id', $form->id)
                ->where('user_id', $user->id)
                ->excludeRejectedSubmissions()
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'form' => __('You have already submitted this form.'),
                ]);
            }

            $this->formAnswerWriter->create([
                'answers' => $answers,
                'form_id' => $form->id,
                'user_id' => (string) $user->id,
            ]);
        });

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => __('Your form has been submitted successfully.'),
        ]);

        return $this->successRedirect($user, $event, $form);
    }

    /**
     * @param  array{leader: array<string, mixed>, members: list<array<string, mixed>>}  $answers
     * @param  array<array-key, string>  $memberEmails  normalized lowercase emails
     * @param  array<string, string>|null  $resolvedGuestIdsByEmail  pre-check resolution
     */
    private function submitBundleRegistration(
        array $answers,
        array $memberEmails,
        Event $event,
        Form $form,
        User $leaderUser,
        bool $isAdmin,
        ?array $resolvedGuestIdsByEmail,
    ): RedirectResponse {
        $result = $this->bundleRegistrationSubmitter->submit(
            $form,
            $event,
            $leaderUser,
            $answers['leader'],
            $answers['members'],
            $memberEmails,
            $isAdmin,
            $resolvedGuestIdsByEmail,
        );

        SendRegistrationConfirmationJob::dispatch($result['leader']->id)->afterCommit();

        $delaySeconds = (int) config('registration.email_send_delay_seconds', 7);

        foreach ($result['members'] as $index => $memberAnswer) {
            SendRegistrationConfirmationJob::dispatch($memberAnswer->id)
                ->delay(now()->addSeconds($index * $delaySeconds))
                ->afterCommit();
        }

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => __('Your bundle registration has been submitted. Confirmation emails were sent to all participants.'),
        ]);

        return $this->successRedirect($leaderUser, $event, $form);
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<array-key, string>  $memberEmails  normalized lowercase emails
     * @param  array<string, mixed>  $flashableInput  request input without secret answers
     */
    private function submitTeamRegistration(
        array $answers,
        array $memberEmails,
        Event $event,
        Form $form,
        User $leaderUser,
        bool $isAdmin,
        array $flashableInput,
    ): RedirectResponse {
        $memberUsers = [];
        $usersByEmail = User::resolveByNormalizedEmails($memberEmails);

        foreach ($memberEmails as $index => $email) {
            $memberUser = $usersByEmail[$email] ?? null;

            if ($memberUser === null) {
                return $this->fillRedirect($event, $form)
                    ->withErrors(["team_member_emails.{$index}" => __('No account exists for this email. The teammate must register first.')])
                    ->withInput($flashableInput);
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

        return $this->successRedirect($leaderUser, $event, $form);
    }

    private function fillRedirect(Event $event, Form $form): RedirectResponse
    {
        return redirect()->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form]);
    }

    private function extraMemberSlots(FormSubmissionMode $mode, int $teamSize): int
    {
        if ($mode !== FormSubmissionMode::Bundle || $teamSize < 2) {
            return 0;
        }

        return $teamSize - 1;
    }

    /**
     * Only land on the event page when the policy actually allows it; otherwise
     * a non-owner organizer would hit a 403 right after a successful submit.
     */
    private function successRedirect(User $user, Event $event, Form $form): RedirectResponse
    {
        if ($user->can('view', $event)) {
            return redirect()->route('dashboard.events.show', ['event' => $event]);
        }

        return redirect()->route('dashboard.events.forms.submitted', [
            'event' => $event,
            'form' => $form,
        ]);
    }
}
