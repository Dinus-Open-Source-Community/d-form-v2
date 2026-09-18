<?php

namespace Tests\Unit\Mail;

use App\Mail\ScanTestQrMail;
use Tests\TestCase;

class ScanTestQrMailTest extends TestCase
{
    /** @return array<int, array{code:string,name:string,kind:string,context:string}> */
    private function rows(): array
    {
        return [
            ['code' => 'KUSE-KZ49', 'name' => 'Scan Test Event 01', 'kind' => 'event', 'context' => 'Seminar Programming'],
            ['code' => 'OPREC-2026-90001', 'name' => 'Scan Test Oprec 01', 'kind' => 'oprec', 'context' => 'OPREC 2026 · Programming'],
        ];
    }

    public function test_envelope_reports_label_and_code_count(): void
    {
        $mail = new ScanTestQrMail('Event', $this->rows(), [
            'event-KUSE-KZ49.png' => 'binary-1',
            'event-BBBB-2222.png' => 'binary-2',
        ]);

        $this->assertSame('Scan test QR — Event (2 kode)', $mail->envelope()->subject);
    }

    public function test_attachments_keep_filenames_and_png_mime(): void
    {
        $mail = new ScanTestQrMail('OpRec', $this->rows(), ['oprec-OPREC-2026-90001.png' => 'binary']);

        $attachments = $mail->attachments();

        $this->assertCount(1, $attachments);
        $this->assertSame('oprec-OPREC-2026-90001.png', $attachments[0]->as);
        $this->assertSame('image/png', $attachments[0]->mime);
    }

    public function test_body_lists_code_name_kind_and_context(): void
    {
        $mail = new ScanTestQrMail('Event', $this->rows(), ['event-KUSE-KZ49.png' => 'binary']);

        $html = $mail->render();

        foreach (['Kode', 'Nama', 'Jenis', 'Event / Periode'] as $heading) {
            $this->assertStringContainsString($heading, $html);
        }

        $this->assertStringContainsString('KUSE-KZ49', $html);
        $this->assertStringContainsString('Scan Test Event 01', $html);
        $this->assertStringContainsString('Seminar Programming', $html);
        $this->assertStringContainsString('OPREC-2026-90001', $html);
        $this->assertStringContainsString('OPREC 2026 · Programming', $html);
    }

    public function test_body_escapes_html_in_row_values(): void
    {
        $mail = new ScanTestQrMail('Event', [
            ['code' => '<b>X</b>', 'name' => '<script>alert(1)</script>', 'kind' => 'event', 'context' => '"quote"'],
        ], []);

        $html = $mail->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>X</b>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_body_renders_placeholder_when_no_rows(): void
    {
        $mail = new ScanTestQrMail('Event', [], []);

        $this->assertStringContainsString('Tidak ada baris', $mail->render());
    }
}
