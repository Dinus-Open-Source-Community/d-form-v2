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
use App\Models\FormField;
use App\Models\User;
use App\Services\Registration\BundleRegistrationSubmitter;
use App\Services\Registration\TeamRegistrationSubmitter;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * EP-04 AUDIT-P1-005 F1/F2/F3: email resolution and token generation stay in a
 * constant number of queries regardless of participant count.
 */
class RegistrationEmailLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function openEvent(): Event
    {
        return Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
            'quota' => 100,
            'registered_count' => 0,
        ]);
    }

    private function openForm(Event $event, array $metadata): Form
    {
        return Form::factory()->create([
            'event_id' => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
            'metadata' => $metadata,
        ]);
    }

    private function textField(Form $form): FormField
    {
        return FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => 'full_name',
            'label' => 'Full Name',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => ['required' => true], 'duplicatable' => true],
        ]);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }

    private function submitPath(Event $event, Form $form): string
    {
        return route('dashboard.forms.submission', ['event' => $event, 'form' => $form], false);
    }

    private function countQueries(string $pattern): int
    {
        $count = 0;

        foreach (DB::getQueryLog() as $entry) {
            if (preg_match($pattern, (string) ($entry['query'] ?? '')) === 1) {
                $count++;
            }
        }

        return $count;
    }

    public function test_team_submission_resolves_ten_member_emails_with_one_lookup_and_no_fresh(): void
    {
        $leader = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event, ['registration_mode' => 'team', 'team_size' => 11]);
        $this->textField($form);

        $emails = [];

        for ($i = 0; $i < 10; $i++) {
            // Mixed-case stored email must still resolve from the lowercase payload.
            $emails[] = User::factory()->create(['email' => 'Team.Member'.$i.'@Example.com'])->email;
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Leader',
                'team_member_emails' => array_map(static fn (string $email): string => strtolower($email), $emails),
            ])
            ->assertRedirect(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form], false));

        $emailLookups = $this->countQueries('/["`]?email_normalized["`]?\s+in\s*\(/i');
        $perRowFresh = $this->countQueries('/from ["`]?form_answers["`]? where ["`]?id["`]? = \?/i');

        DB::disableQueryLog();

        $this->assertSame(1, $emailLookups, 'Ten members must resolve with one lookup.');
        $this->assertSame(0, $perRowFresh, 'No per-row fresh() may remain.');
        $this->assertSame(11, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_team_submission_reports_the_first_unknown_member_index(): void
    {
        $leader = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event, ['registration_mode' => 'team', 'team_size' => 6]);
        $this->textField($form);

        $emails = [];

        for ($i = 0; $i < 5; $i++) {
            $emails[] = $i === 4 ? 'missing-four@example.com' : User::factory()->create()->email;
        }

        $emails[2] = 'missing-two@example.com';

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Leader',
                'team_member_emails' => $emails,
            ])
            ->assertSessionHasErrors('team_member_emails.2');

        $this->assertSame(0, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_team_submission_rejects_a_soft_deleted_member_account(): void
    {
        $leader = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event, ['registration_mode' => 'team', 'team_size' => 2]);
        $this->textField($form);

        $deleted = User::factory()->create(['email' => 'deleted-mate@example.com']);
        $deleted->delete();

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Leader',
                'team_member_emails' => ['deleted-mate@example.com'],
            ])
            ->assertSessionHasErrors('team_member_emails.0');

        $this->assertSame(0, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_bundle_links_registered_accounts_and_leaves_guests_null(): void
    {
        $leader = User::factory()->create();
        $event = $this->openEvent();
        $form = $this->openForm($event, ['registration_mode' => 'bundle', 'max_team_size' => 3]);

        $registered = User::factory()->create(['email' => 'bundle-account@example.com']);

        $emails = ['bundle-account@example.com', 'bundle-guest@example.com'];
        $rows = [['full_name' => 'Account'], ['full_name' => 'Guest']];

        $result = app(BundleRegistrationSubmitter::class)->submit(
            $form,
            $event,
            $leader,
            ['full_name' => 'Leader'],
            $rows,
            $emails,
            false,
        );

        $this->assertSame($registered->id, $result['members'][0]->user_id);
        $this->assertNull($result['members'][1]->user_id);
    }

    public function test_team_invitation_tokens_are_unique_and_generated_in_one_query(): void
    {
        $leader = User::factory()->create();
        $event = $this->openEvent();
        $form = $this->openForm($event, ['registration_mode' => 'team', 'team_size' => 11]);

        $members = [];

        for ($i = 0; $i < 10; $i++) {
            $members[] = User::factory()->create();
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        app(TeamRegistrationSubmitter::class)->submit($form, $event, $leader, ['full_name' => 'Leader'], $members, false);

        $tokenChecks = $this->countQueries('/["`]?invitation_token["`]?\s+in\s*\(/i');
        $perRowFresh = $this->countQueries('/from ["`]?form_answers["`]? where ["`]?id["`]? = \?/i');

        DB::disableQueryLog();

        $tokens = FormAnswer::query()
            ->where('form_id', $form->id)
            ->whereNotNull('invitation_token')
            ->pluck('invitation_token')
            ->all();

        $this->assertSame(1, $tokenChecks, 'Whole token batch must be validated with one query.');
        $this->assertSame(0, $perRowFresh, 'No per-row fresh() may remain.');
        $this->assertCount(10, $tokens);
        $this->assertCount(10, array_unique($tokens));
    }

    public function test_team_accent_variant_matches_legacy_semantics(): void
    {
        $stored = User::factory()->create(['email' => 'josé@example.com']);
        $legacyResolves = User::query()->whereRaw('LOWER(email) = ?', ['jose@example.com'])->exists();

        $leader = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event, ['registration_mode' => 'team', 'team_size' => 2]);
        $this->textField($form);

        $response = $this->actingAs($leader)->post($this->submitPath($event, $form), [
            'full_name' => 'Leader',
            'team_member_emails' => ['jose@example.com'],
        ]);

        if ($legacyResolves) {
            $response->assertRedirect(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form], false));
            $this->assertSame(
                (string) $stored->id,
                (string) FormAnswer::query()->where('form_id', $form->id)->where('registration_role', RegistrationRole::Member->value)->value('user_id'),
            );
        } else {
            $response->assertSessionHasErrors('team_member_emails.0');
        }
    }

    public function test_bundle_accent_variant_matches_legacy_semantics(): void
    {
        $stored = User::factory()->create(['email' => 'josé@example.com']);
        $legacyResolves = User::query()->whereRaw('LOWER(email) = ?', ['jose@example.com'])->exists();

        $leader = User::factory()->create();
        $event = $this->openEvent();
        $form = $this->openForm($event, ['registration_mode' => 'bundle', 'max_team_size' => 2]);

        $result = app(BundleRegistrationSubmitter::class)->submit(
            $form,
            $event,
            $leader,
            ['full_name' => 'Leader'],
            [['full_name' => 'Member']],
            ['jose@example.com'],
            false,
        );

        $expectedUserId = $legacyResolves ? (string) $stored->id : null;

        $this->assertSame($expectedUserId, $result['members'][0]->user_id);
    }

    public function test_email_resolution_is_deterministic_across_repeated_calls(): void
    {
        $first = User::factory()->create(['email' => 'DUP@example.com']);
        $second = User::factory()->create(['email' => 'Dup@example.com']);

        $expectedId = strcmp((string) $first->id, (string) $second->id) <= 0
            ? (string) $first->id
            : (string) $second->id;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $resolved = User::resolveByNormalizedEmails(['dup@example.com']);

            $this->assertArrayHasKey('dup@example.com', $resolved);
            $this->assertSame($expectedId, (string) $resolved['dup@example.com']->id);
        }
    }

    public function test_bundle_duplicate_key_violation_becomes_a_field_error(): void
    {
        $leader = User::factory()->create();
        $event = $this->openEvent();
        $form = $this->openForm($event, ['registration_mode' => 'bundle', 'max_team_size' => 2]);

        $existing = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'invited_email' => 'race@example.com',
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'review_status' => FormAnswerReviewStatus::Rejected,
            'answers' => ['full_name' => 'Existing'],
        ]);

        // Excluded from the duplicate checker, but it still occupies the active
        // (form_id, invited_email, is_active) unique slot.
        DB::table('form_answers')->where('id', $existing->id)->update(['is_active' => 1]);

        try {
            app(BundleRegistrationSubmitter::class)->submit(
                $form,
                $event,
                $leader,
                ['full_name' => 'Leader'],
                [['full_name' => 'Member']],
                ['race@example.com'],
                false,
            );
            $this->fail('Expected ValidationException for the colliding member row.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team_member_emails.0', $exception->errors());
            $this->assertSame(
                __('A participant with this email is already registered for this form.'),
                $exception->errors()['team_member_emails.0'][0],
            );
        }

        $this->assertSame(1, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_non_unique_query_errors_are_not_swallowed(): void
    {
        $unique = new QueryException(
            'sqlite',
            'insert into "form_answers" values (1)',
            [],
            new \PDOException('UNIQUE constraint failed: form_answers.form_id, form_answers.invited_email, form_answers.is_active', 23000),
        );

        try {
            TeamRegistrationSubmitter::rethrowParticipantWriteFailure($unique, 3);
            $this->fail('Expected ValidationException for the unique violation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team_member_emails.3', $exception->errors());
        }

        $other = new QueryException(
            'sqlite',
            'insert into "form_answers" values (1)',
            [],
            new \PDOException('no such table: form_answers', 1),
        );

        $this->expectException(QueryException::class);

        TeamRegistrationSubmitter::rethrowParticipantWriteFailure($other, 3);
    }
}
