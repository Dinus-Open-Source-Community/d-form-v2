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
use App\Models\ReferralCode;
use App\Models\User;
use App\Services\Form\FormAccessGuard;
use App\Services\Form\FormPriceService;
use App\Services\Form\FormSubmissionLimit;
use App\Services\Registration\BundleRegistrationSubmitter;
use App\Services\Registration\EventRegistrationCounter;
use App\Services\Registration\FormAnswerWriter;
use App\Services\Registration\FormAnswerFileStorage;
use App\Services\Registration\FormSubmissionContext;
use App\Services\Registration\FormSubmissionOrchestrator;
use App\Services\Registration\FormSubmissionRules;
use App\Services\Registration\TeamRegistrationSubmitter;
use App\Services\Form\ReferralRedemptionContext;
use App\Services\Form\ReferralRedemptionService;
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
        private FormAnswerWriter $formAnswerWriter,
        private FormSubmissionOrchestrator $formSubmissionOrchestrator,
        private FormAnswerFileStorage $formAnswerFileStorage,
        private ReferralRedemptionService $referralRedemptionService,
        private FormPriceService $formPriceService,
    ) {
    }

    public function __invoke(Request $request, Event $event, Form $form): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return app(GuestFormSubmissionController::class)($request, $event, $form);
        }

        abort_unless($form->event_id === $event->id, 404);
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

        try {
            $payload = $this->formSubmissionOrchestrator->validateAndMap(new FormSubmissionContext(
                input: $request->all(),
                form: $form,
                user: $user,
                fields: $fields,
                mode: $mode,
                teamSize: $teamSize,
            ));
        } catch (ValidationException $exception) {
            return $this->fillRedirect($event, $form)
                ->withErrors($exception->errors())
                ->withInput(SecretAnswerInput::flashable($request, $fields, $this->extraMemberSlots($mode, $teamSize)));
        }

        $answers = $payload->answers;
        $participantEmails = $payload->participantEmails;
        $resolvedGuestIdsByEmail = $payload->resolvedGuestIdsByEmail;
        $referralCode = $payload->referralCode;

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
                    $referralCode,
                );
            } catch (ValidationException $exception) {
                $this->formAnswerFileStorage->discardMappedUploads($form, $answers);

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
                $referralCode,
            );
        }

        if ($mode === FormSubmissionMode::Other) {
            try {
                return $this->submitOtherForm($answers['leader'], $form, $user, $event);
            } catch (ValidationException $exception) {
                $this->formAnswerFileStorage->discardMappedUploads($form, $answers['leader']);

                return $this->fillRedirect($event, $form)
                    ->withErrors($exception->errors())
                    ->withInput(SecretAnswerInput::flashable($request, $fields, 0));
            }
        }

        try {
            $submission = DB::transaction(function () use ($answers, $form, $user, $event, $isAdmin, $referralCode): FormAnswer {
                $lockedEvent = Event::query()->lockForUpdate()->find($event->id);

                if ($lockedEvent === null) {
                    throw new \RuntimeException('Event not found.');
                }

                $lockedForm = Form::query()->lockForUpdate()->find($form->id);

                if ($lockedForm === null) {
                    throw new \RuntimeException('Form not found.');
                }

                $resolvedReferralCode = $this->referralRedemptionService->resolveLocked($lockedForm, $referralCode);

                if (FormAnswer::query()
                    ->where('form_id', $lockedForm->id)
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
                    'form_id' => $lockedForm->id,
                    'user_id' => (string) $user->id,
                ]);

                $this->registrationCounter->reserveLocked($lockedEvent, 1);

                if ($resolvedReferralCode !== null) {
                    $this->redeemReferral(
                        $lockedForm,
                        $resolvedReferralCode,
                        $submission,
                        null,
                        $this->participantName($answers['leader'], $user->name),
                        $user->email,
                    );
                }

                $this->snapshotPrice($lockedForm, $submission, [], $resolvedReferralCode);

                return $submission;
            });
        } catch (ValidationException $exception) {
            $this->formAnswerFileStorage->discardMappedUploads($form, $answers['leader']);

            return $this->fillRedirect($event, $form)
                ->withErrors($exception->errors())
                ->withInput(SecretAnswerInput::flashable($request, $fields, 0));
        }

        SendRegistrationConfirmationJob::dispatch($submission->id)->afterCommit();

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => 'Your registration has been submitted successfully.',
        ]);

        return $this->successRedirect($user, $event, $form);
    }

    /**
     * Non-registration forms use the configured total active-submission limit.
     *
     * @param  array<string, mixed>  $answers
     */
    private function submitOtherForm(array $answers, Form $form, User $user, Event $event): RedirectResponse
    {
        DB::transaction(function () use ($answers, $form, $user): void {
            $lockedForm = Form::query()->lockForUpdate()->find($form->id);
            if ($lockedForm === null) {
                throw new \RuntimeException('Form not found.');
            }

            FormSubmissionLimit::assertAvailable($lockedForm, $user);

            $this->formAnswerWriter->create([
                'answers' => $answers,
                'form_id' => $lockedForm->id,
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
     *
     * @param  array{leader: array<string, mixed>, members: list<array<string, mixed>>}  $answers
     * @param  array<array-key, string>  $memberEmails  normalized lowercase emails
     * @param  array<string, string>|null  $resolvedGuestIdsByEmail  pre-check resolution
     *
     */
    private function submitBundleRegistration(
        array $answers,
        array $memberEmails,
        Event $event,
        Form $form,
        User $leaderUser,
        bool $isAdmin,
        ?array $resolvedGuestIdsByEmail,
        ?string $referralCode,
    ): RedirectResponse {
        $result = DB::transaction(function () use (
            $form,
            $event,
            $leaderUser,
            $answers,
            $memberEmails,
            $isAdmin,
            $resolvedGuestIdsByEmail,
            $referralCode,
        ): array {
            $lockedEvent = Event::query()->lockForUpdate()->find($event->id);
            if ($lockedEvent === null) {
                throw new \RuntimeException('Event not found.');
            }

            $lockedForm = Form::query()->lockForUpdate()->find($form->id);
            if ($lockedForm === null) {
                throw new \RuntimeException('Form not found.');
            }

            $resolvedReferralCode = $this->referralRedemptionService->resolveLocked($lockedForm, $referralCode);
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

            if ($resolvedReferralCode !== null) {
                $this->redeemReferral(
                    $lockedForm,
                    $resolvedReferralCode,
                    $result['leader'],
                    $result['leader']->group_token,
                    $this->participantName($answers['leader'], $leaderUser->name),
                    $leaderUser->email,
                    $this->bundleReferralMembers($result['members'], $memberEmails),
                );
            }

            $this->snapshotPrice($lockedForm, $result['leader'], $result['members'], $resolvedReferralCode);

            return $result;
        });

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
     *
     * @param  array<string, mixed>  $answers
     * @param  array<array-key, string>  $memberEmails  normalized lowercase emails
     * @param  array<string, mixed>  $flashableInput  request input without secret answers
     *
     */
    private function submitTeamRegistration(
        array $answers,
        array $memberEmails,
        Event $event,
        Form $form,
        User $leaderUser,
        bool $isAdmin,
        array $flashableInput,
        ?string $referralCode,
    ): RedirectResponse {
        $memberUsers = [];
        $usersByEmail = User::resolveByNormalizedEmails($memberEmails);

        foreach ($memberEmails as $index => $email) {
            $memberUser = $usersByEmail[$email] ?? null;

            if ($memberUser === null) {
                $this->formAnswerFileStorage->discardMappedUploads($form, $answers);

                return $this->fillRedirect($event, $form)
                    ->withErrors(["team_member_emails.{$index}" => __('No account exists for this email. The teammate must register first.')])
                    ->withInput($flashableInput);
            }

            $memberUsers[] = $memberUser;
        }

        try {
            $result = DB::transaction(function () use (
                $form,
                $event,
                $leaderUser,
                $answers,
                $memberUsers,
                $isAdmin,
                $referralCode,
            ): array {
                $lockedEvent = Event::query()->lockForUpdate()->find($event->id);
                if ($lockedEvent === null) {
                    throw new \RuntimeException('Event not found.');
                }

                $lockedForm = Form::query()->lockForUpdate()->find($form->id);
                if ($lockedForm === null) {
                    throw new \RuntimeException('Form not found.');
                }

                $resolvedReferralCode = $this->referralRedemptionService->resolveLocked($lockedForm, $referralCode);
                $result = $this->teamRegistrationSubmitter->submit(
                    $form,
                    $event,
                    $leaderUser,
                    $answers,
                    $memberUsers,
                    $isAdmin,
                );

                if ($resolvedReferralCode !== null) {
                    $this->redeemReferral(
                        $lockedForm,
                        $resolvedReferralCode,
                        $result['leader'],
                        null,
                        $this->participantName($answers, $leaderUser->name),
                        $leaderUser->email,
                        $this->userReferralMembers($memberUsers),
                    );
                }

                $this->snapshotPrice($lockedForm, $result['leader'], $result['members'], $resolvedReferralCode);

                return $result;
            });
        } catch (ValidationException $exception) {
            $this->formAnswerFileStorage->discardMappedUploads($form, $answers);

            return $this->fillRedirect($event, $form)
                ->withErrors($exception->errors())
                ->withInput($flashableInput);
        }

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

    /** @param iterable<FormAnswer> $members */
    private function snapshotPrice(Form $form, FormAnswer $leader, iterable $members, ?ReferralCode $code): void
    {
        $quote = $this->formPriceService->quote($this->formPriceService->basePrice($form), $code);
        $this->formPriceService->snapshot($leader, $members, $quote, $code);
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

    /** @param array<string, mixed> $answers */
    private function participantName(array $answers, string $fallback): ?string
    {
        $name = $answers['full_name'] ?? null;

        return is_string($name) && trim($name) !== '' ? $name : $fallback;
    }

    /** @param list<User> $users @return list<array{name: string|null, email: string|null}> */
    private function userReferralMembers(array $users): array
    {
        return array_map(static fn (User $user): array => [
            'name' => $user->name,
            'email' => $user->email,
        ], $users);
    }

    /** @param list<FormAnswer> $answers @param list<string> $emails @return list<array{name: string|null, email: string|null}> */
    private function bundleReferralMembers(array $answers, array $emails): array
    {
        $members = [];

        foreach ($answers as $index => $answer) {
            $name = is_array($answer->answers) ? ($answer->answers['full_name'] ?? null) : null;
            $members[] = [
                'name' => is_string($name) ? $name : null,
                'email' => $emails[$index] ?? null,
            ];
        }

        return $members;
    }

    /** @param list<array{name: string|null, email: string|null}> $members */
    private function redeemReferral(
        Form $form,
        ReferralCode $code,
        FormAnswer $answer,
        ?string $groupToken,
        ?string $participantName,
        ?string $participantEmail,
        array $members = [],
    ): void {
        $this->referralRedemptionService->redeem(new ReferralRedemptionContext(
            form: $form,
            code: $code,
            answer: $answer,
            groupToken: $groupToken,
            participantName: $participantName,
            participantEmail: $participantEmail,
            members: $members,
        ));
    }

    /** Only land on the event page when the policy actually allows it; otherwise a non-owner organizer would hit a 403 right after a successful submit. */
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
