<?php

namespace Tests\Feature\Forms;

use App\Services\Form\FormPurposeRules;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FormPurposeRulesTest extends TestCase
{
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

    public function test_d_kelas_cannot_be_created_from_the_client(): void
    {
        $this->expectException(ValidationException::class);

        FormPurposeRules::apply(['purpose' => 'd_kelas']);
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

    public function test_prefill_falls_back_to_registration_for_disabled_or_unknown_purpose(): void
    {
        $this->assertSame(['purpose' => 'registration', 'mode' => 'single'], FormPurposeRules::prefill('d_kelas', 'team'));
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
}
