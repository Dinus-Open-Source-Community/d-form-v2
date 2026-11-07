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

        $participantEmails = [];
        $answers = ['leader' => [], 'members' => []];

        try {
            $validated = $this->validatePayload($request, $fields, $mode, $teamSize);

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
                $this->assertBundleGuestsAvailable($participantEmails, $form);
                $answers = $this->formAnswerInputMapper->map($validated, $fields, $form, $teamSize - 1);
            }

            if ($mode === FormSubmissionMode::Single || $mode === FormSubmissionMode::Other) {
                $answers = $this->formAnswerInputMapper->map($validated, $fields, $form);
            }
        } catch (ValidationException $exception) {
            return $this->fillRedirect($event, $form)
                ->withErrors($exception->errors())
                ->withInput();
        }

        $isAdmin = $user->can('events.list');

        if ($mode === FormSubmissionMode::Bundle) {
            return $this->submitBundleRegistration($answers, $participantEmails, $event, $form, $user, $isAdmin);
        }

        if ($mode === FormSubmissionMode::Team) {
            return $this->submitTeamRegistration($answers['leader'], $participantEmails, $event, $form, $user, $isAdmin);
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
     * Field validation plus the two team-size misconfiguration guards, in the
     * historical order: bundle guard before validation, team guard after.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, FormField>  $fields
     * @return array<string, mixed> validated input
     *
     * @throws ValidationException
     */
    private function validatePayload(Request $request, \Illuminate\Database\Eloquent\Collection $fields, FormSubmissionMode $mode, int $teamSize): array
    {
        if ($mode === FormSubmissionMode::Bundle && $teamSize < 2) {
            throw ValidationException::withMessages([
                'team_member_emails' => __('This bundle form requires a team size of at least 2 in form settings.'),
            ]);
        }

        $validated = FormSubmissionRules::validate($request->all(), $fields, $mode, $teamSize);

        if ($mode === FormSubmissionMode::Team && $teamSize < 2) {
            throw ValidationException::withMessages([
                'team_member_emails' => __('This team form requires a team size of at least 2 in form settings.'),
            ]);
        }

        return $validated;
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
     */
    private function submitBundleRegistration(
        array $answers,
        array $memberEmails,
        Event $event,
        Form $form,
        User $leaderUser,
        bool $isAdmin,
    ): RedirectResponse {
        $result = $this->bundleRegistrationSubmitter->submit(
            $form,
            $event,
            $leaderUser,
            $answers['leader'],
            $answers['members'],
            $memberEmails,
            $isAdmin,
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
     */
    private function submitTeamRegistration(
        array $answers,
        array $memberEmails,
        Event $event,
        Form $form,
        User $leaderUser,
        bool $isAdmin,
    ): RedirectResponse {
        $memberUsers = [];

        foreach ($memberEmails as $i => $email) {
            $memberUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($memberUser === null) {
                return $this->fillRedirect($event, $form)
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

        return $this->successRedirect($leaderUser, $event, $form);
    }

    /**
     * Bundle guests must not be registered for the same form already.
     *
     * @param  array<array-key, string>  $emails
     *
     * @throws ValidationException
     */
    private function assertBundleGuestsAvailable(array $emails, Form $form): void
    {
        foreach ($emails as $index => $email) {
            $this->bundleGuestDuplicateChecker->assertEmailAvailableForForm(
                $form,
                $email,
                "team_member_emails.{$index}",
            );
        }
    }

    private function fillRedirect(Event $event, Form $form): RedirectResponse
    {
        return redirect()->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form]);
    }

    private function successRedirect(User $user, Event $event, Form $form): RedirectResponse
    {
        if ($user->can('events.view')) {
            return redirect()->route('dashboard.events.show', ['event' => $event]);
        }

        return redirect()->route('dashboard.events.forms.submitted', [
            'event' => $event,
            'form' => $form,
        ]);
    }
}
