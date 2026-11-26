<?php

namespace Tests\Feature\Forms;

use App\Services\Form\FormPurposeRules;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FormPurposeRulesTest extends TestCase
{
    public function test_guest_submissions_default_to_false(): void
    {
        $metadata = FormPurposeRules::apply(['purpose' => 'feedback']);

        $this->assertFalse($metadata['allow_guest_submissions']);
    }

    public function test_guest_submissions_are_preserved_for_eligible_purposes(): void
    {
        $metadata = FormPurposeRules::apply(['purpose' => 'feedback', 'allow_guest_submissions' => true]);

        $this->assertTrue($metadata['allow_guest_submissions']);
    }

    public function test_registration_and_d_kelas_cannot_enable_guest_submissions(): void
    {
        $registration = FormPurposeRules::apply(['purpose' => 'registration', 'allow_guest_submissions' => true]);
        $dKelas = FormPurposeRules::apply(['purpose' => 'd_kelas', 'allow_guest_submissions' => true], 'd_kelas');

        $this->assertFalse($registration['allow_guest_submissions']);
        $this->assertFalse($dKelas['allow_guest_submissions']);
    }

    public function test_guest_submissions_reject_non_boolean_values(): void
    {
        $this->expectException(ValidationException::class);

        FormPurposeRules::apply(['purpose' => 'feedback', 'allow_guest_submissions' => 'true']);
    }

    public function test_registration_accepts_team_mode(): void
    {
        $metadata = FormPurposeRules::apply(['purpose' => 'registration', 'registration_mode' => 'team']);

        $this->assertSame('registration', $metadata['purpose']);
        $this->assertSame('team', $metadata['registration_mode']);
    }

    public function test_non_registration_is_rejected_when_mode_is_not_single(): void
    {
        $this->expectException(ValidationException::class);

        FormPurposeRules::apply(['purpose' => 'answer_collection', 'registration_mode' => 'bundle']);
    }

    public function test_unknown_purpose_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        FormPurposeRules::apply(['purpose' => 'sesuatu_yang_aneh']);
    }

    public function test_d_kelas_accepts_single_bundle_and_team_modes(): void
    {
        foreach (['single', 'bundle', 'team'] as $mode) {
            $metadata = FormPurposeRules::apply([
                'purpose' => 'd_kelas',
                'registration_mode' => $mode,
            ]);

            $this->assertSame($mode, $metadata['registration_mode']);
        }
    }

    public function test_d_kelas_purpose_cannot_be_changed_later(): void
    {
        $this->expectException(ValidationException::class);

        FormPurposeRules::apply(['purpose' => 'feedback'], 'd_kelas');
    }

    public function test_d_kelas_stays_d_kelas(): void
    {
        $metadata = FormPurposeRules::apply(['purpose' => 'd_kelas'], 'd_kelas');

        $this->assertSame('d_kelas', $metadata['purpose']);
    }

    public function test_prefill_preserves_d_kelas_purpose_and_mode(): void
    {
        $this->assertSame(['purpose' => 'd_kelas', 'mode' => 'team'], FormPurposeRules::prefill('d_kelas', 'team'));
    }

    public function test_prefill_falls_back_to_registration_for_unknown_purpose(): void
    {
        $this->assertSame(['purpose' => 'registration', 'mode' => 'single'], FormPurposeRules::prefill('apa_saja', 'team'));
    }

    public function test_prefill_keeps_valid_purpose_and_single_for_non_registration(): void
    {
        $this->assertSame(['purpose' => 'registration', 'mode' => 'bundle'], FormPurposeRules::prefill('registration', 'bundle'));
        $this->assertSame(['purpose' => 'feedback', 'mode' => 'single'], FormPurposeRules::prefill('feedback', 'team'));
    }

    public function test_missing_purpose_defaults_to_registration_mode_single(): void
    {
        $metadata = FormPurposeRules::apply(['purpose' => 'registration']);

        $this->assertSame('single', $metadata['registration_mode']);
    }

    public function test_purpose_cannot_change_after_the_form_has_submissions(): void
    {
        $this->expectException(ValidationException::class);

        FormPurposeRules::apply(['purpose' => 'feedback'], 'registration', true);
    }

    public function test_purpose_can_be_resaved_when_it_is_unchanged(): void
    {
        $metadata = FormPurposeRules::apply(['purpose' => 'registration', 'registration_mode' => 'single'], 'registration', true);

        $this->assertSame('registration', $metadata['purpose']);
    }

    public function test_non_registration_purposes_accept_two_total_submissions(): void
    {
        foreach (['answer_collection', 'feedback', 'other'] as $purpose) {
            $metadata = FormPurposeRules::apply([
                'purpose' => $purpose,
                'registration_mode' => 'single',
                'allow_multiple_submissions' => true,
                'max_submissions' => 2,
            ]);

            $this->assertTrue($metadata['allow_multiple_submissions']);
            $this->assertSame(2, $metadata['max_submissions']);
        }
    }

    public function test_enabled_non_registration_defaults_to_two_total_submissions(): void
    {
        $metadata = FormPurposeRules::apply([
            'purpose' => 'feedback',
            'registration_mode' => 'single',
            'allow_multiple_submissions' => true,
        ]);

        $this->assertTrue($metadata['allow_multiple_submissions']);
        $this->assertSame(2, $metadata['max_submissions']);
    }

    public function test_enabled_non_registration_rejects_less_than_two_total_submissions(): void
    {
        $this->expectException(ValidationException::class);

        FormPurposeRules::apply([
            'purpose' => 'feedback',
            'registration_mode' => 'single',
            'allow_multiple_submissions' => true,
            'max_submissions' => 1,
        ]);
    }

    public function test_repeatable_forms_reject_non_digit_or_out_of_range_submission_limits(): void
    {
        foreach (['2.5', '2abc', ' 2', '+2', '', 1, 10001] as $maxSubmissions) {
            try {
                FormPurposeRules::apply([
                    'purpose' => 'feedback',
                    'registration_mode' => 'single',
                    'allow_multiple_submissions' => true,
                    'max_submissions' => $maxSubmissions,
                ]);
                $this->fail("Expected validation failure for submission limit: {$maxSubmissions}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_repeatable_forms_normalize_digit_string_boundaries_to_integers(): void
    {
        foreach (['2' => 2, '10000' => 10000] as $rawValue => $expectedValue) {
            $metadata = FormPurposeRules::apply([
                'purpose' => 'feedback',
                'registration_mode' => 'single',
                'allow_multiple_submissions' => true,
                'max_submissions' => $rawValue,
            ]);

            $this->assertSame($expectedValue, $metadata['max_submissions']);
        }
    }

    public function test_registration_and_d_kelas_cannot_enable_multiple_submissions(): void
    {
        $registration = FormPurposeRules::apply([
            'purpose' => 'registration',
            'registration_mode' => 'single',
            'allow_multiple_submissions' => true,
            'max_submissions' => 2,
        ]);
        $dKelas = FormPurposeRules::apply([
            'purpose' => 'd_kelas',
            'registration_mode' => 'single',
            'allow_multiple_submissions' => true,
            'max_submissions' => 2,
        ], 'd_kelas');

        $this->assertFalse($registration['allow_multiple_submissions']);
        $this->assertSame(1, $registration['max_submissions']);
        $this->assertFalse($dKelas['allow_multiple_submissions']);
        $this->assertSame(1, $dKelas['max_submissions']);
    }
}
