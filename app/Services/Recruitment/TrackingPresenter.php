<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;

final class TrackingPresenter
{
    public function __construct(
        private readonly ApplicationEditGate $editGate,
        private readonly RecruitmentQrPngGenerator $qrGenerator,
        private readonly FeedbackService $feedbackService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(RecruitmentApplication $application): array
    {
        $application->loadMissing([
            'period',
            'primaryDivision',
            'secondaryDivision',
            'interview',
            'queueEntry',
            'attendance',
            'finalDecision.finalDivision',
            'correctionRequests',
            'feedback',
        ]);

        $latestCorrection = $application->correctionRequests
            ->sortByDesc('created_at')
            ->first();

        $edit = [
            'can_edit' => $this->editGate->canEdit($application),
            'can_request_correction' => $this->editGate->canRequestCorrection($application),
            'latest_correction' => $latestCorrection ? [
                'id' => $latestCorrection->id,
                'status' => $latestCorrection->status->value,
                'status_label' => $latestCorrection->status->label(),
                'request_message' => $latestCorrection->request_message,
                'review_notes' => $latestCorrection->review_notes,
            ] : null,
        ];

        $feedback = [
            'can_submit' => $this->feedbackService->canSubmit($application),
            'submitted' => $application->feedback !== null,
            'submitted_at' => $application->feedback?->submitted_at?->toIso8601String(),
        ];

        $interview = $this->presentInterview($application);
        $attendanceQr = $this->presentAttendanceQrBase64($application);

        return [
            'application' => $this->presentApplication($application),
            'period' => [
                'name' => $application->period?->name,
            ],
            'next_action' => $this->presentNextAction($application, $edit, $feedback, $interview, $attendanceQr),
            'timeline' => $this->presentTimeline($application),
            'interview' => $interview,
            'attendance' => $this->presentAttendance($application),
            'attendance_qr_base64' => $attendanceQr,
            'queue' => $this->presentQueue($application),
            'final' => $this->presentFinal($application),
            'edit' => $edit,
            'feedback' => $feedback,
        ];
    }

    /**
     * @param  array<string, mixed>  $edit
     * @param  array<string, mixed>  $feedback
     * @param  array<string, mixed>|null  $interview
     * @return array{tone: string, title: string, description: string, action: string|null}
     */
    private function presentNextAction(
        RecruitmentApplication $application,
        array $edit,
        array $feedback,
        ?array $interview,
        ?string $attendanceQr,
    ): array {
        if ($edit['can_edit']) {
            return [
                'tone' => 'warning',
                'title' => 'Perbarui pendaftaran kamu',
                'description' => 'Tim meminta revisi data. Lengkapi formulir lalu kirim ulang sebelum batas waktu.',
                'action' => 'edit',
            ];
        }

        if ($edit['latest_correction'] !== null && ($edit['latest_correction']['status'] ?? '') === 'pending') {
            return [
                'tone' => 'info',
                'title' => 'Permintaan koreksi sedang ditinjau',
                'description' => 'Tim akan memberi kabar lewat email setelah permintaanmu diproses.',
                'action' => null,
            ];
        }

        if ($feedback['can_submit']) {
            return [
                'tone' => 'success',
                'title' => 'Isi feedback OpRec',
                'description' => 'Proses recruitment selesai. Bantu kami evaluasi pengalamanmu (±2 menit).',
                'action' => 'feedback',
            ];
        }

        if ($application->stage === ApplicationStage::Completed) {
            if ($application->result === ApplicationResult::Accepted) {
                return [
                    'tone' => 'success',
                    'title' => 'Selamat! Kamu diterima',
                    'description' => 'Lihat detail keanggotaan dan divisi di bawah.',
                    'action' => 'final',
                ];
            }

            if ($application->result === ApplicationResult::Rejected) {
                return [
                    'tone' => 'neutral',
                    'title' => 'Proses recruitment selesai',
                    'description' => 'Terima kasih sudah ikut OpenRecruitment DOSCOM.',
                    'action' => 'final',
                ];
            }
        }

        if ($application->stage === ApplicationStage::FinalReview) {
            return [
                'tone' => 'info',
                'title' => 'Menunggu keputusan akhir',
                'description' => 'Interview selesai. Tim sedang memfinalisasi hasil seleksi.',
                'action' => null,
            ];
        }

        if ($application->stage === ApplicationStage::Interview) {
            $queue = $application->queueEntry;

            if ($queue !== null && in_array($queue->status->value, ['called', 'in_progress'], true)) {
                return [
                    'tone' => 'warning',
                    'title' => 'Giliran interview kamu',
                    'description' => 'Silakan menuju ruang interview sesuai panggilan panitia.',
                    'action' => 'queue',
                ];
            }

            if ($queue !== null) {
                return [
                    'tone' => 'info',
                    'title' => 'Nomor antrean #'.$queue->queue_number,
                    'description' => 'Status: '.$queue->status->label().'. Tunggu panggilan panitia.',
                    'action' => 'queue',
                ];
            }

            if ($application->attendance !== null) {
                return [
                    'tone' => 'info',
                    'title' => 'Sudah check-in',
                    'description' => 'Menunggu nomor antrean dari panitia.',
                    'action' => null,
                ];
            }

            if ($attendanceQr !== null) {
                return [
                    'tone' => 'warning',
                    'title' => 'Bawa QR absensi ke lokasi',
                    'description' => 'Tunjukkan QR code ke panitia saat tiba — scroll ke bagian interview.',
                    'action' => 'qr',
                ];
            }

            if ($interview !== null) {
                $when = $interview['scheduled_at'] !== null
                    ? \Illuminate\Support\Carbon::parse($interview['scheduled_at'])
                        ->timezone(config('app.timezone'))
                        ->locale('id')
                        ->translatedFormat('l, j F Y · H:i')
                    : null;

                return [
                    'tone' => 'info',
                    'title' => 'Interview dijadwalkan',
                    'description' => $when !== null
                        ? $when.' · '.$interview['location'].' · Ruang '.$interview['room']
                        : 'Cek detail jadwal di bagian interview.',
                    'action' => 'interview',
                ];
            }

            return [
                'tone' => 'info',
                'title' => 'Menunggu jadwal interview',
                'description' => 'Tim akan mengirim jadwal lewat email. Pantau halaman ini.',
                'action' => null,
            ];
        }

        if ($application->revision_required) {
            return [
                'tone' => 'warning',
                'title' => 'Revisi diperlukan',
                'description' => 'Cek email untuk instruksi dari tim screening.',
                'action' => null,
            ];
        }

        if (in_array($application->stage, [ApplicationStage::Submitted, ApplicationStage::Screening], true)) {
            return [
                'tone' => 'info',
                'title' => 'Menunggu screening',
                'description' => 'Tim sedang meninjau pendaftaran. Update akan muncul di sini dan via email.',
                'action' => null,
            ];
        }

        return [
            'tone' => 'neutral',
            'title' => $application->stage->label(),
            'description' => $application->result->label(),
            'action' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentApplication(RecruitmentApplication $application): array
    {
        return [
            'registration_number' => $application->registration_number,
            'full_name' => $application->full_name,
            'nim' => $application->nim,
            'semester' => $application->semester,
            'phone' => $application->phone,
            'personal_email' => $application->personal_email,
            'student_email' => $application->student_email,
            'instagram_username' => $application->instagram_username,
            'primary_division' => $application->primaryDivision?->name,
            'secondary_division' => $application->secondaryDivision?->name,
            'stage' => $application->stage->value,
            'stage_label' => $application->stage->label(),
            'result' => $application->result->value,
            'result_label' => $application->result->label(),
            'revision_required' => $application->revision_required,
            'is_verified' => $application->is_verified,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentTimeline(RecruitmentApplication $application): array
    {
        $currentStage = $application->stage;
        $currentIndex = array_search($currentStage, ApplicationStage::timelineOrder(), true);

        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $items = [];

        foreach (ApplicationStage::timelineOrder() as $index => $stage) {
            $status = match (true) {
                $index < $currentIndex => 'completed',
                $index === $currentIndex => 'current',
                default => 'upcoming',
            };

            if ($stage === ApplicationStage::Completed && $application->result->value !== 'pending') {
                $status = 'completed';
            }

            $items[] = [
                'key' => $stage->value,
                'label' => $stage->label(),
                'status' => $status,
                'note' => $stage === ApplicationStage::Screening && $application->revision_required
                    ? 'Perlu revisi data — tim akan menghubungi kamu.'
                    : null,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentInterview(RecruitmentApplication $application): ?array
    {
        $interview = $application->interview;

        if ($interview === null) {
            return null;
        }

        $status = $interview->status instanceof InterviewStatus
            ? $interview->status
            : InterviewStatus::tryFrom((string) $interview->status);

        return [
            'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
            'location' => $interview->location,
            'room' => $interview->room,
            'status' => $status?->value ?? (string) $interview->status,
            'status_label' => $status?->label() ?? (string) $interview->status,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentAttendance(RecruitmentApplication $application): ?array
    {
        $attendance = $application->attendance;

        if ($attendance === null) {
            return null;
        }

        return [
            'checked_in_at' => $attendance->checked_in_at?->toIso8601String(),
            'method' => $attendance->method instanceof \App\Enums\Recruitment\AttendanceMethod
                ? $attendance->method->value
                : (string) $attendance->method,
        ];
    }

    private function presentAttendanceQrBase64(RecruitmentApplication $application): ?string
    {
        if ($application->attendance !== null) {
            return null;
        }

        $interview = $application->interview;

        if ($interview === null) {
            return null;
        }

        $status = $interview->status instanceof InterviewStatus
            ? $interview->status
            : InterviewStatus::tryFrom((string) $interview->status);

        if ($status !== InterviewStatus::Scheduled) {
            return null;
        }

        try {
            return base64_encode($this->qrGenerator->pngForApplication($application->id));
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentQueue(RecruitmentApplication $application): ?array
    {
        $queue = $application->queueEntry;

        if ($queue === null) {
            return null;
        }

        return [
            'queue_number' => $queue->queue_number,
            'status' => $queue->status instanceof \App\Enums\Recruitment\QueueStatus
                ? $queue->status->value
                : (string) $queue->status,
            'status_label' => $queue->status instanceof \App\Enums\Recruitment\QueueStatus
                ? $queue->status->label()
                : (string) $queue->status,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentFinal(RecruitmentApplication $application): ?array
    {
        if ($application->stage !== ApplicationStage::Completed) {
            return null;
        }

        $decision = $application->finalDecision;

        if ($decision === null && $application->result->value === 'rejected') {
            return [
                'membership_type' => null,
                'final_division' => null,
                'public_message' => null,
                'result' => $application->result->value,
                'result_label' => $application->result->label(),
            ];
        }

        if ($decision === null) {
            return null;
        }

        return [
            'membership_type' => $decision->membership_type,
            'final_division' => $decision->finalDivision?->name,
            'public_message' => $decision->public_message,
            'result' => $application->result->value,
            'result_label' => $application->result->label(),
        ];
    }
}
