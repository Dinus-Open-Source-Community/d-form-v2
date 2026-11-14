<?php

namespace App\Support\E2e;

use App\Enums\EmailBroadcastStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Menulis broadcast email ber-marker (`email_broadcasts.name` bersufiks marker). */
final class BroadcastFixtureWriter
{
    public function __construct(
        private readonly FixtureIdentity $identity,
        private readonly FixtureJournal $journal,
    ) {
    }

    /**
     *
     * @param  array<string, mixed>  $payload  Payload fixture penuh (membaca `broadcasts`)
     * @return list<array{id: string, name: string, recipientIds: list<string>}>
     *
     */
    public function create(array $payload, ?string $createdByUserId, Carbon $now): array
    {
        $entries = $payload['broadcasts'] ?? [];

        if (! is_array($entries)) {
            throw new \InvalidArgumentException('payload.broadcasts must be an array.');
        }

        $results = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                throw new \InvalidArgumentException('each payload.broadcasts entry must be an object.');
            }

            $name = (string) ($entry['name'] ?? '');

            if ($name === '') {
                throw new \InvalidArgumentException('payload.broadcasts.name must be a non-empty string.');
            }

            $status = (string) ($entry['status'] ?? EmailBroadcastStatus::Draft->value);

            // Status yang butuh scheduled_at/total_recipients tidak didukung fixture; pakai dispatch nyata.
            if (! in_array($status, [
                EmailBroadcastStatus::Draft->value,
                EmailBroadcastStatus::Cancelled->value,
                EmailBroadcastStatus::Failed->value,
            ], true)) {
                throw new \InvalidArgumentException(
                    'payload.broadcasts.status only supports draft, cancelled, or failed.',
                );
            }

            $broadcastId = $this->createBroadcast($name, $status, $entry, $createdByUserId, $now);
            $recipientIds = $this->createRecipients($broadcastId, $entry['recipients'] ?? [], $now);

            $results[] = [
                'id' => $broadcastId,
                'name' => $this->identity->suffixed($name),
                'recipientIds' => $recipientIds,
            ];
        }

        return $results;
    }

    /**
     *
     * @param  array<string, mixed>  $entry
     *
     */
    private function createBroadcast(string $name, string $status, array $entry, ?string $createdByUserId, Carbon $now): string
    {
        $title = $this->identity->suffixed($name);
        $existing = DB::table('email_broadcasts')->where('name', $title)->value('id');

        if ($existing !== null) {
            return (string) $existing;
        }

        $broadcastId = (string) Str::orderedUuid();
        DB::table('email_broadcasts')->insert([
            'id' => $broadcastId,
            'name' => $title,
            'subject' => $entry['subject'] ?? null,
            'content' => $entry['content'] ?? null,
            'status' => $status,
            'created_by' => $createdByUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->journal->record('email_broadcasts', $broadcastId);

        return $broadcastId;
    }

    /**
     *
     * @return list<string>
     *
     */
    private function createRecipients(string $broadcastId, mixed $recipients, Carbon $now): array
    {
        if (! is_array($recipients)) {
            throw new \InvalidArgumentException('payload.broadcasts.recipients must be an array.');
        }

        $ids = [];

        foreach ($recipients as $recipient) {
            if (! is_array($recipient)) {
                throw new \InvalidArgumentException('each payload.broadcasts.recipients entry must be an object.');
            }

            $email = (string) ($recipient['email'] ?? '');

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('payload.broadcasts.recipients.email must be a valid email address.');
            }

            $existing = DB::table('email_broadcast_recipients')
                ->where('broadcast_id', $broadcastId)
                ->where('email', $email)
                ->value('id');

            if ($existing !== null) {
                $ids[] = (string) $existing;

                continue;
            }

            $recipientId = (string) Str::orderedUuid();
            DB::table('email_broadcast_recipients')->insert([
                'id' => $recipientId,
                'broadcast_id' => $broadcastId,
                'name' => $recipient['name'] ?? null,
                'email' => $email,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->journal->record('email_broadcast_recipients', $recipientId);
            $ids[] = $recipientId;
        }

        return $ids;
    }
}
