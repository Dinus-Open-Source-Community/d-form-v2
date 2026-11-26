<?php

namespace Tests\Feature\Forms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormPriceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_codes_carry_optional_discount_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('referral_codes', 'discount_type'));
        $this->assertTrue(Schema::hasColumn('referral_codes', 'discount_value'));
    }

    public function test_form_answers_carry_a_nullable_pricing_snapshot(): void
    {
        $this->assertTrue(Schema::hasColumn('form_answers', 'pricing'));
    }
}
