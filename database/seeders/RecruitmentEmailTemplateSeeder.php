<?php

namespace Database\Seeders;

use App\Models\Recruitment\RecruitmentEmailTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RecruitmentEmailTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $templates = [
            [
                'event_type' => 'application_submitted',
                'subject' => '[DOSCOM OpRec] Konfirmasi Pendaftaran — {{registration_number}}',
                'body_html' => '<p>Halo {{applicant_name}},</p><p>Pendaftaran OpenRecruitment DOSCOM kamu telah berhasil diterima.</p><p><strong>Nomor Pendaftaran:</strong> {{registration_number}}</p><p><strong>Token Tracking:</strong> {{tracking_token}}</p><p>Pantau progress di <a href="{{tracking_url}}">{{tracking_url}}</a></p>',
            ],
            [
                'event_type' => 'revision_required',
                'subject' => '[DOSCOM OpRec] Perlu Revisi Pendaftaran',
                'body_html' => '<p>Halo {{applicant_name}},</p><p>Pendaftaranmu memerlukan revisi. Silakan periksa tracking portal.</p>',
            ],
            [
                'event_type' => 'passed_screening',
                'subject' => '[DOSCOM OpRec] Lolos Screening',
                'body_html' => '<p>Halo {{applicant_name}},</p><p>Selamat! Kamu lolos tahap screening OpenRecruitment.</p>',
            ],
            [
                'event_type' => 'interview_scheduled',
                'subject' => '[DOSCOM OpRec] Jadwal Interview — {{registration_number}}',
                'body_html' => '<p>Halo {{applicant_name}},</p><p>Interview kamu dijadwalkan pada <strong>{{interview_date}}</strong> pukul <strong>{{interview_time}}</strong>.</p><p>Lokasi: {{interview_location}} · Ruang {{interview_room}}</p><p>Tunjukkan QR code absensi (lampiran email atau di halaman tracking) kepada panitia saat tiba. Absensi hanya diproses oleh panitia.</p><p>Pantau progress di <a href="{{tracking_url}}">{{tracking_url}}</a></p>',
            ],
            [
                'event_type' => 'interview_rescheduled',
                'subject' => '[DOSCOM OpRec] Jadwal Interview Diubah',
                'body_html' => '<p>Halo {{applicant_name}},</p><p>Jadwal interview kamu diperbarui menjadi {{interview_date}} pukul {{interview_time}}.</p><p>Lokasi: {{interview_location}} · Ruang {{interview_room}}</p><p>Tunjukkan QR code absensi kepada panitia saat tiba.</p>',
            ],
            [
                'event_type' => 'interview_reminder_h1',
                'subject' => '[DOSCOM OpRec] Reminder Interview Besok',
                'body_html' => '<p>Halo {{applicant_name}},</p><p>Reminder: interview kamu besok ({{interview_date}}) pukul {{interview_time}} di {{interview_location}} ruang {{interview_room}}.</p><p>Bawa QR code absensi (email atau tracking) untuk discan panitia.</p>',
            ],
            [
                'event_type' => 'interview_reminder_h2',
                'subject' => '[DOSCOM OpRec] Reminder Interview 2 Jam Lagi',
                'body_html' => '<p>Halo {{applicant_name}},</p><p>Interview kamu akan dimulai sekitar 2 jam lagi ({{interview_time}}) di {{interview_location}} ruang {{interview_room}}.</p><p>Tunjukkan QR code absensi kepada panitia saat tiba.</p>',
            ],
            [
                'event_type' => 'interview_assignment',
                'subject' => '[DOSCOM OpRec] Penugasan Interview — {{registration_number}}',
                'body_html' => '<p>Halo {{interviewer_name}},</p><p>Kamu ditugaskan sebagai interviewer untuk <strong>{{applicant_name}}</strong> ({{registration_number}}).</p><p>Jadwal: {{interview_date}} pukul {{interview_time}} · {{interview_location}} ruang {{interview_room}}</p><p><a href="{{application_admin_url}}">Lihat applicant</a></p>',
            ],
            [
                'event_type' => 'final_accepted',
                'subject' => '[DOSCOM OpRec] Selamat — Kamu Diterima!',
                'body_html' => '<p>Halo {{applicant_name}},</p><p>Selamat! Kamu diterima sebagai {{membership_type}} di divisi {{final_division}}.</p>',
            ],
            [
                'event_type' => 'correction_request_staff',
                'subject' => '[DOSCOM OpRec] Permintaan Koreksi — {{registration_number}}',
                'body_html' => '<p>Applicant <strong>{{applicant_name}}</strong> ({{registration_number}}) mengajukan permintaan koreksi.</p><p><strong>Pesan:</strong> {{correction_request_message}}</p><p><a href="{{application_admin_url}}">Buka di dashboard</a></p>',
            ],
            [
                'event_type' => 'rejected_screening',
                'subject' => '[DOSCOM OpRec] Hasil Screening',
                'body_html' => '<p>Halo {{applicant_name}},</p><p>Terima kasih telah mengikuti OpenRecruitment DOSCOM. Mohon maaf, kamu belum lolos tahap screening.</p><p>Pantau informasi di <a href="{{tracking_url}}">{{tracking_url}}</a></p>',
            ],
            [
                'event_type' => 'final_rejected',
                'subject' => '[DOSCOM OpRec] Hasil OpenRecruitment',
                'body_html' => '<p>Halo {{applicant_name}},</p><p>Terima kasih telah mengikuti OpenRecruitment DOSCOM.</p>',
            ],
        ];

        foreach ($templates as $template) {
            RecruitmentEmailTemplate::query()->updateOrCreate(
                ['event_type' => $template['event_type']],
                [
                    'subject' => $template['subject'],
                    'body_html' => $template['body_html'],
                    'body_text' => strip_tags($template['body_html']),
                    'available_variables' => ['applicant_name', 'registration_number', 'period_name', 'tracking_url', 'tracking_token'],
                    'is_active' => true,
                ],
            );
        }
    }
}
