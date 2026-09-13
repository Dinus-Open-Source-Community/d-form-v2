<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentEmailTemplate;

final class RecruitmentEmailRenderer
{
    /**
     * @param  array<string, string>  $variables
     */
    public function render(string $template, array $variables): string
    {
        $rendered = $template;

        foreach ($variables as $key => $value) {
            $rendered = str_replace('{{'.$key.'}}', $value, $rendered);
        }

        return $rendered;
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    public function renderTemplate(string $eventType, array $variables): array
    {
        $template = RecruitmentEmailTemplate::query()
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            return $this->fallbackTemplate($eventType, $variables);
        }

        return [
            'subject' => $this->render($template->subject, $variables),
            'body_html' => $this->render($template->body_html, $variables),
            'body_text' => $this->render($template->body_text ?? strip_tags($template->body_html), $variables),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function fallbackTemplate(string $eventType, array $variables): array
    {
        return match ($eventType) {
            'revision_required' => [
                'subject' => '[DOSCOM OpRec] Perlu Revisi Pendaftaran',
                'body_html' => '<p>Halo '.$variables['applicant_name'].',</p>'
                    .'<p>Pendaftaranmu memerlukan revisi. Silakan periksa tracking portal di '
                    .$variables['tracking_url'].'</p>',
                'body_text' => 'Pendaftaranmu memerlukan revisi. Pantau di '.$variables['tracking_url'],
            ],
            'passed_screening' => [
                'subject' => '[DOSCOM OpRec] Lolos Screening',
                'body_html' => '<p>Halo '.$variables['applicant_name'].',</p>'
                    .'<p>Selamat! Kamu lolos tahap screening OpenRecruitment.</p>',
                'body_text' => 'Selamat! Kamu lolos tahap screening OpenRecruitment.',
            ],
            'rejected_screening' => [
                'subject' => '[DOSCOM OpRec] Hasil Screening',
                'body_html' => '<p>Halo '.$variables['applicant_name'].',</p>'
                    .'<p>Terima kasih telah mengikuti OpenRecruitment DOSCOM.</p>',
                'body_text' => 'Terima kasih telah mengikuti OpenRecruitment DOSCOM.',
            ],
            'correction_request_staff' => [
                'subject' => '[DOSCOM OpRec] Permintaan Koreksi — '.$variables['registration_number'],
                'body_html' => '<p>Applicant '.$variables['applicant_name'].' ('.$variables['registration_number'].') mengajukan permintaan koreksi.</p>'
                    .'<p><strong>Pesan:</strong> '.$variables['correction_request_message'].'</p>'
                    .'<p><a href="'.$variables['application_admin_url'].'">Buka di dashboard</a></p>',
                'body_text' => 'Permintaan koreksi dari '.$variables['applicant_name'].'. '.$variables['application_admin_url'],
            ],
            'interview_scheduled', 'interview_rescheduled', 'interview_reminder_h1', 'interview_reminder_h2', 'interview_assignment' => [
                'subject' => '[DOSCOM OpRec] Interview — '.$variables['registration_number'],
                'body_html' => '<p>Halo '.($variables['applicant_name'] ?: $variables['interviewer_name']).',</p>'
                    .'<p>Jadwal interview: '.$variables['interview_date'].' pukul '.$variables['interview_time'].'</p>'
                    .'<p>Lokasi: '.$variables['interview_location'].' · Ruang '.$variables['interview_room'].'</p>',
                'body_text' => 'Jadwal interview '.$variables['interview_date'].' '.$variables['interview_time'],
            ],
            default => $this->fallbackApplicationSubmitted($variables),
        };
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function fallbackApplicationSubmitted(array $variables): array
    {
        $subject = '[DOSCOM OpRec] Konfirmasi Pendaftaran — '.$variables['registration_number'];
        $bodyHtml = '<p>Halo '.$variables['applicant_name'].',</p>'
            .'<p>Pendaftaran OpenRecruitment DOSCOM kamu telah berhasil diterima.</p>'
            .'<p><strong>Nomor Pendaftaran:</strong> '.$variables['registration_number'].'</p>'
            .'<p><strong>Token Tracking:</strong> '.$variables['tracking_token'].'</p>'
            .'<p>Gunakan nomor pendaftaran dan token untuk memantau progress di '
            .$variables['tracking_url'].'</p>';

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => strip_tags($bodyHtml),
        ];
    }
}
