<?php

namespace Tests\Feature\Forms;

use App\Models\Event;
use App\Models\Form;
use App\Models\ReferralCode;
use App\Models\User;
use App\Services\Form\ReferralCodeService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralCodeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Event $event;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->owner = $this->organizer();
        $this->event = Event::factory()->create(['created_by' => $this->owner->id]);
        $this->form = Form::factory()->create(['event_id' => $this->event->id]);
    }

    public function test_owner_creates_a_manual_code(): void
    {
        $this->actingAs($this->owner)
            ->post($this->storePath(), $this->manualPayload(['prefix' => 'UDIN', 'body' => 'AB12']))
            ->assertRedirect();

        $code = ReferralCode::query()->sole();

        $this->assertSame('UDIN-AB12', $code->code);
        $this->assertSame('UDIN', $code->prefix);
        $this->assertSame('AB12', $code->body);
        $this->assertSame('manual', $code->generation_mode);
        $this->assertSame(0, $code->usage_count);
        $this->assertSame($this->owner->id, $code->created_by);
        $this->assertTrue($code->is_active);
    }

    public function test_random_generation_produces_a_well_formed_body(): void
    {
        $this->actingAs($this->owner)
            ->post($this->storePath(), [
                'attribution_mode' => 'name',
                'attribution_name' => 'Komunitas',
                'prefix' => 'UDIN',
                'generation_mode' => 'random',
            ])
            ->assertRedirect();

        $this->assertMatchesRegularExpression('/^UDIN-[A-HJ-NP-Z2-9]{8}$/', ReferralCode::query()->sole()->code);
    }

    public function test_lowercase_input_is_normalized(): void
    {
        $this->actingAs($this->owner)
            ->post($this->storePath(), $this->manualPayload(['prefix' => 'udin', 'body' => 'ab12']))
            ->assertRedirect();

        $this->assertSame('UDIN-AB12', ReferralCode::query()->sole()->code);
    }

    public function test_duplicate_body_in_the_same_form_is_rejected(): void
    {
        $this->createCode('UDIN-AB12');

        $this->actingAs($this->owner)
            ->post($this->storePath(), $this->manualPayload(['prefix' => 'UDIN', 'body' => 'AB12']))
            ->assertSessionHasErrors('body');

        $this->assertSame(1, ReferralCode::query()->count());
    }

    public function test_the_same_code_may_exist_on_another_form(): void
    {
        $this->createCode('UDIN-AB12');

        $otherEvent = Event::factory()->create(['created_by' => $this->owner->id]);
        $otherForm = Form::factory()->create(['event_id' => $otherEvent->id]);

        $this->actingAs($this->owner)
            ->post($this->storePath($otherEvent, $otherForm), $this->manualPayload(['prefix' => 'UDIN', 'body' => 'AB12']))
            ->assertRedirect();

        $this->assertSame(2, ReferralCode::query()->count());
    }

    public function test_attribution_is_required_and_mutually_exclusive(): void
    {
        $this->actingAs($this->owner)
            ->post($this->storePath(), [
                'attribution_mode' => 'name',
                'prefix' => 'UDIN',
                'generation_mode' => 'manual',
                'body' => 'AB12',
            ])
            ->assertSessionHasErrors('attribution_name');

        $this->actingAs($this->owner)
            ->post($this->storePath(), $this->manualPayload([
                'attribution_mode' => 'user',
                'assigned_user_id' => $this->owner->id,
                'attribution_name' => 'Dobel',
            ]))
            ->assertSessionHasErrors('attribution_name');
    }

    public function test_invalid_prefix_and_body_are_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post($this->storePath(), $this->manualPayload(['prefix' => 'U', 'body' => 'AB12']))
            ->assertSessionHasErrors('prefix');

        $this->actingAs($this->owner)
            ->post($this->storePath(), $this->manualPayload(['prefix' => 'UDIN', 'body' => 'A-B']))
            ->assertSessionHasErrors('body');
    }

    public function test_zero_usage_limit_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post($this->storePath(), $this->manualPayload(['usage_limit' => 0]))
            ->assertSessionHasErrors('usage_limit');
    }

    public function test_unrelated_organizer_cannot_manage_codes(): void
    {
        $stranger = $this->organizer();

        $this->actingAs($stranger)
            ->post($this->storePath(), $this->manualPayload())
            ->assertForbidden();

        $this->actingAs($stranger)
            ->patch(route('dashboard.events.forms.referral.update', ['event' => $this->event, 'form' => $this->form]), ['enabled' => true])
            ->assertForbidden();
    }

    public function test_super_admin_can_manage_codes(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->actingAs($superAdmin)
            ->post($this->storePath(), $this->manualPayload())
            ->assertRedirect();

        $this->assertSame(1, ReferralCode::query()->count());
    }

    public function test_unused_code_attribution_can_be_changed(): void
    {
        $code = $this->createCode('UDIN-AB12');

        $this->actingAs($this->owner)
            ->patch($this->updatePath($code), [
                'attribution_mode' => 'name',
                'attribution_name' => 'Nama Baru',
            ])
            ->assertRedirect();

        $this->assertSame('Nama Baru', $code->fresh()->attribution_name);
    }

    public function test_used_code_identity_and_attribution_are_locked(): void
    {
        $code = $this->createCode('UDIN-AB12');
        $code->forceFill(['usage_count' => 1])->save();

        $this->actingAs($this->owner)
            ->patch($this->updatePath($code), [
                'attribution_mode' => 'name',
                'attribution_name' => 'Nama Baru',
            ])
            ->assertSessionHasErrors('attribution_name');

        $this->actingAs($this->owner)
            ->patch($this->updatePath($code), ['prefix' => 'LAIN', 'body' => 'ZZ99'])
            ->assertSessionHasErrors('body');

        $fresh = $code->fresh();
        $this->assertSame('UDIN-AB12', $fresh->code);
        $this->assertSame('Komunitas Udinus', $fresh->attribution_name);
    }

    public function test_owner_creates_a_code_with_a_percentage_discount(): void
    {
        $this->actingAs($this->owner)
            ->post($this->storePath(), $this->manualPayload([
                'discount_type' => 'percentage',
                'discount_value' => 20,
            ]))
            ->assertRedirect();

        $code = ReferralCode::query()->sole();

        $this->assertSame('percentage', $code->discount_type?->value);
        $this->assertSame(20, $code->discount_value);
    }

    public function test_owner_creates_a_code_with_a_fixed_discount(): void
    {
        $this->actingAs($this->owner)
            ->post($this->storePath(), $this->manualPayload([
                'discount_type' => 'fixed',
                'discount_value' => 25_000,
            ]))
            ->assertRedirect();

        $code = ReferralCode::query()->sole();

        $this->assertSame('fixed', $code->discount_type?->value);
        $this->assertSame(25_000, $code->discount_value);
    }

    public function test_discount_type_and_value_must_be_sent_together(): void
    {
        $this->actingAs($this->owner)
            ->post($this->storePath(), $this->manualPayload(['discount_type' => 'percentage']))
            ->assertSessionHasErrors('discount_value');

        $this->actingAs($this->owner)
            ->post($this->storePath(), $this->manualPayload(['discount_value' => 20_000]))
            ->assertSessionHasErrors('discount_value');
    }

    public function test_percentage_discount_stays_within_one_hundred(): void
    {
        foreach ([0, 101] as $value) {
            $this->actingAs($this->owner)
                ->post($this->storePath(), $this->manualPayload([
                    'discount_type' => 'percentage',
                    'discount_value' => $value,
                ]))
                ->assertSessionHasErrors('discount_value');
        }
    }

    public function test_discount_can_be_changed_after_the_code_is_used(): void
    {
        $code = $this->createCode('UDIN-AB12', [
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'usage_count' => 1,
        ]);

        $this->actingAs($this->owner)
            ->patch($this->updatePath($code), [
                'discount_type' => 'fixed',
                'discount_value' => 25_000,
            ])
            ->assertRedirect();

        $fresh = $code->fresh();

        $this->assertSame('fixed', $fresh->discount_type?->value);
        $this->assertSame(25_000, $fresh->discount_value);
    }

    public function test_code_without_discount_stays_without_one(): void
    {
        $code = $this->createCode('UDIN-AB12');

        $this->assertNull($code->discount_type);
        $this->assertNull($code->discount_value);
    }

    public function test_payload_exposes_the_discount(): void
    {
        $code = $this->createCode('UDIN-AB12', [
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);

        $payload = app(ReferralCodeService::class)->payload($code);

        $this->assertSame('percentage', $payload['discount_type']);
        $this->assertSame(20, $payload['discount_value']);
    }

    public function test_usage_limit_cannot_drop_below_usage_count(): void
    {
        $code = $this->createCode('UDIN-AB12', ['usage_limit' => 5]);
        $code->forceFill(['usage_count' => 3])->save();

        $this->actingAs($this->owner)
            ->patch($this->updatePath($code), ['usage_limit' => 2])
            ->assertSessionHasErrors('usage_limit');

        $this->actingAs($this->owner)
            ->patch($this->updatePath($code), ['usage_limit' => 3])
            ->assertRedirect();

        $this->assertSame(3, $code->fresh()->usage_limit);
    }

    public function test_delete_is_allowed_only_for_unused_codes(): void
    {
        $unused = $this->createCode('UDIN-AB12');
        $used = $this->createCode('UDIN-CD34', ['usage_count' => 1]);

        $this->actingAs($this->owner)
            ->delete($this->deletePath($used))
            ->assertStatus(409);

        $this->actingAs($this->owner)
            ->delete($this->deletePath($unused))
            ->assertRedirect();

        $this->assertSame(1, ReferralCode::query()->count());
    }

    public function test_referral_cannot_be_enabled_on_a_non_registration_form(): void
    {
        $event = Event::factory()->create(['created_by' => $this->owner->id]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['purpose' => 'other'],
        ]);

        $this->actingAs($this->owner)
            ->patch(route('dashboard.events.forms.referral.update', ['event' => $event, 'form' => $form]), ['enabled' => true])
            ->assertSessionHasErrors('enabled');

        $this->assertFalse($form->fresh()->referral_enabled);
    }

    public function test_referral_can_be_enabled_on_a_d_kelas_form(): void
    {
        $event = Event::factory()->create(['created_by' => $this->owner->id]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single'],
        ]);

        $this->actingAs($this->owner)
            ->patch(route('dashboard.events.forms.referral.update', ['event' => $event, 'form' => $form]), ['enabled' => true])
            ->assertRedirect();

        $this->assertTrue($form->fresh()->referral_enabled);
    }

    public function test_owner_toggles_the_form_feature(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('dashboard.events.forms.referral.update', ['event' => $this->event, 'form' => $this->form]), ['enabled' => true])
            ->assertRedirect();

        $this->assertTrue($this->form->fresh()->referral_enabled);
    }

    public function test_code_of_another_form_is_not_found(): void
    {
        $code = $this->createCode('UDIN-AB12');
        $otherForm = Form::factory()->create(['event_id' => $this->event->id]);

        $this->actingAs($this->owner)
            ->patch(route('dashboard.events.forms.referral-codes.update', [
                'event' => $this->event,
                'form' => $otherForm,
                'referralCode' => $code->id,
            ]), ['usage_limit' => 10])
            ->assertNotFound();
    }

    private function organizer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function manualPayload(array $overrides = []): array
    {
        return array_merge([
            'attribution_mode' => 'name',
            'attribution_name' => 'Komunitas Udinus',
            'prefix' => 'UDIN',
            'generation_mode' => 'manual',
            'body' => 'AB12',
            'is_active' => true,
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function createCode(string $code, array $overrides = []): ReferralCode
    {
        [$prefix, $body] = explode('-', $code);

        return ReferralCode::query()->create(array_merge([
            'form_id' => $this->form->id,
            'code' => $code,
            'prefix' => $prefix,
            'body' => $body,
            'generation_mode' => 'manual',
            'created_by' => $this->owner->id,
            'attribution_name' => 'Komunitas Udinus',
            'usage_limit' => null,
            'usage_count' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function storePath(?Event $event = null, ?Form $form = null): string
    {
        return route('dashboard.events.forms.referral-codes.store', [
            'event' => $event ?? $this->event,
            'form' => $form ?? $this->form,
        ]);
    }

    private function updatePath(ReferralCode $code): string
    {
        return route('dashboard.events.forms.referral-codes.update', [
            'event' => $this->event,
            'form' => $this->form,
            'referralCode' => $code->id,
        ]);
    }

    private function deletePath(ReferralCode $code): string
    {
        return route('dashboard.events.forms.referral-codes.destroy', [
            'event' => $this->event,
            'form' => $this->form,
            'referralCode' => $code->id,
        ]);
    }
}
