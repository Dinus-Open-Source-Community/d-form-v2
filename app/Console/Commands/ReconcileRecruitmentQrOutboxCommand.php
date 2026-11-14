<?php

namespace App\Console\Commands;

use App\Enums\EmailLogStatus;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentQrDispatchOutbox;
use App\Support\LogRedaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Operator reconciliation for an ambiguous QR delivery. */
class ReconcileRecruitmentQrOutboxCommand extends Command
{
    protected $signature = 'recruitment:reconcile-qr-outbox
        {emailLogId : email_logs.id of the QR intent}
        {state : sent when the mail was delivered, failed when it was not}';

    protected $description = 'Record the operator-confirmed outcome of an ambiguous recruitment QR delivery';

    /** Flip the outbox and its email log together; refuse any row that is not ambiguous. */
    public function handle(): int
    {
        $emailLogId = (string) $this->argument('emailLogId');
        $state = (string) $this->argument('state');

        if (! in_array($state, ['sent', 'failed'], true)) {
            $this->error('State must be "sent" or "failed".');

            return self::INVALID;
        }

        $outcome = DB::transaction(function () use ($emailLogId, $state): string {
            $outbox = RecruitmentQrDispatchOutbox::query()
                ->whereKey($emailLogId)
                ->lockForUpdate()
                ->first();

            if ($outbox === null) {
                return 'missing';
            }

            if ($outbox->status !== RecruitmentQrDispatchOutbox::STATUS_SENDING) {
                return 'not_sending:'.$outbox->status;
            }

            $delivered = $state === 'sent';
            $now = now();

            $outbox->forceFill([
                'status' => $delivered
                    ? RecruitmentQrDispatchOutbox::STATUS_SENT
                    : RecruitmentQrDispatchOutbox::STATUS_FAILED,
                'sent_at' => $delivered ? $now : null,
                'failed_at' => $delivered ? null : $now,
            ])->save();

            $log = EmailLog::query()->whereKey($emailLogId)->lockForUpdate()->first();

            if ($log !== null) {
                $log->forceFill([
                    'status' => $delivered ? EmailLogStatus::Sent : EmailLogStatus::Failed,
                    'error_message' => $delivered ? null : LogRedaction::MAIL_DELIVERY_FAILURE_MESSAGE,
                    'sent_at' => $delivered ? $now : null,
                ])->save();
            }

            return $delivered ? 'reconciled_sent' : 'reconciled_failed';
        });

        if ($outcome === 'missing') {
            $this->error("No recruitment QR outbox row for email log {$emailLogId}.");

            return self::FAILURE;
        }

        if (str_starts_with($outcome, 'not_sending:')) {
            $status = substr($outcome, strlen('not_sending:'));

            $this->error("Intent {$emailLogId} is '{$status}', not 'sending'; nothing to reconcile.");

            return self::FAILURE;
        }

        $this->info($outcome === 'reconciled_sent'
            ? "Intent {$emailLogId} recorded as sent."
            : "Intent {$emailLogId} recorded as failed; the applicant is eligible for a resend.");

        return self::SUCCESS;
    }
}
