<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\ReferralCode;
use App\Models\ReferralRedemption;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReferralReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Event $event;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('admin');
        $this->event = Event::factory()->create(['created_by' => $this->owner->id]);
        $this->form = Form::factory()->create([
            'event_id' => $this->event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'referral_enabled' => true,
        ]);
    }

    public function test_referral_sort_ascending_keeps_all_rows_visible(): void
    {
        $direct = $this->answer();
        $this->redeem($this->code('UDIN-AB12'), $direct);

        $this->answer();

        $this->actingAs($this->owner)
            ->get($this->showPath(null, 'asc'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 2)
                ->where('submissions.0.id', $direct->id)
                ->where('submissions.0.referral.code', 'UDIN-AB12')
                ->where('submissions.0.referral.unit', 'single')
                ->where('submissionsCount', 2)
                ->where('referral.sort', 'asc'));
    }

    public function test_referral_sort_desc_keeps_rows_without_referral(): void
    {
        $this->redeem($this->code('UDIN-AB12'), $this->answer());
        $plain = $this->answer();

        $this->actingAs($this->owner)
            ->get($this->showPath(null, 'desc'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 2)
                ->where('submissionsCount', 2)
                ->where('referral.sort', 'desc')
                ->where('submissions.1.id', $plain->id)
                ->where('submissions.1.referral', null));
    }

    public function test_without_parameter_every_row_is_listed(): void
    {
        $this->redeem($this->code('UDIN-AB12'), $this->answer());
        $this->answer();

        $this->actingAs($this->owner)
            ->get($this->showPath())
            ->assertInertia(fn (Assert $page) => $page->has('submissions', 2));
    }

    public function test_unknown_referral_query_does_not_filter_submissions(): void
    {
        $this->answer();

        $this->actingAs($this->owner)
            ->get($this->showPath('UDIN-ZZ99'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissionsCount', 1));
    }

    public function test_bundle_member_rows_follow_the_group_referral(): void
    {
        $code = $this->code('BNDL-AB12');
        $leader = $this->answer();
        $member = $this->answer([
            'user_id' => null,
            'invited_email' => 'member@example.test',
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'group_token' => 'GROUPAAA',
        ]);
        $leader->forceFill(['group_token' => 'GROUPAAA'])->save();
        $this->redeem($code, $leader, 'GROUPAAA');

        $this->actingAs($this->owner)
            ->get($this->showPath('BNDL-AB12'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 2)
                ->where('submissions', fn (Collection $rows): bool => $rows
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->all() === collect([$leader->id, $member->id])->sort()->values()->all()));
    }

    public function test_team_member_rows_follow_the_leader_referral(): void
    {
        $code = $this->code('TEAM-AB12');
        $leader = $this->answer(['registration_role' => RegistrationRole::Leader]);
        $member = $this->answer([
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'leader_form_answer_id' => $leader->id,
        ]);
        $this->redeem($code, $leader);

        $this->actingAs($this->owner)
            ->get($this->showPath('TEAM-AB12'))
            ->assertInertia(fn (Assert $page) => $page->has('submissions', 2));
    }

    public function test_attribution_uses_the_redemption_snapshot(): void
    {
        $assigned = User::factory()->create(['name' => 'Mitra Awal']);
        $code = $this->code('UDIN-AB12', ['assigned_user_id' => $assigned->id, 'attribution_name' => null]);
        $answer = $this->answer();
        $this->redeem($code, $answer);

        $assigned->forceFill(['name' => 'Nama Diubah'])->save();
        $code->forceFill(['attribution_name' => 'Diubah Manual'])->save();

        $this->actingAs($this->owner)
            ->get($this->showPath('UDIN-AB12'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('submissions.0.referral.attribution', 'Mitra Awal'));
    }

    public function test_bulk_selectable_ids_include_all_rows_when_sorted(): void
    {
        $code = $this->code('UDIN-AB12');
        $filtered = $this->answer();
        $this->redeem($code, $filtered);
        $unfiltered = $this->answer();

        $this->actingAs($this->owner)
            ->get($this->showPath(null, 'asc'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectableSubmissionIds', fn (Collection $ids): bool => $ids->all() === [$filtered->id, $unfiltered->id]
                    || $ids->all() === [$unfiltered->id, $filtered->id])
                ->where('bulkSelectableTotal', 2)
                ->where('submissions.0.id', $filtered->id));

        self::assertNotSame($filtered->id, $unfiltered->id);
    }

    public function test_disabled_referral_still_returns_all_rows_with_sort(): void
    {
        $this->form->forceFill(['referral_enabled' => false])->save();
        $first = $this->answer();
        $second = $this->answer();

        $this->actingAs($this->owner)
            ->get($this->showPath('UDIN-AB12', 'asc'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 2)
                ->where('submissionsCount', 2)
                ->where('referral.sort', 'asc'));

        self::assertNotSame($first->id, $second->id);
    }

    public function test_referral_payload_keeps_inactive_codes_in_management_list(): void
    {
        $this->code('OLD-AB12', ['is_active' => false]);

        $this->actingAs($this->owner)
            ->get($this->showPath())
            ->assertInertia(fn (Assert $page) => $page
                ->where('referral.codes', fn (Collection $options): bool => $options
                    ->pluck('code')
                    ->contains('OLD-AB12')));
    }

    private function showPath(?string $referral = null, ?string $sort = null): string
    {
        $query = ['tab' => 'jawaban'];

        if ($referral !== null) {
            $query['referral'] = $referral;
        }
        if ($sort !== null) {
            $query['referral_sort'] = $sort;
        }

        return route('dashboard.events.forms.show', [
            'event' => $this->event,
            'form' => $this->form,
            ...$query,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function answer(array $overrides = []): FormAnswer
    {
        return FormAnswer::factory()->create(array_merge([
            'form_id' => $this->form->id,
            'answers' => ['full_name' => 'Peserta'],
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function code(string $code, array $overrides = []): ReferralCode
    {
        [$prefix, $body] = explode('-', $code);

        return ReferralCode::query()->create(array_merge([
            'form_id' => $this->form->id,
            'code' => $code,
            'prefix' => $prefix,
            'body' => $body,
            'generation_mode' => 'manual',
            'attribution_name' => 'Komunitas Udinus',
            'usage_limit' => null,
            'usage_count' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function redeem(ReferralCode $code, FormAnswer $answer, ?string $groupToken = null): ReferralRedemption
    {
        $code->increment('usage_count');

        return ReferralRedemption::query()->create([
            'referral_code_id' => $code->id,
            'form_id' => $this->form->id,
            'form_answer_id' => $answer->id,
            'group_token' => $groupToken,
            'user_id' => $answer->user_id,
            'participant_snapshot' => ['name' => 'Peserta', 'email' => 'peserta@example.test', 'members' => []],
            'attribution_snapshot' => [
                'assigned_user_id' => $code->assigned_user_id,
                'assigned_name' => $code->assignedUser?->name,
                'attribution_name' => $code->attribution_name,
                'code' => $code->code,
            ],
            'redeemed_at' => now(),
            'status' => ReferralRedemption::STATUS_VALID,
        ]);
    }
}
