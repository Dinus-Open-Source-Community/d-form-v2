<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\ReferralCode;
use App\Models\ReferralRedemption;
use App\Models\User;
use App\Services\Event\EventService;
use App\Services\Form\PublicFormFillPayload;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReferralRedemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Mail::fake();
    }

    public function test_single_submission_redeems_a_valid_code(): void
    {
        [$event, $form] = $this->singleForm();
        $code = $this->code($form, 'UDIN-AB12');
        $member = $this->member();

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta', 'referral_code' => 'udin-ab12'])
            ->assertRedirect();

        $answer = FormAnswer::query()->where('form_id', $form->id)->sole();
        $redemption = ReferralRedemption::query()->sole();

        $this->assertSame($answer->id, $redemption->form_answer_id);
        $this->assertSame($code->id, $redemption->referral_code_id);
        $this->assertSame(ReferralRedemption::STATUS_VALID, $redemption->status);
        $this->assertSame($member->email, $redemption->participant_snapshot['email']);
        $this->assertSame('Komunitas Udinus', $redemption->attribution_snapshot['attribution_name']);
        $this->assertSame(1, $code->fresh()->usage_count);
    }

    public function test_unknown_code_rejects_the_submission(): void
    {
        [$event, $form] = $this->singleForm();

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta', 'referral_code' => 'UDIN-ZZ99'])
            ->assertSessionHasErrors('referral_code');

        $this->assertDatabaseMissing('form_answers', ['form_id' => $form->id]);
    }

    public function test_disabled_feature_rejects_even_a_valid_code(): void
    {
        [$event, $form] = $this->singleForm(['referral_enabled' => false]);
        $this->code($form, 'UDIN-AB12');

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta', 'referral_code' => 'UDIN-AB12'])
            ->assertSessionHasErrors('referral_code');

        $this->assertSame(0, ReferralRedemption::query()->count());
    }

    public function test_inactive_code_is_rejected(): void
    {
        [$event, $form] = $this->singleForm();
        $this->code($form, 'UDIN-AB12', ['is_active' => false]);

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta', 'referral_code' => 'UDIN-AB12'])
            ->assertSessionHasErrors('referral_code');
    }

    public function test_code_outside_its_window_is_rejected(): void
    {
        [$event, $form] = $this->singleForm();
        $this->code($form, 'UDIN-AB12', ['starts_at' => now()->addDay()]);

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta', 'referral_code' => 'UDIN-AB12'])
            ->assertSessionHasErrors('referral_code');

        $this->code($form, 'UDIN-CD34', ['expires_at' => now()->subMinute()]);

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta', 'referral_code' => 'UDIN-CD34'])
            ->assertSessionHasErrors('referral_code');
    }

    public function test_usage_limit_is_enforced_across_users(): void
    {
        [$event, $form] = $this->singleForm();
        $code = $this->code($form, 'UDIN-AB12', ['usage_limit' => 1]);

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Pertama', 'referral_code' => 'UDIN-AB12'])
            ->assertRedirect();

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Kedua', 'referral_code' => 'UDIN-AB12'])
            ->assertSessionHasErrors('referral_code');

        $this->assertSame(1, ReferralRedemption::query()->count());
        $this->assertSame(1, $code->fresh()->usage_count);
    }

    public function test_d_kelas_submission_redeems_a_valid_code(): void
    {
        $event = $this->openEvent();
        $form = $this->openForm($event, [
            'purpose' => 'd_kelas',
            'registration_mode' => 'single',
        ]);
        $this->textField($form);
        $code = $this->code($form, 'UDIN-AB12');

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta', 'referral_code' => 'UDIN-AB12'])
            ->assertRedirect();

        $answer = FormAnswer::query()->where('form_id', $form->id)->sole();
        $redemption = ReferralRedemption::query()->sole();

        $this->assertSame($answer->id, $redemption->form_answer_id);
        $this->assertSame($code->id, $redemption->referral_code_id);
        $this->assertSame(1, $code->fresh()->usage_count);
    }

    public function test_team_submission_redeems_once_on_the_leader(): void
    {
        [$leader, $teammate, $event, $form] = $this->teamForm();
        $this->code($form, 'UDIN-AB12');

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Ketua',
                'team_member_emails' => [$teammate->email],
                'referral_code' => 'UDIN-AB12',
            ])
            ->assertRedirect();

        $leaderRow = FormAnswer::query()->where('form_id', $form->id)->where('user_id', $leader->id)->sole();
        $redemption = ReferralRedemption::query()->sole();

        $this->assertSame($leaderRow->id, $redemption->form_answer_id);
        $this->assertNull($redemption->group_token);
        $this->assertSame(2, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_bundle_submission_redeems_once_with_the_group_token(): void
    {
        [$leader, $event, $form] = $this->bundleForm(['guest@example.test']);
        $this->code($form, 'UDIN-AB12');

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Ketua',
                'bundle__full_name__0' => 'Anggota',
                'team_member_emails' => ['guest@example.test'],
                'referral_code' => 'UDIN-AB12',
            ])
            ->assertRedirect();

        $leaderRow = FormAnswer::query()->where('form_id', $form->id)->where('user_id', $leader->id)->sole();
        $redemption = ReferralRedemption::query()->sole();

        $this->assertSame($leaderRow->id, $redemption->form_answer_id);
        $this->assertNotNull($redemption->group_token);
        $this->assertSame($leaderRow->group_token, $redemption->group_token);
    }

    public function test_non_registration_form_hides_the_input_and_never_redeems(): void
    {
        $event = $this->openEvent();
        $form = $this->openForm($event, ['purpose' => 'other']);
        $form->forceFill(['referral_enabled' => true])->save();
        $this->textField($form);
        $code = $this->code($form, 'UDIN-AB12');

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta', 'referral_code' => 'UDIN-AB12'])
            ->assertRedirect();

        $this->assertSame(0, ReferralRedemption::query()->count());
        $this->assertSame(0, $code->fresh()->usage_count);
        // Halaman fill juga tidak menampilkan input karena purpose bukan pendaftaran.
        $this->assertFalse(PublicFormFillPayload::build($event, $form)['form']['referral_enabled']);
    }

    public function test_deleted_assigned_user_keeps_the_redemption_snapshot(): void
    {
        [$event, $form] = $this->singleForm();
        $assigned = User::factory()->create(['name' => 'Mitra Awal']);
        $code = $this->code($form, 'UDIN-AB12', ['assigned_user_id' => $assigned->id, 'attribution_name' => null]);

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta', 'referral_code' => 'UDIN-AB12'])
            ->assertRedirect();

        $assigned->forceDelete();

        $this->assertNull($code->fresh()->assigned_user_id);
        $this->assertSame('Mitra Awal', ReferralRedemption::query()->sole()->attribution_snapshot['assigned_name']);
    }

    public function test_event_purge_removes_referral_rows(): void
    {
        [$event, $form] = $this->singleForm();
        $this->code($form, 'UDIN-AB12');

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta', 'referral_code' => 'UDIN-AB12'])
            ->assertRedirect();

        $event->delete();
        app(EventService::class)->forceDelete($event);

        $this->assertSame(0, ReferralCode::query()->count());
        $this->assertSame(0, ReferralRedemption::query()->count());
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
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
            'referral_enabled' => true,
            'metadata' => $metadata,
        ]);
    }

    /** @return array{0: Event, 1: Form} */
    private function singleForm(array $overrides = []): array
    {
        $event = $this->openEvent();
        $form = $this->openForm($event, [
            'purpose' => 'registration',
            'registration_mode' => 'single',
        ]);
        $form->forceFill($overrides)->save();
        $this->textField($form);

        return [$event, $form];
    }

    /** @return array{0: User, 1: User, 2: Event, 3: Form} */
    private function teamForm(): array
    {
        $leader = $this->member();
        $teammate = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event, [
            'purpose' => 'registration',
            'registration_mode' => 'team',
            'team_size' => 2,
        ]);
        $this->textField($form);

        return [$leader, $teammate, $event, $form];
    }

    /** @param list<string> $guestEmails @return array{0: User, 1: Event, 2: Form} */
    private function bundleForm(array $guestEmails): array
    {
        $leader = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event, [
            'purpose' => 'registration',
            'registration_mode' => 'bundle',
            'max_team_size' => count($guestEmails) + 1,
        ]);
        FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => 'full_name',
            'label' => 'Full Name',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => ['required' => true], 'duplicatable' => true],
        ]);

        return [$leader, $event, $form];
    }

    private function textField(Form $form): FormField
    {
        return FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => 'full_name',
            'label' => 'Full Name',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => ['required' => true]],
        ]);
    }

    private function submitPath(Event $event, Form $form): string
    {
        return route('dashboard.forms.submission', ['event' => $event, 'form' => $form], false);
    }

    /** @param array<string, mixed> $overrides */
    private function code(Form $form, string $code, array $overrides = []): ReferralCode
    {
        [$prefix, $body] = explode('-', $code);

        return ReferralCode::query()->create(array_merge([
            'form_id' => $form->id,
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
}
