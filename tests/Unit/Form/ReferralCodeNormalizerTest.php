<?php

namespace Tests\Unit\Form;

use App\Services\Form\ReferralCodeNormalizer;
use Tests\TestCase;

class ReferralCodeNormalizerTest extends TestCase
{
    public function test_it_normalizes_lowercase_codes(): void
    {
        $this->assertSame('AB-12CD', ReferralCodeNormalizer::normalize('ab-12cd'));
        $this->assertSame('AB-12CD', ReferralCodeNormalizer::normalize("  ab-12cd\n"));
    }

    public function test_it_rejects_codes_without_the_fixed_separator(): void
    {
        $this->assertNull(ReferralCodeNormalizer::normalize('AB12CD'));
    }

    public function test_it_rejects_prefix_shorter_than_two_characters(): void
    {
        $this->assertNull(ReferralCodeNormalizer::normalize('A-1234'));
    }

    public function test_it_rejects_prefix_longer_than_twelve_characters(): void
    {
        $this->assertNull(ReferralCodeNormalizer::normalize('ABCDEFGHIJKLM-1234'));
    }

    public function test_it_rejects_body_outside_four_to_sixteen_characters(): void
    {
        $this->assertNull(ReferralCodeNormalizer::normalize('AB-123'));
        $this->assertNull(ReferralCodeNormalizer::normalize('AB-12345678901234567'));
    }

    public function test_it_rejects_characters_outside_uppercase_alphanumeric(): void
    {
        $this->assertNull(ReferralCodeNormalizer::normalize('AB 1234'));
        $this->assertNull(ReferralCodeNormalizer::normalize('AB-12_4'));
        $this->assertNull(ReferralCodeNormalizer::normalize('AÉ-1234'));
        $this->assertNull(ReferralCodeNormalizer::normalize('AB-1234-56'));
    }

    public function test_it_rejects_non_string_and_empty_input(): void
    {
        $this->assertNull(ReferralCodeNormalizer::normalize(null));
        $this->assertNull(ReferralCodeNormalizer::normalize(''));
        $this->assertNull(ReferralCodeNormalizer::normalize('   '));
    }

    public function test_it_splits_a_normalized_code(): void
    {
        $this->assertSame(['prefix' => 'AB', 'body' => '12CD'], ReferralCodeNormalizer::split('AB-12CD'));
    }

    public function test_it_builds_a_code_from_prefix_and_body(): void
    {
        $this->assertSame('AB-12CD', ReferralCodeNormalizer::codeFor('ab', '12cd'));
    }

    public function test_generated_bodies_are_unambiguous_and_well_formed(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $body = ReferralCodeNormalizer::generateBody();

            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $body);
        }
    }
}
