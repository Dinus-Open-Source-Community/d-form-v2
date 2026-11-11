<?php

namespace App\Services\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\Recruitment\ApplicationResult;
use App\Jobs\Recruitment\SendRecruitmentCorrectionRequestStaffJob;
use App\Jobs\Recruitment\SendRecruitmentInterviewerNotificationJob;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentActivityLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\Recruitment\RecruitmentInterview;
use Illuminate\Support\Facades\DB;
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

    public const MAX_PER_DAY = 3;

    public const DAILY_LIMIT_MESSAGE = 'Batas kirim ulang tercapai (maks 3x sehari per jenis). Coba lagi besok.';

    public function __construct(
        private readonly RecruitmentTrackingResendService $trackingResendService,
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    /** Kirim ulang satu jenis email applicant (throttle 3x/hari per jenis). */
    public function resend(EmailResendCommand $command): void
    {
        if ($command->type === 'tracking') {
            $this->trackingResendService->resend(
                $command->actor,
                $command->application,
                $command->request,
            );

            return;
        }

        if ($command->type === 'confirmation') {
            $this->resendConfirmation($command);

            return;
        }

        DB::transaction(function () use ($command): void {
            $application = RecruitmentApplication::query()
                ->whereKey($command->application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->throwUnlessUnderDailyLimit($command);

            $this->activityLogger->log(
                action: 'email.resend',
                actor: $command->actor,
                application: $application,
                newValues: [
                    'type' => $command->type,
                ],
                request: $command->request,
            );
        });

        match ($command->type) {
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
        $this->trackingResendService->resendConfirmation(
            $command->actor,
            $command->application,
            $command->request,
        );
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

    /**
     * Template key notifikasi terkirim terbaru yang bisa dikirim ulang, atau `null`.
     *
     * Satu-satunya sumber aturan "replayable": log terbaru boleh non-resendable (mis.
     * konfirmasi setelah resend), jadi pencarian melewatinya dan berhenti di log pertama
     * yang tipenya ada di allowlist. Template interview tetap butuh interview yang ada.
     *
     * @param  bool|null  $hasInterview  Hasil query pemanggil; `null` berarti helper yang mencari.
     */
    public function latestReplayableNotification(RecruitmentApplication $application, ?bool $hasInterview = null): ?string
    {
        $types = EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('status', EmailLogStatus::Sent)
            ->whereIn('notification_type', array_keys(self::RESENDABLE_TEMPLATES))
            ->orderByDesc('created_at')
            ->pluck('notification_type');

        foreach ($types as $type) {
            $value = $type instanceof EmailNotificationType ? $type->value : (string) $type;
            $templateKey = self::RESENDABLE_TEMPLATES[$value];

            if (! in_array($templateKey, ['interview_scheduled', 'interview_rescheduled'], true)) {
                return $templateKey;
            }

            $hasInterview ??= RecruitmentInterview::query()
                ->where('recruitment_application_id', $application->id)
                ->whereNotNull('interviewer_id')
                ->exists();

            if ($hasInterview) {
                return $templateKey;
            }
        }

        return null;
    }

    /** Kirim ulang notifikasi terakhir yang terkirim untuk applicant. */
    private function resendNotification(EmailResendCommand $command): void
    {
        $templateKey = $this->latestReplayableNotification($command->application);

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
            abort(429, self::DAILY_LIMIT_MESSAGE);
        }
    }
}
