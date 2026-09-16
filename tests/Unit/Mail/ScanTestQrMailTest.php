<?php

namespace Tests\Unit\Mail;

use App\Mail\ScanTestQrMail;
use Tests\TestCase;

class ScanTestQrMailTest extends TestCase
{
    public function test_envelope_reports_label_and_code_count(): void
    {
        $mail = new ScanTestQrMail('Event', [
            'event-AAAA-1111.png' => 'binary-1',
            'event-BBBB-2222.png' => 'binary-2',
        ]);

        $this->assertSame('Scan test QR — Event (2 kode)', $mail->envelope()->subject);
    }

    public function test_attachments_keep_filenames_and_png_mime(): void
    {
        $mail = new ScanTestQrMail('OpRec', ['oprec-OPREC-2026-90001.png' => 'binary']);

        $attachments = $mail->attachments();

        $this->assertCount(1, $attachments);
        $this->assertSame('oprec-OPREC-2026-90001.png', $attachments[0]->as);
        $this->assertSame('image/png', $attachments[0]->mime);
    }

    public function test_render_does_not_depend_on_blade_partials(): void
    {
        $mail = new ScanTestQrMail('Event', ['event-AAAA-1111.png' => 'binary']);

        $this->assertStringContainsString('1 QR Event', $mail->render());
    }
}
