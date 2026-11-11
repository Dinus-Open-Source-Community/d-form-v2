<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

final class EventExportAuditLogger
{
    /** Record safe metadata for one completed event CSV export. */
    public function record(string|int|null $actorId, string $eventId, string $exportType, int $rowCount): void
    {
        $context = [
            'actor_id' => $actorId,
            'event_id' => $eventId,
            'export_type' => $exportType,
            'row_count' => $rowCount,
        ];

        try {
            Log::info('event_csv_export.generated', $context);
        } catch (Throwable $exception) {
            $failureContext = [
                'actor_id' => $actorId,
                'event_id' => $eventId,
                'export_type' => $exportType,
                'exception_class' => $exception::class,
            ];

            try {
                Log::warning('event_csv_export_audit_failed', $failureContext);
            } catch (Throwable) {
                error_log('event_csv_export_audit_failed '.$exception::class);
            }
        }
    }
}
