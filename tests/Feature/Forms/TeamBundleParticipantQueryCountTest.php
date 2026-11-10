<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use App\Services\Registration\BundleGuestDuplicateChecker;
use App\Services\Registration\BundleRegistrationSubmitter;
use App\Services\Registration\TeamRegistrationSubmitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Participant duplicate checks must run in a constant number of queries,
 * independent of the number of team or bundle members.
 */
class TeamBundleParticipantQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function openEvent(array $overrides = []): Event
    {
        return Event::factory()->create(array_merge([
            'status'             => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end'   => now()->addDays(30),
            'quota'              => 100,
            'registered_count'   => 0,
        ], $overrides));
    }

    private function openForm(Event $event, array $overrides = []): Form
    {
        return Form::factory()->create(array_merge([
            'event_id'    => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at'   => now()->addDays(30),
        ], $overrides));
    }

    /**
     * @return list<User>
     */
    private function users(int $count): array
    {
        $users = [];

        for ($i = 0; $i < $count; $i++) {
            $users[] = User::factory()->create();
        }

        return $users;
    }

    private function countQueriesMatching(string $pattern): int
    {
        $count = 0;

        foreach (DB::getQueryLog() as $entry) {
            if (preg_match($pattern, (string) ($entry['query'] ?? '')) === 1) {
                $count++;
            }
        }

        return $count;
    }

    public function test_team_submit_uses_one_participant_duplicate_query_for_eleven_members(): void
    {
        $leader      = User::factory()->create();
        $memberUsers = $this->users(10);
        $event       = $this->openEvent();
        $form        = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size'         => 11,
            ],
        ]);

        $submitter = app(TeamRegistrationSubmitter::class);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $submitter->submit($form, $event, $leader, ['full_name' => 'Leader'], $memberUsers, false);

        $duplicateQueries = $this->countQueriesMatching('/["`]?user_id["`]?\s*(?:=\s*\?|in\s*\()/i');

        DB::disableQueryLog();

        $this->assertSame(1, $duplicateQueries, 'Expected exactly one batched participant duplicate query.');
        $this->assertSame(11, FormAnswer::query()->where('form_id', $form->id)->where('is_active', 1)->count());
    }

    public function test_team_duplicate_member_still_rejected_with_generic_key(): void
    {
        $leader      = User::factory()->create();
        $memberUsers = $this->users(10);
        $event       = $this->openEvent();
        $form        = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size'         => 11,
            ],
        ]);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $memberUsers[7]->id,
        ]);

        $submitter = app(TeamRegistrationSubmitter::class);

        try {
            $submitter->submit($form, $event, $leader, ['full_name' => 'Leader'], $memberUsers, false);
            $this->fail('Expected ValidationException for duplicate participant.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team_member_emails', $exception->errors());
            $this->assertSame(
                __('A participant is already registered for this form.'),
                $exception->errors()['team_member_emails'][0],
            );
        }

        $this->assertSame(1, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_bundle_submit_uses_constant_participant_duplicate_queries_for_ten_members(): void
    {
        $leader       = User::factory()->create();
        $memberEmails = [];
        $memberRows   = [];

        for ($i = 0; $i < 10; $i++) {
            $memberEmails[] = 'bundle-member-'.$i.'-'.uniqid().'@example.com';
            $memberRows[]   = ['full_name' => 'Member '.$i];
        }

        $event = $this->openEvent();
        $form  = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'bundle',
                'max_team_size'     => 11,
            ],
        ]);

        $submitter = app(BundleRegistrationSubmitter::class);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $submitter->submit($form, $event, $leader, ['full_name' => 'Leader'], $memberRows, $memberEmails, false);

        $emailQueries    = $this->countQueriesMatching('/["`]?email_normalized["`]?\s+in\s*\(/i');
        $legacyEmail     = $this->countQueriesMatching('/\blower\s*\(\s*"?email"?\s*\)/i');
        $invitedQueries  = $this->countQueriesMatching('/["`]?invited_email["`]?\s+in\s*\(/i');
        $legacyInvited   = $this->countQueriesMatching('/\blower\s*\(\s*["`]?invited_email/i');

        DB::disableQueryLog();

        $this->assertSame(1, $emailQueries, 'Expected one batched user-email lookup.');
        $this->assertSame(0, $legacyEmail, 'The insert loop must not resolve users one by one.');
        $this->assertSame(1, $invitedQueries, 'Expected one batched invited-email match.');
        $this->assertSame(0, $legacyInvited, 'LOWER(invited_email) must not defeat the index.');
        $this->assertSame(11, FormAnswer::query()->where('form_id', $form->id)->where('is_active', 1)->count());
    }

    public function test_bundle_duplicate_guest_email_rejected_at_member_index(): void
    {
        $leader       = User::factory()->create();
        $memberEmails = [];
        $memberRows   = [];

        for ($i = 0; $i < 10; $i++) {
            $memberEmails[] = 'bundle-dup-guest-'.$i.'-'.uniqid().'@example.com';
            $memberRows[]   = ['full_name' => 'Member '.$i];
        }

        $event = $this->openEvent();
        $form  = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'bundle',
                'max_team_size'     => 11,
            ],
        ]);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'invited_email' => $memberEmails[7],
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'answers' => ['full_name' => 'Existing Guest'],
        ]);

        $submitter = app(BundleRegistrationSubmitter::class);

        try {
            $submitter->submit($form, $event, $leader, ['full_name' => 'Leader'], $memberRows, $memberEmails, false);
            $this->fail('Expected ValidationException for duplicate guest email.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team_member_emails.7', $exception->errors());
            $this->assertSame(
                __('A participant with this email is already registered for this form.'),
                $exception->errors()['team_member_emails.7'][0],
            );
        }

        $this->assertSame(1, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_bundle_duplicate_registered_account_rejected_at_member_index(): void
    {
        $leader       = User::factory()->create();
        $memberEmails = [];
        $memberRows   = [];

        for ($i = 0; $i < 10; $i++) {
            $memberEmails[] = 'bundle-dup-account-'.$i.'-'.uniqid().'@example.com';
            $memberRows[]   = ['full_name' => 'Member '.$i];
        }

        $event = $this->openEvent();
        $form  = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'bundle',
                'max_team_size'     => 11,
            ],
        ]);

        $registeredUser = User::factory()->create(['email' => $memberEmails[5]]);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $registeredUser->id,
        ]);

        $submitter = app(BundleRegistrationSubmitter::class);

        try {
            $submitter->submit($form, $event, $leader, ['full_name' => 'Leader'], $memberRows, $memberEmails, false);
            $this->fail('Expected ValidationException for registered participant account.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team_member_emails.5', $exception->errors());
        }

        $this->assertSame(1, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_bundle_first_duplicate_index_wins(): void
    {
        $leader       = User::factory()->create();
        $memberEmails = [];
        $memberRows   = [];

        for ($i = 0; $i < 10; $i++) {
            $memberEmails[] = 'bundle-first-dup-'.$i.'-'.uniqid().'@example.com';
            $memberRows[]   = ['full_name' => 'Member '.$i];
        }

        $event = $this->openEvent();
        $form  = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'bundle',
                'max_team_size'     => 11,
            ],
        ]);

        foreach ([3, 8] as $index) {
            FormAnswer::factory()->create([
                'form_id' => $form->id,
                'user_id' => null,
                'invited_email' => $memberEmails[$index],
                'registration_role' => RegistrationRole::Member,
                'member_confirmation_status' => MemberConfirmationStatus::Accepted,
                'answers' => ['full_name' => 'Existing Guest '.$index],
            ]);
        }

        $submitter = app(BundleRegistrationSubmitter::class);

        try {
            $submitter->submit($form, $event, $leader, ['full_name' => 'Leader'], $memberRows, $memberEmails, false);
            $this->fail('Expected ValidationException for the first duplicate index.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team_member_emails.3', $exception->errors());
        }

        $this->assertSame(2, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_bundle_rejected_submission_allows_re_registration(): void
    {
        $leader       = User::factory()->create();
        $memberEmails = [];
        $memberRows   = [];

        for ($i = 0; $i < 10; $i++) {
            $memberEmails[] = 'bundle-reregister-'.$i.'-'.uniqid().'@example.com';
            $memberRows[]   = ['full_name' => 'Member '.$i];
        }

        $event = $this->openEvent();
        $form  = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'bundle',
                'max_team_size'     => 11,
            ],
        ]);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'invited_email' => $memberEmails[2],
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'review_status' => FormAnswerReviewStatus::Rejected,
            'answers' => ['full_name' => 'Rejected Guest'],
        ]);

        $submitter = app(BundleRegistrationSubmitter::class);

        $submitter->submit($form, $event, $leader, ['full_name' => 'Leader'], $memberRows, $memberEmails, false);

        $this->assertSame(12, FormAnswer::query()->where('form_id', $form->id)->count());
        $this->assertSame(11, FormAnswer::query()->where('form_id', $form->id)->where('is_active', 1)->count());
    }

    public function test_bundle_checker_query_count_is_constant(): void
    {
        $event = $this->openEvent();
        $form  = $this->openForm($event);

        $checker = app(BundleGuestDuplicateChecker::class);

        $counts = [];

        // Direct-checker invariance guard: unlike the submit-level shape asserts,
        // this catches any per-email query even if the submit path changes. A
        // future batched user lookup in the creation loop adds one more
        // LOWER(email) IN to the submit path, not to this checker.
        foreach ([2, 10] as $emailCount) {
            $emails = [];

            for ($i = 0; $i < $emailCount; $i++) {
                $emails[] = 'checker-'.$emailCount.'-'.$i.'-'.uniqid().'@example.com';
            }

            DB::enableQueryLog();
            DB::flushQueryLog();

            $checker->assertEmailsAvailableForForm($form, $emails);

            $counts[$emailCount] = count(DB::getQueryLog());

            DB::disableQueryLog();
        }

        $this->assertSame(2, $counts[2]);
        $this->assertSame(2, $counts[10]);
    }

    public function test_bundle_email_registered_on_another_form_does_not_block(): void
    {
        $leader       = User::factory()->create();
        $memberEmails = [];
        $memberRows   = [];

        for ($i = 0; $i < 10; $i++) {
            $memberEmails[] = 'bundle-other-form-'.$i.'-'.uniqid().'@example.com';
            $memberRows[]   = ['full_name' => 'Member '.$i];
        }

        $event = $this->openEvent();
        $form  = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'bundle',
                'max_team_size'     => 11,
            ],
        ]);
        $otherForm = $this->openForm($event);

        FormAnswer::factory()->create([
            'form_id' => $otherForm->id,
            'user_id' => null,
            'invited_email' => $memberEmails[3],
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'answers' => ['full_name' => 'Other Form Guest'],
        ]);

        $submitter = app(BundleRegistrationSubmitter::class);

        $submitter->submit($form, $event, $leader, ['full_name' => 'Leader'], $memberRows, $memberEmails, false);

        $this->assertSame(11, FormAnswer::query()->where('form_id', $form->id)->where('is_active', 1)->count());
        $this->assertSame(1, FormAnswer::query()->where('form_id', $otherForm->id)->count());
    }

    public function test_bundle_rejected_member_invitation_allows_re_registration(): void
    {
        $leader       = User::factory()->create();
        $memberEmails = [];
        $memberRows   = [];

        for ($i = 0; $i < 10; $i++) {
            $memberEmails[] = 'bundle-rejected-member-'.$i.'-'.uniqid().'@example.com';
            $memberRows[]   = ['full_name' => 'Member '.$i];
        }

        $event = $this->openEvent();
        $form  = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'bundle',
                'max_team_size'     => 11,
            ],
        ]);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'invited_email' => $memberEmails[2],
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Rejected,
            'answers' => ['full_name' => 'Rejected Member'],
        ]);

        $submitter = app(BundleRegistrationSubmitter::class);

        $submitter->submit($form, $event, $leader, ['full_name' => 'Leader'], $memberRows, $memberEmails, false);

        $this->assertSame(12, FormAnswer::query()->where('form_id', $form->id)->count());
        $this->assertSame(11, FormAnswer::query()->where('form_id', $form->id)->where('is_active', 1)->count());
    }

    public function test_bundle_expired_member_invitation_allows_re_registration(): void
    {
        $leader       = User::factory()->create();
        $memberEmails = [];
        $memberRows   = [];

        for ($i = 0; $i < 10; $i++) {
            $memberEmails[] = 'bundle-expired-member-'.$i.'-'.uniqid().'@example.com';
            $memberRows[]   = ['full_name' => 'Member '.$i];
        }

        $event = $this->openEvent();
        $form  = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'bundle',
                'max_team_size'     => 11,
            ],
        ]);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'invited_email' => $memberEmails[4],
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Expired,
            'answers' => ['full_name' => 'Expired Member'],
        ]);

        $submitter = app(BundleRegistrationSubmitter::class);

        $submitter->submit($form, $event, $leader, ['full_name' => 'Leader'], $memberRows, $memberEmails, false);

        $this->assertSame(12, FormAnswer::query()->where('form_id', $form->id)->count());
        $this->assertSame(11, FormAnswer::query()->where('form_id', $form->id)->where('is_active', 1)->count());
    }

    public function test_empty_email_values_are_skipped_without_queries(): void
    {
        $event = $this->openEvent();
        $form  = $this->openForm($event);

        $checker = app(BundleGuestDuplicateChecker::class);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $checker->assertEmailsAvailableForForm($form, ['', '  ']);

        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame(0, $queryCount);
        $this->assertSame(0, FormAnswer::query()->where('form_id', $form->id)->count());
    }
}
