<?php

namespace App\Services\Recruitment;

final class RecruitmentEmailRenderer
{
    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    public function renderTemplate(string $eventType, array $variables): array
    {
        return match ($eventType) {
            'application_submitted' => $this->applicationSubmitted($variables),
            'revision_required' => $this->revisionRequired($variables),
            'passed_screening' => $this->passedScreening($variables),
            'rejected_screening' => $this->rejectedScreening($variables),
            'correction_request_staff' => $this->correctionRequestStaff($variables),
            'interview_scheduled' => $this->interviewScheduled($variables),
            'interview_rescheduled' => $this->interviewRescheduled($variables),
            'interview_reminder_h1' => $this->interviewReminderH1($variables),
            'interview_reminder_h2' => $this->interviewReminderH2($variables),
            'interview_assignment' => $this->interviewAssignment($variables),
            'final_accepted' => $this->finalAccepted($variables),
            'final_rejected' => $this->finalRejected($variables),
            default => $this->applicationSubmitted($variables),
        };
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function applicationSubmitted(array $variables): array
    {
        $name = $this->e($variables, 'applicant_name');
        $reg = $this->e($variables, 'registration_number');
        $token = $this->e($variables, 'tracking_token');
        $trackingUrl = $this->e($variables, 'tracking_url');
        $portalButton = $variables['tracking_portal_button'] ?? '';
        $portalUrl = $this->e($variables, 'tracking_portal_url', $variables['tracking_url'] ?? '');

        $bodyHtml = '<p>Halo '.$name.',</p>'
            .'<p>Pendaftaran OpenRecruitment DOSCOM kamu telah berhasil diterima.</p>'
            .'<p><strong>Nomor Pendaftaran:</strong> '.$reg.'</p>'
            .'<p><strong>Token Tracking:</strong> '
            .'<span style="font-family:monospace;font-size:16px;letter-spacing:0.08em;">'.$token.'</span></p>'
            .'<p>Simpan nomor pendaftaran dan token di atas. Keduanya diperlukan untuk masuk portal tracking.</p>'
            .$portalButton
            .'<p style="font-size:13px;color:#6b7280;">Atau buka '
            .'<a href="'.$trackingUrl.'">'.$trackingUrl.'</a> lalu masukkan kredensial secara manual.</p>';

        return [
            'subject' => '[DOSCOM OpRec] Konfirmasi Pendaftaran — '.($variables['registration_number'] ?? ''),
            'body_html' => $bodyHtml,
            'body_text' => 'Pendaftaran diterima. Nomor: '.($variables['registration_number'] ?? '')
                .'. Token: '.($variables['tracking_token'] ?? '')
                .'. Portal: '.$portalUrl,
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function revisionRequired(array $variables): array
    {
        $name = $this->e($variables, 'applicant_name');
        $trackingUrl = $this->e($variables, 'tracking_url');
        $bodyHtml = '<p>Halo '.$name.',</p>'
            .'<p>Pendaftaranmu memerlukan revisi. Silakan periksa tracking portal di '
            .'<a href="'.$trackingUrl.'">'.$trackingUrl.'</a></p>';

        return [
            'subject' => '[DOSCOM OpRec] Perlu Revisi Pendaftaran',
            'body_html' => $bodyHtml,
            'body_text' => 'Pendaftaranmu memerlukan revisi. Pantau di '.($variables['tracking_url'] ?? ''),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function passedScreening(array $variables): array
    {
        $name = $this->e($variables, 'applicant_name');
        $bodyHtml = '<p>Halo '.$name.',</p>'
            .'<p>Selamat! Kamu lolos tahap screening OpenRecruitment.</p>';

        return [
            'subject' => '[DOSCOM OpRec] Lolos Screening',
            'body_html' => $bodyHtml,
            'body_text' => 'Selamat! Kamu lolos tahap screening OpenRecruitment.',
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function rejectedScreening(array $variables): array
    {
        $name = $this->e($variables, 'applicant_name');
        $trackingUrl = $this->e($variables, 'tracking_url');
        $bodyHtml = '<p>Halo '.$name.',</p>'
            .'<p>Terima kasih telah mengikuti OpenRecruitment DOSCOM. Mohon maaf, kamu belum lolos tahap screening.</p>'
            .'<p>Pantau informasi di <a href="'.$trackingUrl.'">'.$trackingUrl.'</a></p>';

        return [
            'subject' => '[DOSCOM OpRec] Hasil Screening',
            'body_html' => $bodyHtml,
            'body_text' => 'Terima kasih telah mengikuti OpenRecruitment DOSCOM.',
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function correctionRequestStaff(array $variables): array
    {
        $name = $this->e($variables, 'applicant_name');
        $reg = $this->e($variables, 'registration_number');
        $message = $this->e($variables, 'correction_request_message');
        $adminUrl = $this->e($variables, 'application_admin_url');
        $bodyHtml = '<p>Applicant <strong>'.$name.'</strong> ('.$reg.') mengajukan permintaan koreksi.</p>'
            .'<p><strong>Pesan:</strong> '.$message.'</p>'
            .'<p><a href="'.$adminUrl.'">Buka di dashboard</a></p>';

        return [
            'subject' => '[DOSCOM OpRec] Permintaan Koreksi — '.($variables['registration_number'] ?? ''),
            'body_html' => $bodyHtml,
            'body_text' => 'Permintaan koreksi dari '.($variables['applicant_name'] ?? '').'. '.($variables['application_admin_url'] ?? ''),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function interviewScheduled(array $variables): array
    {
        return $this->interviewBody(
            subject: '[DOSCOM OpRec] Jadwal Interview — '.($variables['registration_number'] ?? ''),
            variables: $variables,
            intro: 'Interview kamu dijadwalkan pada',
            includeTrackingLink: true,
            qrNote: 'Tunjukkan QR code absensi (lampiran email atau di halaman tracking) kepada panitia saat tiba. Absensi hanya diproses oleh panitia.',
        );
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function interviewRescheduled(array $variables): array
    {
        return $this->interviewBody(
            subject: '[DOSCOM OpRec] Jadwal Interview Diubah',
            variables: $variables,
            intro: 'Jadwal interview kamu diperbarui menjadi',
            includeTrackingLink: false,
            qrNote: 'Tunjukkan QR code absensi kepada panitia saat tiba.',
        );
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function interviewReminderH1(array $variables): array
    {
        $name = $this->e($variables, 'applicant_name');
        $date = $this->e($variables, 'interview_date');
        $time = $this->e($variables, 'interview_time');
        $location = $this->e($variables, 'interview_location');
        $room = $this->e($variables, 'interview_room');
        $bodyHtml = '<p>Halo '.$name.',</p>'
            .'<p>Reminder: interview kamu besok ('.$date.') pukul '.$time.' di '.$location.' ruang '.$room.'.</p>'
            .'<p>Bawa QR code absensi (email atau tracking) untuk discan panitia.</p>';

        return [
            'subject' => '[DOSCOM OpRec] Reminder Interview Besok',
            'body_html' => $bodyHtml,
            'body_text' => 'Reminder interview besok '.$date.' '.$time,
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function interviewReminderH2(array $variables): array
    {
        $name = $this->e($variables, 'applicant_name');
        $time = $this->e($variables, 'interview_time');
        $location = $this->e($variables, 'interview_location');
        $room = $this->e($variables, 'interview_room');
        $bodyHtml = '<p>Halo '.$name.',</p>'
            .'<p>Interview kamu akan dimulai sekitar 2 jam lagi ('.$time.') di '.$location.' ruang '.$room.'.</p>'
            .'<p>Tunjukkan QR code absensi kepada panitia saat tiba.</p>';

        return [
            'subject' => '[DOSCOM OpRec] Reminder Interview 2 Jam Lagi',
            'body_html' => $bodyHtml,
            'body_text' => 'Interview 2 jam lagi '.$time.' di '.$location,
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function interviewAssignment(array $variables): array
    {
        $interviewer = $this->e($variables, 'interviewer_name');
        $applicant = $this->e($variables, 'applicant_name');
        $reg = $this->e($variables, 'registration_number');
        $date = $this->e($variables, 'interview_date');
        $time = $this->e($variables, 'interview_time');
        $location = $this->e($variables, 'interview_location');
        $room = $this->e($variables, 'interview_room');
        $adminUrl = $this->e($variables, 'application_admin_url');
        $bodyHtml = '<p>Halo '.$interviewer.',</p>'
            .'<p>Kamu ditugaskan sebagai interviewer untuk <strong>'.$applicant.'</strong> ('.$reg.').</p>'
            .'<p>Jadwal: '.$date.' pukul '.$time.' · '.$location.' ruang '.$room.'</p>'
            .'<p><a href="'.$adminUrl.'">Lihat applicant</a></p>';

        return [
            'subject' => '[DOSCOM OpRec] Penugasan Interview — '.($variables['registration_number'] ?? ''),
            'body_html' => $bodyHtml,
            'body_text' => 'Penugasan interview '.$applicant.' '.$date.' '.$time,
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function finalAccepted(array $variables): array
    {
        $name = $this->e($variables, 'applicant_name');
        $membership = $this->e($variables, 'membership_type');
        $division = $this->e($variables, 'final_division');
        $bodyHtml = '<p>Halo '.$name.',</p>'
            .'<p>Selamat! Kamu diterima sebagai '.$membership.' di divisi '.$division.'.</p>';

        return [
            'subject' => '[DOSCOM OpRec] Selamat — Kamu Diterima!',
            'body_html' => $bodyHtml,
            'body_text' => 'Selamat! Kamu diterima sebagai '.($variables['membership_type'] ?? '').'.',
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function finalRejected(array $variables): array
    {
        $name = $this->e($variables, 'applicant_name');
        $bodyHtml = '<p>Halo '.$name.',</p>'
            .'<p>Terima kasih telah mengikuti OpenRecruitment DOSCOM.</p>';

        return [
            'subject' => '[DOSCOM OpRec] Hasil OpenRecruitment',
            'body_html' => $bodyHtml,
            'body_text' => 'Terima kasih telah mengikuti OpenRecruitment DOSCOM.',
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function interviewBody(
        string $subject,
        array $variables,
        string $intro,
        bool $includeTrackingLink,
        string $qrNote,
    ): array {
        $name = $this->e($variables, 'applicant_name');
        $date = $this->e($variables, 'interview_date');
        $time = $this->e($variables, 'interview_time');
        $location = $this->e($variables, 'interview_location');
        $room = $this->e($variables, 'interview_room');
        $trackingUrl = $this->e($variables, 'tracking_url');

        $bodyHtml = '<p>Halo '.$name.',</p>'
            .'<p>'.$intro.' <strong>'.$date.'</strong> pukul <strong>'.$time.'</strong>.</p>'
            .'<p>Lokasi: '.$location.' · Ruang '.$room.'</p>'
            .'<p>'.$qrNote.'</p>';

        if ($includeTrackingLink) {
            $bodyHtml .= '<p>Pantau progress di <a href="'.$trackingUrl.'">'.$trackingUrl.'</a></p>';
        }

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => strip_tags($bodyHtml),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function e(array $variables, string $key, ?string $fallback = null): string
    {
        $value = $variables[$key] ?? $fallback ?? '';

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
