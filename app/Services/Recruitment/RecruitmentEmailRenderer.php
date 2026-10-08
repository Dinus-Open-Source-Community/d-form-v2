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
            'group_link' => $this->groupLink($variables),
            default => $this->applicationSubmitted($variables),
        };
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function applicationSubmitted(array $variables): array
    {
        $portalUrl = (string) ($variables['tracking_portal_url'] ?? $variables['tracking_url'] ?? '');

        $rawName = (string) ($variables['applicant_name'] ?? '');
        $rawReg = (string) ($variables['registration_number'] ?? '');
        $rawToken = (string) ($variables['tracking_token'] ?? '');

        $bodyText = 'Halo '.$rawName.",\n\n"
            ."Terima kasih — pendaftaran OpenRecruitment DOSCOM kamu sudah kami terima. Tim kami akan meninjau berkasmu dan menghubungimu lagi untuk tahap berikutnya.\n\n"
            ."────────────────────────\n"
            ."Data untuk tracking\n\n"
            .'Nomor Pendaftaran: '.$rawReg."\n"
            .'Token Tracking: '.$rawToken."\n"
            .'Portal: '.$portalUrl."\n\n"
            ."Simpan nomor dan token ini baik-baik. Keduanya dipakai untuk masuk ke portal tracking.\n\n"
            .'Jika tombol tidak berfungsi, buka URL berikut lalu masukkan nomor dan token secara manual:'."\n"
            .$portalUrl;

        return [
            'subject' => '[DOSCOM OpRec] Konfirmasi Pendaftaran — '.($variables['registration_number'] ?? ''),
            'body_html' => view('mail.recruitment.application-submitted', $variables)->render(),
            'body_text' => $bodyText,
        ];
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function revisionRequired(array $variables): array
    {
        $variables['revision_labels'] = $this->revisionSections($variables);

        $bodyHtml = view('mail.recruitment.revision-required', $variables)->render();

        $rawName = (string) ($variables['applicant_name'] ?? '');
        $rawReg = (string) ($variables['registration_number'] ?? '');
        $rawTrackingUrl = (string) ($variables['tracking_url'] ?? '');
        $rawSections = $this->rawRevisionSections($variables);
        $rawNotes = (string) ($variables['revision_notes'] ?? '');

        $bodyText = 'Halo '.$rawName.",\n\n"
            ."Pendaftaranmu perlu sedikit revisi sebelum bisa kami proses lebih lanjut.\n\n"
            ."Silakan buka portal tracking, periksa catatan revisinya, lalu perbarui berkasmu.\n\n";

        if ($rawSections !== []) {
            $bodyText .= "────────────────────────\n"
                ."Bagian yang perlu diperbaiki\n\n";

            foreach ($rawSections as $label) {
                $bodyText .= '- '.$label."\n";
            }

            $bodyText .= "\n";
        }

        if (trim($rawNotes) !== '') {
            $bodyText .= "────────────────────────\n"
                ."Catatan dari tim\n\n"
                .trim($rawNotes)."\n\n";
        }

        if ($rawReg !== '') {
            $bodyText .= "────────────────────────\n"
                ."Data kamu\n\n"
                .'Nomor Pendaftaran: '.$rawReg."\n\n";
        }

        if ($rawTrackingUrl !== '') {
            $bodyText .= 'Portal: '.$rawTrackingUrl."\n\n"
                ."Jika tombol tidak berfungsi, buka URL berikut:\n"
                .$rawTrackingUrl;
        }

        return [
            'subject' => '[DOSCOM OpRec] Perlu Revisi Pendaftaran',
            'body_html' => $bodyHtml,
            'body_text' => rtrim($bodyText),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function passedScreening(array $variables): array
    {
        $bodyHtml = view('mail.recruitment.passed-screening', $variables)->render();

        $rawName = (string) ($variables['applicant_name'] ?? '');
        $rawReg = (string) ($variables['registration_number'] ?? '');
        $rawTrackingUrl = (string) ($variables['tracking_url'] ?? '');
        $rawWhatsappUrl = (string) ($variables['whatsapp_group_url'] ?? '');

        $bodyText = 'Halo '.$rawName.",\n\n"
            ."Kamu lolos tahap screening OpenRecruitment DOSCOM.\n\n"
            ."Jadwal interview akan kami kirim lewat email berikutnya. Pantau juga portal tracking untuk update terbaru.\n\n";

        if ($rawReg !== '' || trim($rawWhatsappUrl) !== '') {
            $bodyText .= "────────────────────────\n"
                ."Data kamu\n\n";
            if ($rawReg !== '') {
                $bodyText .= 'Nomor Pendaftaran: '.$rawReg."\n";
            }
            if (trim($rawWhatsappUrl) !== '') {
                $bodyText .= 'Grup WA: '.trim($rawWhatsappUrl)."\n";
            }
            $bodyText .= "\n";
        }

        if ($rawTrackingUrl !== '') {
            $bodyText .= 'Portal: '.$rawTrackingUrl."\n\n"
                ."Jika tombol tidak berfungsi, buka URL berikut:\n"
                .$rawTrackingUrl;
        }

        return [
            'subject' => '[DOSCOM OpRec] Lolos Screening',
            'body_html' => $bodyHtml,
            'body_text' => rtrim($bodyText),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function rejectedScreening(array $variables): array
    {
        $bodyHtml = view('mail.recruitment.rejected-screening', $variables)->render();

        $rawName = (string) ($variables['applicant_name'] ?? '');
        $rawReg = (string) ($variables['registration_number'] ?? '');
        $rawTrackingUrl = (string) ($variables['tracking_url'] ?? '');

        $bodyText = 'Halo '.$rawName.",\n\n"
            ."Terima kasih telah mengikuti OpenRecruitment DOSCOM. Mohon maaf, kamu belum lolos tahap screening.\n\n";

        if ($rawReg !== '') {
            $bodyText .= "────────────────────────\n"
                ."Data kamu\n\n"
                .'Nomor Pendaftaran: '.$rawReg."\n\n";
        }

        if ($rawTrackingUrl !== '') {
            $bodyText .= 'Portal: '.$rawTrackingUrl."\n\n"
                ."Jika tombol tidak berfungsi, buka URL berikut:\n"
                .$rawTrackingUrl;
        }

        return [
            'subject' => '[DOSCOM OpRec] Hasil Screening',
            'body_html' => $bodyHtml,
            'body_text' => rtrim($bodyText),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function correctionRequestStaff(array $variables): array
    {
        $bodyHtml = view('mail.recruitment.correction-request-staff', $variables)->render();

        $rawName = (string) ($variables['applicant_name'] ?? '');
        $rawReg = (string) ($variables['registration_number'] ?? '');
        $rawMessage = (string) ($variables['correction_request_message'] ?? '');
        $rawAdminUrl = (string) ($variables['application_admin_url'] ?? '');

        $bodyText = 'Applicant '.$rawName.' ('.$rawReg.') mengajukan permintaan koreksi.'."\n\n"
            .'Pesan: '.$rawMessage;

        if ($rawAdminUrl !== '') {
            $bodyText .= "\n\nBuka di dashboard:\n".$rawAdminUrl;
        }

        return [
            'subject' => '[DOSCOM OpRec] Permintaan Koreksi — '.($variables['registration_number'] ?? ''),
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function interviewScheduled(array $variables): array
    {
        $rendered = $this->interviewBody(
            subject: '[DOSCOM OpRec] Jadwal Interview — '.($variables['registration_number'] ?? ''),
            variables: $variables,
            intro: 'Interview kamu dijadwalkan pada',
            includeTrackingLink: true,
            qrNote: 'QR code absensi ada di bagian bawah email ini atau di halaman tracking. Tunjukkan ke panitia saat tiba.',
        );

        $rendered['body_html'] = view('mail.recruitment.interview-scheduled', $variables)->render();

        return $rendered;
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function interviewRescheduled(array $variables): array
    {
        $rendered = $this->interviewBody(
            subject: '[DOSCOM OpRec] Jadwal Interview Diubah',
            variables: $variables,
            intro: 'Jadwal interview kamu diperbarui menjadi',
            includeTrackingLink: false,
            qrNote: 'QR code absensi ada di bagian bawah email ini atau di halaman tracking. Tunjukkan ke panitia saat tiba.',
        );

        $rendered['body_html'] = view('mail.recruitment.interview-rescheduled', $variables)->render();

        return $rendered;
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function interviewReminderH1(array $variables): array
    {
        $rendered = $this->interviewBody(
            subject: '[DOSCOM OpRec] Reminder Interview Besok',
            variables: $variables,
            intro: 'Interview kamu besok pada',
            includeTrackingLink: false,
            qrNote: 'QR code absensi ada di bagian bawah email ini atau di halaman tracking. Tunjukkan ke panitia saat tiba.',
        );

        $rendered['body_html'] = view('mail.recruitment.interview-reminder-h1', $variables)->render();

        return $rendered;
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function interviewReminderH2(array $variables): array
    {
        $rendered = $this->interviewBody(
            subject: '[DOSCOM OpRec] Reminder Interview 2 Jam Lagi',
            variables: $variables,
            intro: 'Interview kamu dimulai sekitar 2 jam lagi pada',
            includeTrackingLink: false,
            qrNote: 'QR code absensi ada di bagian bawah email ini atau di halaman tracking. Tunjukkan ke panitia saat tiba.',
        );

        $rendered['body_html'] = view('mail.recruitment.interview-reminder-h2', $variables)->render();

        return $rendered;
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function interviewAssignment(array $variables): array
    {
        $bodyHtml = view('mail.recruitment.interview-assignment', $variables)->render();

        $rawInterviewer = (string) ($variables['interviewer_name'] ?? '');
        $rawApplicant = (string) ($variables['applicant_name'] ?? '');
        $rawReg = (string) ($variables['registration_number'] ?? '');
        $rawDate = (string) ($variables['interview_date'] ?? '');
        $rawTime = (string) ($variables['interview_time'] ?? '');
        $rawLocation = (string) ($variables['interview_location'] ?? '');
        $rawRoom = (string) ($variables['interview_room'] ?? '');
        $rawAdminUrl = (string) ($variables['application_admin_url'] ?? '');

        $bodyText = 'Halo '.$rawInterviewer.",\n\n"
            .'Kamu ditugaskan sebagai interviewer untuk '.$rawApplicant.' ('.$rawReg.').'."\n\n"
            ."────────────────────────\n"
            ."Penugasan interview\n\n";

        if ($rawApplicant !== '') {
            $bodyText .= 'Applicant: '.$rawApplicant."\n";
        }
        if ($rawReg !== '') {
            $bodyText .= 'Nomor Pendaftaran: '.$rawReg."\n";
        }
        if ($rawDate !== '') {
            $bodyText .= 'Tanggal: '.$rawDate."\n";
        }
        if ($rawTime !== '') {
            $bodyText .= 'Jam: '.$rawTime."\n";
        }
        if ($rawLocation !== '') {
            $bodyText .= 'Lokasi: '.$rawLocation."\n";
        }
        if ($rawRoom !== '') {
            $bodyText .= 'Ruang: '.$rawRoom."\n";
        }

        if ($rawAdminUrl !== '') {
            $bodyText .= "\nLihat applicant:\n".$rawAdminUrl;
        }

        return [
            'subject' => '[DOSCOM OpRec] Penugasan Interview — '.($variables['registration_number'] ?? ''),
            'body_html' => $bodyHtml,
            'body_text' => rtrim($bodyText),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function finalAccepted(array $variables): array
    {
        $bodyHtml = view('mail.recruitment.final-accepted', $variables)->render();

        $rawName = (string) ($variables['applicant_name'] ?? '');
        $rawMembership = (string) ($variables['membership_type'] ?? '');
        $rawDivision = (string) ($variables['final_division'] ?? '');
        $rawPublicMessage = (string) ($variables['public_message'] ?? '');

        $bodyText = 'Halo '.$rawName.",\n\n"
            .'Selamat! Kamu diterima sebagai '.$rawMembership.' di divisi '.$rawDivision.".\n\n"
            ."────────────────────────\n"
            ."Hasil akhir\n\n"
            ."Status: Diterima\n";

        if ($rawDivision !== '') {
            $bodyText .= 'Divisi: '.$rawDivision."\n";
        }
        if ($rawMembership !== '') {
            $bodyText .= 'Tipe: '.$rawMembership."\n";
        }

        if ($rawPublicMessage !== '') {
            $bodyText .= "\nCatatan dari tim:\n".$rawPublicMessage."\n";
        }

        $bodyText .= "\nInformasi orientasi dan langkah berikutnya akan kami kirim lewat email berikutnya.";

        return [
            'subject' => '[DOSCOM OpRec] Selamat — Kamu Diterima!',
            'body_html' => $bodyHtml,
            'body_text' => rtrim($bodyText),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function groupLink(array $variables): array
    {
        $bodyHtml = view('mail.recruitment.group-link', $variables)->render();

        $rawName = (string) ($variables['applicant_name'] ?? '');
        $rawReg = (string) ($variables['registration_number'] ?? '');
        $rawPeriod = (string) ($variables['period_name'] ?? '');
        $rawWhatsappUrl = (string) ($variables['whatsapp_group_url'] ?? '');

        $bodyText = 'Halo '.$rawName.",\n\n"
            ."Kamu lolos tahap screening OpenRecruitment DOSCOM. Gabung ke grup WhatsApp agar tidak ketinggalan info tahap berikutnya.\n\n";

        if (trim($rawWhatsappUrl) !== '') {
            $bodyText .= "Gabung Grup WA:\n".trim($rawWhatsappUrl)."\n\n";
        }

        if ($rawReg !== '') {
            $bodyText .= "────────────────────────\n"
                ."Data kamu\n\n"
                .'Nomor Pendaftaran: '.$rawReg."\n";
        }

        return [
            'subject' => '[DOSCOM OpRec] Link Grup WA'.($rawPeriod !== '' ? ' — '.$rawPeriod : ''),
            'body_html' => $bodyHtml,
            'body_text' => rtrim($bodyText),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    private function finalRejected(array $variables): array
    {
        $bodyHtml = view('mail.recruitment.final-rejected', $variables)->render();

        $rawName = (string) ($variables['applicant_name'] ?? '');

        return [
            'subject' => '[DOSCOM OpRec] Hasil OpenRecruitment',
            'body_html' => $bodyHtml,
            'body_text' => 'Halo '.$rawName.",\n\n"
                ."Terima kasih telah mengikuti seluruh rangkaian OpenRecruitment DOSCOM.\n\n"
                .'Mohon maaf, kamu belum lolos seleksi tahap akhir.',
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

        $bodyHtml = '<p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#374151;">'
            .'<strong style="color:#111827;">Halo '.$name.',</strong></p>'
            .'<p style="margin:0 0 20px;font-size:16px;line-height:1.65;color:#374151;">'
            .$intro.' <strong style="color:#111827;">'.$date.'</strong>'
            .' pukul <strong style="color:#111827;">'.$time.'</strong>.</p>';

        $rows = '';
        if ($date !== '') {
            $rows .= '<tr>'
                .'<td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;width:38%;">Tanggal</td>'
                .'<td style="padding:8px 0;border-top:1px solid #e5e7eb;font-weight:600;color:#111827;">'.$date.'</td>'
                .'</tr>';
        }
        if ($time !== '') {
            $rows .= '<tr>'
                .'<td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;">Jam</td>'
                .'<td style="padding:8px 0;border-top:1px solid #e5e7eb;font-weight:600;color:#111827;">'.$time.'</td>'
                .'</tr>';
        }
        if ($location !== '') {
            $rows .= '<tr>'
                .'<td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;">Lokasi</td>'
                .'<td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#374151;">'.$location.'</td>'
                .'</tr>';
        }
        if ($room !== '') {
            $rows .= '<tr>'
                .'<td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;">Ruang</td>'
                .'<td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#374151;">'.$room.'</td>'
                .'</tr>';
        }

        if ($rows !== '') {
            $bodyHtml .= '<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" '
                .'style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;margin:0 0 20px;">'
                .'<tr><td style="padding:18px 20px;">'
                .'<p style="margin:0 0 10px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">'
                .'Jadwal interview</p>'
                .'<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" '
                .'style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;font-size:15px;line-height:1.55;color:#374151;">'
                .$rows
                .'</table>'
                .'</td></tr></table>';
        }

        $bodyHtml .= '<p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#4b5563;">'
            .$qrNote.'</p>';

        if ($includeTrackingLink && $trackingUrl !== '') {
            $bodyHtml .= '<table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" '
                .'style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;margin:16px auto 8px;">'
                .'<tr><td bgcolor="#4f46e5" style="border-radius:10px;background-color:#4f46e5;">'
                .'<a href="'.$trackingUrl.'" '
                .'style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">'
                .'Pantau progress pendaftaran</a>'
                .'</td></tr></table>'
                .'<p style="margin:8px 0 0;font-size:13px;line-height:1.6;color:#6b7280;text-align:center;">'
                .'Jika tombol tidak berfungsi, salin URL ini ke browser:<br>'
                .'<span style="word-break:break-all;color:#374151;">'.$trackingUrl.'</span></p>';
        }

        $rawName = (string) ($variables['applicant_name'] ?? '');
        $rawDate = (string) ($variables['interview_date'] ?? '');
        $rawTime = (string) ($variables['interview_time'] ?? '');
        $rawLocation = (string) ($variables['interview_location'] ?? '');
        $rawRoom = (string) ($variables['interview_room'] ?? '');
        $rawTrackingUrl = (string) ($variables['tracking_url'] ?? '');

        $bodyText = 'Halo '.$rawName.",\n\n"
            .$intro.' '.$rawDate.' pukul '.$rawTime.".\n\n"
            ."────────────────────────\n"
            ."Jadwal interview\n\n";

        if ($rawDate !== '') {
            $bodyText .= 'Tanggal: '.$rawDate."\n";
        }
        if ($rawTime !== '') {
            $bodyText .= 'Jam: '.$rawTime."\n";
        }
        if ($rawLocation !== '') {
            $bodyText .= 'Lokasi: '.$rawLocation."\n";
        }
        if ($rawRoom !== '') {
            $bodyText .= 'Ruang: '.$rawRoom."\n";
        }

        $bodyText .= "\n".$qrNote."\n";

        if ($includeTrackingLink && $rawTrackingUrl !== '') {
            $bodyText .= "\nPortal: ".$rawTrackingUrl."\n\n"
                ."Jika tombol tidak berfungsi, buka URL berikut:\n"
                .$rawTrackingUrl;
        }

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => rtrim($bodyText),
        ];
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return list<string>
     */
    private function revisionSections(array $variables): array
    {
        $labels = [];

        foreach ($this->rawRevisionSections($variables) as $label) {
            $labels[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        }

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return list<string>
     */
    private function rawRevisionSections(array $variables): array
    {
        $sections = $variables['revision_sections'] ?? [];

        if (is_string($sections)) {
            $sections = [$sections];
        }

        if (! is_array($sections)) {
            return [];
        }

        $labels = [];

        foreach ($sections as $section) {
            $label = trim((string) $section);

            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return array_values($labels);
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
