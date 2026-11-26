<?php

namespace Tests\Feature\Forms;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\ReferralCode;
use App\Models\ReferralRedemption;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_is_disabled_by_default(): void
    {
        $form = Form::factory()->create();

        $this->assertFalse($form->fresh()->referral_enabled);
    }

    public function test_code_is_unique_per_form(): void
    {
        $form = Form::factory()->create();
        $otherForm = Form::factory()->create();

        $this->code($form, 'AB-12CD');
        $this->code($otherForm, 'AB-12CD');

        $this->expectException(QueryException::class);
        $this->code($form, 'AB-12CD');
    }

    public function test_form_force_delete_cascades_codes_and_redemptions(): void
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $code = $this->code($form, 'AB-12CD');
        $answer = FormAnswer::factory()->create(['form_id' => $form->id]);
        $this->redemption($code, $form, $answer);

        // `form_answers.form_id` sengaja RESTRICT: purge aplikasi menghapus jawaban
        // lebih dulu (EventService::forceDelete). Test ini mengikuti urutan itu.
        $answer->delete();
        $form->forceDelete();

        $this->assertSame(0, ReferralCode::query()->count());
        $this->assertSame(0, ReferralRedemption::query()->count());
    }

    public function test_one_answer_has_at_most_one_redemption(): void
    {
        $form = Form::factory()->create();
        $code = $this->code($form, 'AB-12CD');
        $answer = FormAnswer::factory()->create(['form_id' => $form->id]);

        $this->redemption($code, $form, $answer);

        $this->expectException(QueryException::class);
        $this->redemption($code, $form, $answer);
    }

    public function test_group_rows_may_omit_the_answer_reference(): void
    {
        $form = Form::factory()->create();
        $code = $this->code($form, 'AB-12CD');

        $this->redemption($code, $form, null, 'GROUPAAA');
        $this->redemption($code, $form, null, 'GROUPBBB');

        $this->assertSame(2, ReferralRedemption::query()->count());
    }

    public function test_display_code_is_the_stored_prefix_and_body(): void
    {
        $form = Form::factory()->create();
        $code = $this->code($form, 'AB-12CD');

        $this->assertSame('AB', $code->prefix);
        $this->assertSame('12CD', $code->body);
        $this->assertSame('AB-12CD', $code->code);
    }

    private function code(Form $form, string $code): ReferralCode
    {
        [$prefix, $body] = explode('-', $code);

        return ReferralCode::query()->create([
            'form_id' => $form->id,
            'code' => $code,
            'prefix' => $prefix,
            'body' => $body,
            'generation_mode' => 'manual',
            'attribution_name' => 'Komunitas Udinus',
            'usage_limit' => null,
            'usage_count' => 0,
            'is_active' => true,
        ]);
    }

    private function redemption(
        ReferralCode $code,
        Form $form,
        ?FormAnswer $answer,
        ?string $groupToken = null,
    ): ReferralRedemption {
        return ReferralRedemption::query()->create([
            'referral_code_id' => $code->id,
            'form_id' => $form->id,
            'form_answer_id' => $answer?->id,
            'group_token' => $groupToken,
            'user_id' => $answer?->user_id,
            'participant_snapshot' => ['name' => 'Peserta', 'email' => 'peserta@example.test', 'members' => []],
            'attribution_snapshot' => ['attribution_name' => 'Komunitas Udinus'],
            'redeemed_at' => now(),
            'status' => ReferralRedemption::STATUS_VALID,
        ]);
    }
}
