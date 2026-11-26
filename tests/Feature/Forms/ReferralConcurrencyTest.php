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
use App\Services\Form\ReferralRedemptionContext;
use App\Services\Form\ReferralRedemptionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Bukti ketahanan referral terhadap beban dan interleaving: throttle submit
 * dipakai bersama (tidak ada limiter baru) dan penukaran kode berada di dalam
 * transaksi submission sehingga kegagalan kuota tidak menyisakan jawaban.
 */
class ReferralConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Mail::fake();
    }

    public function test_referral_submission_is_rate_limited_without_writing_a_redemption(): void
    {
        [$event, $form] = $this->singleForm();
        $this->code($form, 'UDIN-AB12');
        $member = $this->member();
        $path = $this->submitPath($event, $form);

        // Habiskan bucket dengan payload tidak valid: ditolak lebih awal, tanpa menulis baris.
        $this->actingAs($member);
        for ($attempt = 1; $attempt <= (int) config('events.forms.submit_rate_limit_per_minute'); $attempt++) {
            $this->post($path, [])->assertRedirect();
        }

        $this->actingAs($member)
            ->post($path, ['full_name' => 'Peserta', 'referral_code' => 'UDIN-AB12'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertSame(0, FormAnswer::query()->where('form_id', $form->id)->count());
        $this->assertSame(0, ReferralRedemption::query()->count());
    }

    public function test_code_creation_is_rate_limited_per_organizer(): void
    {
        config(['events.forms.referral.write_rate_limit_per_minute' => 2]);

        $owner = User::factory()->create();
        $owner->assignRole('admin');
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $form = Form::factory()->create(['event_id' => $event->id]);
        $path = route('dashboard.events.forms.referral-codes.store', ['event' => $event, 'form' => $form]);

        $this->actingAs($owner);
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->post($path, ['prefix' => 'U'])->assertSessionHasErrors('prefix');
        }

        $this->actingAs($owner)
            ->post($path, [
                'attribution_mode' => 'name',
                'attribution_name' => 'Komunitas',
                'prefix' => 'UDIN',
                'generation_mode' => 'manual',
                'body' => 'AB12',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertSame(0, ReferralCode::query()->count());
    }

    public function test_exhausted_quota_rolls_back_the_answer_row(): void
    {
        [$event, $form] = $this->singleForm();
        $code = $this->code($form, 'UDIN-AB12', ['usage_limit' => 1]);
        $this->redeemDirectly($code, $form, $this->directoryAnswer($form));

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Peserta Kedua',
                'referral_code' => 'UDIN-AB12',
            ])
            ->assertSessionHasErrors('referral_code');

        // Jawaban dari request yang gagal tidak boleh tertinggal (transaksi sama).
        $this->assertSame(1, FormAnswer::query()->where('form_id', $form->id)->count());
        $this->assertSame(1, ReferralRedemption::query()->count());
        $this->assertSame(1, $code->fresh()->usage_count);
    }

    public function test_team_submission_rolls_back_all_rows_when_the_code_is_exhausted(): void
    {
        [$leader, $teammate, $event, $form] = $this->teamForm();
        $code = $this->code($form, 'UDIN-AB12', ['usage_limit' => 1]);
        $this->redeemDirectly($code, $form, $this->directoryAnswer($form));

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Ketua',
                'team_member_emails' => [$teammate->email],
                'referral_code' => 'UDIN-AB12',
            ])
            ->assertSessionHasErrors('referral_code');

        $this->assertSame(1, FormAnswer::query()->where('form_id', $form->id)->count());
        $this->assertSame(1, $code->fresh()->usage_count);
    }

    public function test_resolve_rereads_the_code_and_rejects_state_changed_by_another_worker(): void
    {
        [, $form] = $this->singleForm();
        $code = $this->code($form, 'UDIN-AB12', ['usage_limit' => 1]);

        // Worker lain keburu memakai kuota terakhir setelah model ini dibaca.
        $this->assertSame(0, $code->usage_count);
        ReferralCode::query()->whereKey($code->id)->update(['usage_count' => 1]);

        $this->expectException(ValidationException::class);

        app(ReferralRedemptionService::class)->resolveLocked($form->fresh(), 'UDIN-AB12');
    }

    public function test_second_redemption_for_the_same_unit_is_rejected_and_counted_once(): void
    {
        [, $form] = $this->singleForm();
        $code = $this->code($form, 'UDIN-AB12');
        $answer = $this->directoryAnswer($form);
        $service = app(ReferralRedemptionService::class);

        $this->redeemDirectly($code, $form, $answer);

        try {
            $service->redeem(new ReferralRedemptionContext(
                form: $form,
                code: $code->fresh(),
                answer: $answer,
                groupToken: null,
                participantName: 'Peserta',
                participantEmail: 'peserta@example.test',
            ));

            $this->fail('Redemption kedua pada unit yang sama harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('referral_code', $exception->errors());
        }

        $this->assertSame(1, ReferralRedemption::query()->count());
        $this->assertSame(1, $code->fresh()->usage_count);
    }

    private function redeemDirectly(ReferralCode $code, Form $form, FormAnswer $answer): void
    {
        app(ReferralRedemptionService::class)->redeem(new ReferralRedemptionContext(
            form: $form,
            code: $code,
            answer: $answer,
            groupToken: null,
            participantName: 'Peserta',
            participantEmail: 'peserta@example.test',
        ));
    }

    /** Jawaban milik user lain sebagai konsumen kuota pertama (bukan bagian dari request). */
    private function directoryAnswer(Form $form): FormAnswer
    {
        return FormAnswer::factory()->create([
            'form_id' => $form->id,
            'answers' => ['full_name' => 'Pemakai Pertama'],
        ]);
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

    /** @param array<string, mixed> $metadata */
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
    private function singleForm(): array
    {
        $event = $this->openEvent();
        $form = $this->openForm($event, [
            'purpose' => 'registration',
            'registration_mode' => 'single',
        ]);
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
