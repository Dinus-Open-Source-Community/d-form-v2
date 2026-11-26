<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationOtpMail;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Mail\Mailables\Content;
use Tests\TestCase;

/** Salinan email kode OTP: verifikasi email tetap, pemulihan PIN memakai bahasa PIN. */
class EmailVerificationOtpMailTest extends TestCase
{
    private const CODE = '654321';

    private const TTL_MINUTES = 10;

    public function test_the_default_mail_keeps_the_email_verification_copy(): void
    {
        $mail = new EmailVerificationOtpMail('Sapto', self::CODE, self::TTL_MINUTES);

        $this->assertSame('Verifikasi email DForm', $mail->envelope()->subject);
        $this->assertStringContainsString('memverifikasi email akun DForm kamu', $this->renderHtmlBody($mail));
        $this->assertStringContainsString('memverifikasi email akun DForm kamu', $this->renderTextBody($mail));
    }

    public function test_the_recovery_mail_talks_about_the_pin_instead_of_email_verification(): void
    {
        $mail = new EmailVerificationOtpMail(
            'Sapto',
            self::CODE,
            self::TTL_MINUTES,
            purpose: EmailVerificationService::PURPOSE_TWO_FACTOR_RECOVERY,
        );

        $subject = $mail->envelope()->subject;
        $html = $this->renderHtmlBody($mail);
        $text = $this->renderTextBody($mail);

        foreach ([$subject, $html, $text] as $copy) {
            $this->assertStringContainsString('PIN', $copy);
            $this->assertStringNotContainsStringIgnoringCase('verifikasi email', $copy);
            $this->assertStringNotContainsStringIgnoringCase('memverifikasi email', $copy);
        }

        $this->assertStringContainsString('PIN login DForm', $html);
        $this->assertStringContainsString(self::CODE, $html);
    }

    /** Render bagian HTML seperti yang dikirim Laravel Mail. */
    private function renderHtmlBody(EmailVerificationOtpMail $mail): string
    {
        return $mail->render();
    }

    /** Render pasangan teks memakai data view yang sama dengan bagian HTML. */
    private function renderTextBody(EmailVerificationOtpMail $mail): string
    {
        $content = $mail->content();

        $this->assertInstanceOf(Content::class, $content);
        $this->assertIsString($content->text);

        return view($content->text, [
            ...$content->with,
            'recipientName' => $mail->recipientName,
            'code' => $mail->code,
            'ttlMinutes' => $mail->ttlMinutes,
        ])->render();
    }
}
