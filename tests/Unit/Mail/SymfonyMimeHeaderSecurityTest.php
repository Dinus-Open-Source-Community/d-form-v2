<?php

namespace Tests\Unit\Mail;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\Header\ParameterizedHeader;

class SymfonyMimeHeaderSecurityTest extends TestCase
{
    public function test_rejects_non_token_parameter_names_before_serialization(): void
    {
        $this->expectException(RfcComplianceException::class);

        new ParameterizedHeader(
            'Content-Disposition',
            'attachment',
            ["filename\r\nX-Injected" => 'safe.txt'],
        );
    }

    public function test_keeps_valid_attachment_parameter_names_usable(): void
    {
        $header = new ParameterizedHeader(
            'Content-Disposition',
            'attachment',
            ['filename' => 'safe.txt'],
        );

        $this->assertSame('attachment; filename=safe.txt', $header->getBodyAsString());
    }
}
