<?php

namespace App\Console\Commands\E2e;

use App\Console\Commands\E2e\Concerns\ResolvesFixtureIdentity;
use App\Support\E2e\FixtureIdentity;
use App\Support\E2e\RunLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Tulis fixture e2e (user, event, form, field, answer, attendance) yang seluruhnya ber-marker.
 * Payload JSON dibaca dari stdin; setiap baris dicatat di journal agar kegagalan parsial
 * dibersihkan terbalik dalam satu transaksi.
 */
final class FixtureCommand extends Command
{
    use ResolvesFixtureIdentity;

    protected $signature = 'e2e:fixture
        {--marker= : Marker kanonik e2e+<runId>+o<12 hex>}
        {--run-id= : runId bila marker diturunkan dari ownerId}
        {--owner-id= : ownerId bila marker diturunkan dari ownerId}
        {--json : Cetak hasil sebagai JSON}';

    protected $description = 'Create marker-owned e2e fixture rows from a JSON payload on stdin.';

    /** @var list<array{table: string, id: string}> */
    private array $journal = [];

    public function handle(): int
    {
        $identity = $this->resolveIdentity();
        $payload = $this->readPayload();
        $forms = $this->normalizeForms($payload);

        // Baseline ditulis sebelum baris pertama: batas window illegal-marker milik run ini.
        (new RunLedger())->recordBaseline($identity);

        try {
            $ids = $this->create($identity, $payload, $forms);
        } catch (\Throwable $exception) {
            $this->rollbackJournal();
            throw $exception;
        }

        $this->line(json_encode($ids, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /**
     * Buat seluruh baris fixture dan kembalikan id yang dipakai spec.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<array{title: string, fields: list<array{label: string, name: string, input_type: string, required: bool}>, submissions: int}>  $forms
     * @return array<string, mixed>
     */
    private function create(FixtureIdentity $identity, array $payload, array $forms): array
    {
        $now = now();
        $userId = $this->firstId('users', ['email' => $identity->email()]);

        if ($userId === null) {
            $userId = (string) Str::orderedUuid();
            DB::table('users')->insert([
                'id' => $userId,
                'name' => 'E2E '.$identity->ownerKey,
                'email' => $identity->email(),
                'email_verified_at' => $now,
                'password' => Hash::make(Str::random(40)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->journal[] = ['table' => 'users', 'id' => $userId];
        }

        $eventId = $this->firstId('events', ['slug' => $identity->slug()]);

        if ($eventId === null) {
            $eventId = (string) Str::orderedUuid();
            DB::table('events')->insert([
                'id' => $eventId,
                'title' => ($payload['title'] ?? 'E2E fixture '.$identity->ownerKey),
                'slug' => $identity->slug(),
                'description' => '<p>E2E fixture</p>',
                'start_date' => $now->toDateString(),
                'end_date' => $now->copy()->addDay()->toDateString(),
                'registration_start' => $now->copy()->subDay(),
                'registration_end' => $now->copy()->addDay(),
                'location' => 'E2E / '.$identity->ownerKey,
                'quota' => 100,
                'registered_count' => 0,
                'banner' => 'events/banners/placeholder.jpg',
                'price' => 0,
                'session' => 'general',
                'status' => 'published',
                'category' => 'etc',
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->journal[] = ['table' => 'events', 'id' => $eventId];
        }

        $results = [];

        foreach ($forms as $formRequest) {
            $results[] = $this->createForm($identity, $eventId, $userId, $formRequest, $now);
        }

        return [
            'marker' => $identity->marker,
            'slug' => $identity->slug(),
            'userId' => $userId,
            'eventId' => $eventId,
            'eventSlug' => $identity->slug(),
            'forms' => $results,
        ];
    }

    /**
     * Buat satu form beserta field dan submission-nya (idempoten per judul ber-marker).
     *
     * @param  array{title: string, fields: list<array{label: string, name: string, input_type: string, required: bool}>, submissions: int}  $request
     * @return array{formId: string, fieldIds: list<string>, submissionIds: list<string>}
     */
    private function createForm(
        FixtureIdentity $identity,
        string $eventId,
        string $userId,
        array $request,
        \Illuminate\Support\Carbon $now,
    ): array {
        $title = $identity->suffixed($request['title']);
        $formId = $this->firstId('forms', ['event_id' => $eventId, 'title' => $title]);

        if ($formId === null) {
            $formId = (string) Str::orderedUuid();
            DB::table('forms')->insert([
                'id' => $formId,
                'title' => $title,
                'description' => '<p>E2E fixture form</p>',
                'visible_for' => json_encode(['public'], JSON_THROW_ON_ERROR),
                'closed_at' => null,
                'metadata' => json_encode(['marker' => $identity->marker], JSON_THROW_ON_ERROR),
                'event_id' => $eventId,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->journal[] = ['table' => 'forms', 'id' => $formId];
        }

        $fieldIds = [];

        foreach ($request['fields'] as $order => $field) {
            $label = $identity->suffixed($field['label']);
            $fieldId = $this->firstId('form_fields', ['form_id' => $formId, 'name' => $field['name']]);

            if ($fieldId === null) {
                $fieldId = (string) Str::orderedUuid();
                DB::table('form_fields')->insert([
                    'id' => $fieldId,
                    'input_type' => $field['input_type'],
                    'label' => $label,
                    'name' => $field['name'],
                    'metadata' => json_encode([
                        'rules' => ['required' => $field['required']],
                        'placeholder' => 'E2E',
                        'type' => 'text',
                    ], JSON_THROW_ON_ERROR),
                    'form_id' => $formId,
                    'order' => $order + 1,
                    'is_append' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->journal[] = ['table' => 'form_fields', 'id' => $fieldId];
            }

            $fieldIds[] = $fieldId;
        }

        $submissionIds = $this->createSubmissions($identity, $formId, $userId, $request['submissions'], $now);

        return ['formId' => $formId, 'fieldIds' => $fieldIds, 'submissionIds' => $submissionIds];
    }

    /**
     * Buat answer + attendance pemilik; baris answer yang sudah ada dipakai ulang.
     *
     * @return list<string>
     */
    private function createSubmissions(
        FixtureIdentity $identity,
        string $formId,
        string $userId,
        int $requested,
        \Illuminate\Support\Carbon $now,
    ): array {
        $existing = DB::table('form_answers')
            ->where('form_id', $formId)
            ->where('user_id', $userId)
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $missing = max(0, $requested - count($existing));

        $rows = [];
        $answerIds = [];

        for ($index = 0; $index < $missing; $index++) {
            $answerId = (string) Str::orderedUuid();
            $answerIds[] = $answerId;
            $rows[] = [
                'id' => $answerId,
                'answers' => json_encode(['field_1' => 'jawaban e2e'], JSON_THROW_ON_ERROR),
                'user_id' => $userId,
                'form_id' => $formId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('form_answers')->insert($chunk);

            foreach ($chunk as $row) {
                $this->journal[] = ['table' => 'form_answers', 'id' => $row['id']];
            }
        }

        $allAnswerIds = array_merge($existing, $answerIds);
        $attendanceRows = [];

        foreach ($allAnswerIds as $answerId) {
            $attendanceId = $this->firstId('event_attendances', ['form_answer_id' => $answerId]);

            if ($attendanceId !== null) {
                continue;
            }

            $attendanceRows[] = [
                'id' => (string) Str::orderedUuid(),
                'event_id' => DB::table('forms')->where('id', $formId)->value('event_id'),
                'form_answer_id' => $answerId,
                'scanned_by_user_id' => $userId,
                'scanned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($attendanceRows, 500) as $chunk) {
            DB::table('event_attendances')->insert($chunk);

            foreach ($chunk as $row) {
                $this->journal[] = ['table' => 'event_attendances', 'id' => $row['id']];
            }
        }

        return $allAnswerIds;
    }

    /**
     * Cari id pertama untuk predikat exact; `null` bila belum ada.
     *
     * @param  array<string, mixed>  $where
     */
    private function firstId(string $table, array $where): ?string
    {
        $id = DB::table($table)->where($where)->orderBy('id')->value('id');

        return $id === null ? null : (string) $id;
    }

    /**
     * Normalisasi payload `forms` dengan default yang aman untuk spec.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{title: string, fields: list<array{label: string, name: string, input_type: string, required: bool}>, submissions: int}>
     */
    private function normalizeForms(array $payload): array
    {
        $rawForms = $payload['forms'] ?? [];

        if (! is_array($rawForms)) {
            throw new \InvalidArgumentException('payload.forms must be an array.');
        }

        $forms = [];

        foreach ($rawForms as $index => $rawForm) {
            if (! is_array($rawForm)) {
                throw new \InvalidArgumentException('each payload.forms entry must be an object.');
            }

            $rawFields = is_array($rawForm['fields'] ?? null) ? $rawForm['fields'] : [['label' => 'Field 1', 'name' => 'field_1']];
            $fields = [];

            foreach ($rawFields as $fieldIndex => $rawField) {
                if (! is_array($rawField)) {
                    throw new \InvalidArgumentException('each field entry must be an object.');
                }

                $fields[] = [
                    'label' => (string) ($rawField['label'] ?? 'Field '.($fieldIndex + 1)),
                    'name' => (string) ($rawField['name'] ?? 'field_'.($fieldIndex + 1)),
                    'input_type' => (string) ($rawField['input_type'] ?? 'input'),
                    'required' => (bool) ($rawField['required'] ?? false),
                ];
            }

            $forms[] = [
                'title' => (string) ($rawForm['title'] ?? 'Form '.($index + 1)),
                'fields' => $fields,
                'submissions' => max(0, (int) ($rawForm['submissions'] ?? 0)),
            ];
        }

        return $forms;
    }

    /**
     * Bersihkan baris yang sudah tercatat di journal secara terbalik (anak dulu).
     */
    private function rollbackJournal(): void
    {
        $entries = array_reverse($this->journal);
        $this->journal = [];

        if ($entries === []) {
            return;
        }

        DB::transaction(function () use ($entries): void {
            foreach ($entries as $entry) {
                DB::table($entry['table'])->where('id', $entry['id'])->delete();
            }
        });
    }
}
