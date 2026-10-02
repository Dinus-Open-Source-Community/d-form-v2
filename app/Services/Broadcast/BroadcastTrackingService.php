<?php

namespace App\Services\Broadcast;

use App\Enums\EmailLogStatus;
use App\Models\Broadcast;
use App\Models\EmailLog;

final class BroadcastTrackingService
{
    /** Tracking per-penerima dari snapshot + EmailLog existing (tanpa tabel baru). */
    public function for(Broadcast $broadcast): array
    {
        $logsByEmail = EmailLog::query()
            ->where('broadcast_id', $broadcast->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('recipient_email');

        $rows = [];

        foreach (array_values($broadcast->recipient_snapshot['recipients'] ?? []) as $recipient) {
            $email = $recipient['email'] ?? null;

            if (! is_string($email) || $email === '') {
                continue;
            }

            $attempts = $logsByEmail->get($email, collect());
            $latest = $attempts->last();
            $sent = $attempts->where('status', EmailLogStatus::Sent)->last();
            $failed = $attempts->where('status', EmailLogStatus::Failed)->last();

            $rows[] = [
                'email' => $email,
                'name' => $recipient['name'] ?? null,
                'status' => $latest?->status?->value ?? 'pending',
                'attempts' => $attempts->count(),
                'sent_at' => ($sent?->sent_at ?? $sent?->created_at)?->toIso8601String(),
                'failed_at' => $failed?->created_at?->toIso8601String(),
            ];
        }

        $byStatus = array_count_values(array_column($rows, 'status'));

        return [
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'sent' => $byStatus[EmailLogStatus::Sent->value] ?? 0,
                'failed' => $byStatus[EmailLogStatus::Failed->value] ?? 0,
                'pending' => $byStatus['pending'] ?? 0,
            ],
        ];
    }
}
