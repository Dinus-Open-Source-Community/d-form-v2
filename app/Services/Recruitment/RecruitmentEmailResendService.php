<?php

namespace App\Services\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\Recruitment\ApplicationResult;
use App\Jobs\Recruitment\SendRecruitmentApplicationConfirmationJob;
use App\Jobs\Recruitment\SendRecruitmentCorrectionRequestStaffJob;
use App\Jobs\Recruitment\SendRecruitmentInterviewerNotificationJob;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentActivityLog;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\Recruitment\RecruitmentInterview;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class RecruitmentEmailResendService
{
    /** @var list<string> */
    public const TYPES = ['tracking', 'confirmation', 'correction', 'interviewer', 'notification', 'qr', 'final'];

    /** @var array<string, string> */
    public const RESENDABLE_TEMPLATES = [
        'recruitment_passed_screening' => 'passed_screening',
        'recruitment_rejected_screening' => 'rejected_screening',
        'recruitment_interview_scheduled' => 'interview_scheduled',
        'recruitment_interview_rescheduled' => 'interview_rescheduled',
        'recruitment_final_accepted' => 'final_accepted',
        'recruitment_final_rejected' => 'final_rejected',
    ];

    private const MAX_PER_DAY = 3;

    public function __construct(
        private readonly RecruitmentTrackingResendService $trackingResendService,
        private readonly RecruitmentTrackingTokenGenerator $tokenGenerator,
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    /** Kirim ulang satu jenis email applicant (throttle 3x/hari per jenis). */
    public function resend(EmailResendCommand $command): void
    {
        $this->throwUnlessUnderDailyLimit($command);

        $this->activityLogger->log(
            action: 'email.resend',
            actor: $command->actor,
            application: $command->application,
            newValues: [
                'type' => $command->type,
                'recipient_email' => $command->application->personal_email,
            ],
            request: $command->request,
        );

        match ($command->type) {
            'tracking' => $this->trackingResendService->resend($command->actor, $command->application, $command->request),
            'confirmation' => $this->resendConfirmation($command),
            'correction' => $this->resendCorrection($command),
            'interviewer' => $this->resendInterviewer($command),
            'notification' => $this->resendNotification($command),
            'qr' => $this->resendQr($command),
            'final' => $this->resendFinal($command),
            default => throw ValidationException::withMessages([
                'type' => ['Jenis resend tidak dikenal.'],
            ]),
        };
    }

    /** Kirim ulang konfirmasi pendaftaran dengan token tracking baru. */
    private function resendConfirmation(EmailResendCommand $command): void
    {
        $token = $this->tokenGenerator->generate();

        $command->application->update(['tracking_token_hash' => Hash::make($token)]);

        SendRecruitmentApplicationConfirmationJob::dispatch($command->application->id, $token);
    }

    /** Kirim ulang notifikasi koreksi ke staff untuk koreksi terbaru applicant. */
    private function resendCorrection(EmailResendCommand $command): void
    {
        $correction = RecruitmentCorrectionRequest::query()
            ->where('recruitment_application_id', $command->application->id)
            ->orderByDesc('created_at')
            ->first();

        if ($correction === null) {
            throw ValidationException::withMessages([
                'application' => ['Belum ada permintaan koreksi untuk applicant ini.'],
            ]);
        }

        SendRecruitmentCorrectionRequestStaffJob::dispatch($correction->id);
    }

    /** Kirim ulang penugasan ke interviewer pada interview terbaru applicant. */
    private function resendInterviewer(EmailResendCommand $command): void
    {
        $interview = RecruitmentInterview::query()
            ->where('recruitment_application_id', $command->application->id)
            ->whereNotNull('interviewer_id')
            ->orderByDesc('created_at')
            ->first();

        if ($interview === null) {
            throw ValidationException::withMessages([
                'application' => ['Belum ada interview terjadwal untuk applicant ini.'],
            ]);
        }

        SendRecruitmentInterviewerNotificationJob::dispatch($interview->id);
    }

    /** Kirim ulang notifikasi terakhir yang terkirim untuk applicant. */
    private function resendNotification(EmailResendCommand $command): void
    {
        $lastSent = EmailLog::query()
            ->where('recruitment_application_id', $command->application->id)
            ->where('status', EmailLogStatus::Sent)
            ->orderByDesc('created_at')
            ->first();

        $lastType = $lastSent?->notification_type?->value;
        $templateKey = is_string($lastType) ? (self::RESENDABLE_TEMPLATES[$lastType] ?? null) : null;

        if ($templateKey === null) {
            throw ValidationException::withMessages([
                'application' => ['Belum ada notifikasi yang bisa dikirim ulang untuk applicant ini.'],
            ]);
        }

        $interviewId = null;

        if (in_array($templateKey, ['interview_scheduled', 'interview_rescheduled'], true)) {
            $interviewId = RecruitmentInterview::query()
                ->where('recruitment_application_id', $command->application->id)
                ->orderByDesc('created_at')
                ->first()?->id;

            if ($interviewId === null) {
                throw ValidationException::withMessages([
                    'application' => ['Notifikasi ini butuh data interview yang sudah tidak ada.'],
                ]);
            }
        }

        SendRecruitmentNotificationJob::dispatch(
            $command->application->id,
            $templateKey,
            $interviewId,
            null,
            null,
            $templateKey === 'passed_screening' ? $command->application->period?->whatsapp_group_url : null,
        );
    }

    /** Kirim ulang email jadwal interview beserta QR presensi. */
    private function resendQr(EmailResendCommand $command): void
    {
        $interview = RecruitmentInterview::query()
            ->where('recruitment_application_id', $command->application->id)
            ->orderByDesc('created_at')
            ->first();

        if ($interview === null) {
            throw ValidationException::withMessages([
                'application' => ['Belum ada jadwal interview untuk applicant ini.'],
            ]);
        }

        SendRecruitmentNotificationJob::dispatch(
            $command->application->id,
            'interview_scheduled',
            $interview->id,
        );
    }

    /** Kirim ulang pengumuman hasil akhir (diterima/ditolak). */
    private function resendFinal(EmailResendCommand $command): void
    {
        $application = $command->application->loadMissing(['period', 'finalDecision.finalDivision']);

        $templateKey = match ($application->result) {
            ApplicationResult::Accepted => 'final_accepted',
            ApplicationResult::Rejected => 'final_rejected',
            default => null,
        };

        if ($templateKey === null) {
            throw ValidationException::withMessages([
                'application' => ['Hasil akhir pendaftar belum ditentukan (masih pending).'],
            ]);
        }

        SendRecruitmentNotificationJob::dispatch(
            $application->id,
            $templateKey,
            null,
            null,
            null,
            $templateKey === 'final_accepted' ? $application->period?->whatsapp_group_url : null,
        );
    }

    /** Tolak bila sudah 3x resend jenis ini untuk applicant dalam 24 jam. */
    private function throwUnlessUnderDailyLimit(EmailResendCommand $command): void
    {
        $sent = RecruitmentActivityLog::query()
            ->where('recruitment_application_id', $command->application->id)
            ->where('action', 'email.resend')
            ->where('created_at', '>=', now()->subDay())
            ->where('new_values->type', $command->type)
            ->count();

        if ($sent >= self::MAX_PER_DAY) {
            abort(429, 'Batas kirim ulang tercapai (maks 3x sehari per jenis). Coba lagi besok.');
        }
    }
}
