<?php

namespace Tests\Unit\Services\Du;

use App\Enums\DuRedeemStatus;
use PHPUnit\Framework\TestCase;

class DuRedeemStatusTest extends TestCase
{
    public function test_status_values_are_stable(): void
    {
        $this->assertSame(['pending', 'issued', 'failed', 'unknown'], array_column(DuRedeemStatus::cases(), 'value'));
    }
}
