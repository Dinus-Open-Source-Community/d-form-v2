<?php

namespace App\Services\Form;

use App\Models\FormField;
use App\Support\FormFieldTypeMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menyimpan snapshot lengkap field satu form sekaligus.
 *
 * Jumlah query tidak tumbuh sebanding jumlah field: satu query baca id, satu hapus per chunk,
 * satu upsert per chunk. Id milik form lain tidak pernah dipakai ulang agar snapshot tidak bisa
 * memindahkan field antar form.
 */
final class FormFieldSnapshotSync
{
    private const INSERT_CHUNK_SIZE = 200;

    private const UPSERT_CHUNK_SIZE = 200;

    private const DELETE_CHUNK_SIZE = 500;

    /** Kolom yang ditimpa saat id sudah ada; `deleted_at` null memulihkan field yang terhapus. */
    private const UPDATE_COLUMNS = [
        'input_type',
        'label',
        'description',
        'name',
        'order',
        'metadata',
        'is_append',
        'deleted_at',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function sync(string $formId, array $rows): void
    {
        $existing = DB::table('form_fields')
            ->where('form_id', $formId)
            ->get(['id', 'deleted_at']);

        $ownedIds = [];
        $activeIds = [];

        foreach ($existing as $row) {
            $ownedIds[$row->id] = true;

            if ($row->deleted_at === null) {
                $activeIds[] = $row->id;
            }
        }

        $now = now();
        $toInsert = [];
        $toUpsert = [];

        foreach ($rows as $row) {
            $attributes = $this->rowToAttributes($row, $formId, $now);

            if (isset($ownedIds[$attributes['id']])) {
                $toUpsert[] = $attributes;

                continue;
            }

            $toInsert[] = $attributes;
        }

        $incomingIds = array_column([...$toInsert, ...$toUpsert], 'id');

        DB::transaction(function () use ($formId, $toInsert, $toUpsert, $activeIds, $incomingIds): void {
            $this->deleteMissing($formId, $activeIds, $incomingIds);

            foreach (array_chunk($toInsert, self::INSERT_CHUNK_SIZE) as $chunk) {
                // Insert tidak pernah menyentuh baris form lain: id asing berakhir sebagai
                // pelanggaran unique key, sama seperti perilaku create() sebelumnya.
                FormField::query()->insert($chunk);
            }

            foreach (array_chunk($toUpsert, self::UPSERT_CHUNK_SIZE) as $chunk) {
                FormField::query()->upsert($chunk, ['id'], self::UPDATE_COLUMNS);
            }
        });
    }

    /**
     * @param  array<int, string>  $activeIds
     * @param  array<int, string>  $incomingIds
     */
    private function deleteMissing(string $formId, array $activeIds, array $incomingIds): void
    {
        $toDelete = array_values(array_diff($activeIds, $incomingIds));

        foreach (array_chunk($toDelete, self::DELETE_CHUNK_SIZE) as $chunk) {
            FormField::query()
                ->where('form_id', $formId)
                ->whereIn('id', $chunk)
                ->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function rowToAttributes(array $row, string $formId, \DateTimeInterface $now): array
    {
        $metadata = $row['metadata'];
        $id = $row['id'] ?? null;

        return [
            'id' => is_string($id) && $id !== '' ? $id : (string) Str::uuid(),
            'form_id' => $formId,
            'input_type' => FormFieldTypeMapping::toInputType($row['type']),
            'label' => $row['label'],
            'description' => $row['description'] ?? null,
            'name' => $row['name'],
            'order' => (int) $row['order'],
            // Insert dan upsert melewati cast model, jadi metadata di-encode di sini.
            'metadata' => is_string($metadata) ? $metadata : json_encode($metadata),
            'is_append' => (bool) ($row['is_append'] ?? false),
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
