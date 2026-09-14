<?php

namespace Tests\Unit\Recruitment;

use App\Services\Recruitment\RecruitmentTrackingTokenGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecruitmentTrackingTokenGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_eight_character_uppercase_alphanumeric_token_with_letter_and_digit(): void
    {
        $generator = new RecruitmentTrackingTokenGenerator;

        for ($i = 0; $i < 50; $i++) {
            $token = $generator->generate();

            $this->assertSame(8, strlen($token));
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $token);
            $this->assertMatchesRegularExpression('/[A-Z]/', $token);
            $this->assertMatchesRegularExpression('/[0-9]/', $token);
        }
    }
}
