<?php

namespace App\Console\Commands;

use App\Models\FormAnswer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off migration for uploads written before the private-disk change.
 *
 *   php artisan forms:move-uploads-to-private --dry-run
 *   php artisan forms:move-uploads-to-private --write
 *   php artisan forms:move-uploads-to-private --write --form=<form-id>
 *
 * Dry-run is the default. Files are copied to the private disk first and the
 * public copy is deleted only after the private copy exists.
 */
class MoveFormUploadsToPrivateCommand extends Command
{
    protected $signature = 'forms:move-uploads-to-private
        {--write : Copy files to the private disk and delete the public copy (default: dry-run)}
        {--dry-run : Preview only (default)}
        {--form= : Only process answers for this form ID}';

    protected $description = 'Move legacy form uploads from the public disk to the private disk';

    public function handle(): int
    {
        $write = (bool) $this->option('write') && ! (bool) $this->option('dry-run');
        $formId = is_string($this->option('form')) && $this->option('form') !== ''
            ? $this->option('form')
            : null;

        $this->info($write ? 'Mode: WRITE (copy lalu hapus salinan public)' : 'Mode: DRY-RUN (tidak menulis apa pun)');

        $stats = [
            'answers' => 0,
            'files' => 0,
            'moved' => 0,
            'already_private' => 0,
            'missing' => 0,
            'failed' => 0,
        ];

        FormAnswer::query()
            ->when($formId !== null, fn ($query) => $query->where('form_id', $formId))
            ->where('answers', 'like', '%form-uploads%')
            ->lazyById(500)
            ->each(function (FormAnswer $answer) use (&$stats, $write): void {
                $stats['answers']++;
                $answers = is_array($answer->answers) ? $answer->answers : [];
                $prefix = 'form-uploads/'.$answer->form_id.'/';

                foreach ($answers as $value) {
                    if (! is_string($value) || ! str_starts_with($value, $prefix)) {
                        continue;
                    }

                    $stats['files']++;

                    if (Storage::disk('local')->exists($value)) {
                        $stats['already_private']++;

                        continue;
                    }

                    if (! Storage::disk('public')->exists($value)) {
                        $stats['missing']++;

                        continue;
                    }

                    if (! $write) {
                        $stats['moved']++;
                        $this->line("[form-answer:{$answer->id}] WOULD move {$value}");

                        continue;
                    }

                    $stream = Storage::disk('public')->readStream($value);
                    if (! is_resource($stream)) {
                        $stats['failed']++;
                        $this->warn("[form-answer:{$answer->id}] could not read {$value}");

                        continue;
                    }

                    Storage::disk('local')->writeStream($value, $stream);
                    fclose($stream);

                    if (! Storage::disk('local')->exists($value)) {
                        $stats['failed']++;
                        $this->warn("[form-answer:{$answer->id}] could not write {$value}");

                        continue;
                    }

                    Storage::disk('public')->delete($value);
                    $stats['moved']++;
                    $this->line("[form-answer:{$answer->id}] moved {$value}");
                }
            });

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Answers scanned', $stats['answers']],
                ['Files found', $stats['files']],
                [$write ? 'Files moved' : 'Files would move', $stats['moved']],
                ['Already private', $stats['already_private']],
                ['Missing on public disk', $stats['missing']],
                ['Failed', $stats['failed']],
            ]
        );

        if (! $write) {
            $this->info('Dry-run selesai. Jalankan dengan --write untuk memindahkan berkas.');
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
