<?php

namespace App\Jobs\Recruitment;

use App\Enums\EmailNotificationType;
use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\RecruitmentEmailRenderer;
use App\Services\Recruitment\RecruitmentTrackingPortalUrlBuilder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRecruitmentApplicationConfirmationJob implements ShouldQueue, ShouldBeEncrypted
{
    use AppliesOutgoingEmailDelay;
    use Queueable;
    use TracksOutgoingEmail;

    public ?int $trackingTokenGeneration = null;

    public function __construct(
        public string $applicationId,
        public string $trackingToken,
        ?int $trackingTokenGeneration = null,
    ) {
        $this->trackingTokenGeneration = $trackingTokenGeneration;
        $this->initializeEmailDelivery();
    }

    /** Deliver the confirmation and activate only the latest resend generation. */
    public function handle(
        RecruitmentEmailRenderer $renderer,
        RecruitmentTrackingPortalUrlBuilder $portalUrlBuilder,
    ): void {
        $application = RecruitmentApplication::query()
            ->with(['period', 'primaryDivision'])
            ->find($this->applicationId);

        if ($application === null) {
            Log::warning('[SendRecruitmentApplicationConfirmationJob] Application not found.', [
                'application_id' => $this->applicationId,
            ]);

            return;
        }

        if ($this->trackingTokenGeneration !== null
            && (int) $application->tracking_token_generation !== $this->trackingTokenGeneration) {
            Log::info('[SendRecruitmentApplicationConfirmationJob] Stale tracking resend skipped.', [
                'application_id' => $application->id,
                'requested_generation' => $this->trackingTokenGeneration,
                'current_generation' => $application->tracking_token_generation,
            ]);

            return;
        }

        $recipientEmail = $application->personal_email;
        $trackingUrl = url(route('recruitment.track.login', absolute: false));
        $trackingPortalUrl = $portalUrlBuilder->loginUrl(
            $application->registration_number,
            $this->trackingToken,
        );

        $variables = [
            'applicant_name' => $application->full_name,
            'registration_number' => $application->registration_number,
            'period_name' => $application->period?->name ?? 'OpenRecruitment DOSCOM',
            'organization_name' => 'DOSCOM',
            'nim' => $application->nim,
            'semester' => (string) $application->semester,
            'primary_division' => $application->primaryDivision?->name ?? '',
            'tracking_url' => $trackingUrl,
            'tracking_portal_url' => $trackingPortalUrl,
            'tracking_portal_button' => $portalUrlBuilder->loginButtonHtml($trackingPortalUrl),
            'tracking_token' => $this->trackingToken,
        ];

        if ($recipientEmail === '') {
            $this->markEmailLogUndeliverable([
                'recruitment_application_id' => $application->id,
                'event_id' => null,
                'user_id' => null,
                'recipient_email' => '',
                'notification_type' => EmailNotificationType::RecruitmentApplicationSubmitted,
            ], 'No recipient email address configured.');

            return;
        }

        $rendered = $renderer->renderTemplate('application_submitted', $variables);

        $logAttributes = [
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => $recipientEmail,
            'notification_type' => EmailNotificationType::RecruitmentApplicationSubmitted,
        ];

        $log = $this->reserveEmailLog($logAttributes);

        if ($log === null) {
            return;
        }

        try {
            Mail::to($recipientEmail)->send(new RecruitmentApplicationConfirmationMail(
                subjectLine: $rendered['subject'],
                bodyHtml: $rendered['body_html'],
                bodyText: $rendered['body_text'],
                headline: 'Pendaftaran diterima',
            ));

            // A late job must not replace the token requested by a newer resend.
            if ($this->trackingTokenGeneration !== null) {
                DB::transaction(function (): void {
                    $currentApplication = RecruitmentApplication::query()
                        ->whereKey($this->applicationId)
                        ->lockForUpdate()
                        ->first();

                    if ($currentApplication === null
                        || (int) $currentApplication->tracking_token_generation !== $this->trackingTokenGeneration) {
                        return;
                    }

                    $currentApplication->forceFill([
                        'tracking_token_hash' => Hash::make($this->trackingToken),
                    ])->save();
                });
            }

            $this->markEmailLogSent($log, $recipientEmail);
        } catch (\Throwable $exception) {
            $this->markEmailLogFailed($log, $exception);

            Log::error('[SendRecruitmentApplicationConfirmationJob] Email send failed.', [
                'notification_type' => EmailNotificationType::RecruitmentApplicationSubmitted->value,
                'application_id' => $application->id,
                'delivery_key' => $this->deliveryKey,
                'exception_class' => $exception::class,
            ]);

            $this->throwRedacted($exception);
        }
    }
}
