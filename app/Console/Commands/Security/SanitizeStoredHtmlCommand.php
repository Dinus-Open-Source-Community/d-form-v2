<?php

namespace App\Console\Commands\Security;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Broadcasting\BroadcastHtmlSanitizer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Backfill sekali jalan: sanitasi HTML lama yang tersimpan sebelum
 * sanitasi di FormRequest ada. Aman dijalankan berulang (idempoten).
 *
 *   php artisan security:sanitize-stored-html --dry-run
 *   php artisan security:sanitize-stored-html
 */
class SanitizeStoredHtmlCommand extends Command
{
    protected $signature = 'security:sanitize-stored-html
        {--dry-run : Hitung baris yang akan berubah tanpa menulis}';

    protected $description = 'Sanitasi HTML tersimpan lama (event, success content, field builder) dengan allowlist rich text';

    private const CHUNK_SIZE = 500;

    public function handle(BroadcastHtmlSanitizer $sanitizer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $changed = [
            'events.description' => $this->sanitizeColumn(Event::withTrashed(), 'description', $sanitizer, $dryRun),
            'forms.success_content' => $this->sanitizeColumn(Form::withTrashed(), 'success_content', $sanitizer, $dryRun),
            'form_fields.description' => $this->sanitizeColumn(FormField::withTrashed(), 'description', $sanitizer, $dryRun),
            'form_fields.metadata.content' => $this->sanitizeFieldContent($sanitizer, $dryRun),
        ];

        $rows = [];
        foreach ($changed as $column => $count) {
            $rows[] = [$column, $count];
        }

        $this->table([$dryRun ? 'Kolom (dry-run)' : 'Kolom', 'Baris diubah'], $rows);

        if ($dryRun) {
            $this->info('Dry-run: tidak ada baris yang ditulis. Jalankan tanpa --dry-run untuk menyimpan.');
        }

        return self::SUCCESS;
    }

    /**
     * Sanitasi satu kolom teks per batch; baris yang nilainya tidak berubah tidak ditulis.
     *
     * @param Builder<Model> $query
     */
    private function sanitizeColumn(
        Builder $query,
        string $column,
        BroadcastHtmlSanitizer $sanitizer,
        bool $dryRun,
    ): int {
        $changed = 0;

        $query->select(['id', $column])->chunkById(
            self::CHUNK_SIZE,
            function (Collection $rows) use ($column, $sanitizer, $dryRun, &$changed): void {
                foreach ($rows as $model) {
                    $current = $model->getAttribute($column);

                    if (! is_string($current)) {
                        continue;
                    }

                    $clean = $sanitizer->sanitizeIfMarkup($current);

                    if ($clean === $current) {
                        continue;
                    }

                    $changed++;

                    if ($dryRun) {
                        continue;
                    }

                    $model->setAttribute($column, $clean);
                    $model->saveQuietly();
                }
            },
        );

        return $changed;
    }

    /**
     * Sanitasi `metadata.content` field builder (metadata disimpan sebagai collection/JSON).
     */
    private function sanitizeFieldContent(BroadcastHtmlSanitizer $sanitizer, bool $dryRun): int
    {
        $changed = 0;

        FormField::withTrashed()
            ->select(['id', 'metadata'])
            ->chunkById(
                self::CHUNK_SIZE,
                function (Collection $rows) use ($sanitizer, $dryRun, &$changed): void {
                    foreach ($rows as $field) {
                        $raw = $field->metadata;
                        $meta = $raw instanceof Collection ? $raw->all() : (array) $raw;

                        $content = $meta['content'] ?? null;

                        if (! is_string($content)) {
                            continue;
                        }

                        $clean = $sanitizer->sanitizeIfMarkup($content);

                        if ($clean === $content) {
                            continue;
                        }

                        $changed++;

                        if ($dryRun) {
                            continue;
                        }

                        $meta['content'] = $clean;
                        $field->metadata = $meta;
                        $field->saveQuietly();
                    }
                },
            );

        return $changed;
    }
}
