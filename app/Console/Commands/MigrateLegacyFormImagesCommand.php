<?php

namespace App\Console\Commands;

use App\Models\Form;
use App\Models\FormField;
use App\Support\LegacyFormImage;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Migrasi sekali jalan: pindahkan gambar legacy form yang masih berupa
 * data: URL (base64 inline) ke disk "public", DB hanya simpan path.
 *
 *   php artisan forms:migrate-legacy-images --dry-run --limit=100
 *   php artisan forms:migrate-legacy-images --write
 *   php artisan forms:migrate-legacy-images --write --form=<form-id>
 *
 * Default = dry-run (hanya pratinjau). Penulisan terjadi HANYA dengan --write
 * (dan tanpa --dry-run). Tidak ada perubahan skema DB.
 */
class MigrateLegacyFormImagesCommand extends Command
{
    protected $signature = 'forms:migrate-legacy-images
        {--write : Simpan hasil ke disk public + DB (default: dry-run, hanya pratinjau)}
        {--dry-run : Pratinjau tanpa menulis apa pun (default)}
        {--limit= : Batasi jumlah record (forms + fields) yang diproses}
        {--form= : Hanya proses form dengan ID ini}';

    protected $description = 'Pindahkan gambar legacy form (data: URL base64) ke storage public, DB hanya simpan path';

    public function handle(): int
    {
        $write = (bool) $this->option('write') && ! (bool) $this->option('dry-run');
        $limitOption = $this->option('limit');
        $limit = $limitOption !== null && $limitOption !== '' ? max(1, (int) $limitOption) : null;
        $onlyFormId = is_string($this->option('form')) && $this->option('form') !== ''
            ? $this->option('form')
            : null;

        $this->info($write ? 'Mode: WRITE (menyimpan ke disk + DB)' : 'Mode: DRY-RUN (tidak menulis apa pun)');

        $formsQuery = Form::query()
            ->when($onlyFormId !== null, fn ($q) => $q->where('id', $onlyFormId))
            ->where('banner_url', 'like', '%data:%')
            ->orderBy('id');

        $fieldsQuery = FormField::query()
            ->when($onlyFormId !== null, fn ($q) => $q->where('form_id', $onlyFormId))
            ->where('metadata', 'like', '%data:%')
            ->orderBy('id');

        if ($limit !== null) {
            $forms = $formsQuery->limit($limit)->get();
            $remaining = max(0, $limit - $forms->count());
            $fields = $fieldsQuery->limit($remaining)->get();
        } else {
            $forms = $formsQuery->get();
            $fields = $fieldsQuery->get();
        }

        $stats = [
            'forms_scanned' => 0,
            'forms_converted' => 0,
            'forms_failed' => 0,
            'fields_scanned' => 0,
            'fields_converted' => 0,
            'fields_failed' => 0,
            'data_urls_found' => 0,
            'urls_converted' => 0,
            'urls_failed' => 0,
        ];

        foreach ($forms as $form) {
            $stats['forms_scanned']++;

            if (! LegacyFormImage::isDataUrl($form->banner_url)) {
                continue;
            }
            $stats['data_urls_found']++;

            if (! $write) {
                $preview = LegacyFormImage::decode((string) $form->banner_url);
                if ($preview !== null) {
                    $stats['urls_converted']++;
                    $stats['forms_converted']++;
                    $this->line("[forms:{$form->id}] banner_url WOULD convert -> "
                        .LegacyFormImage::BANNER_DIRECTORY_PREFIX."/{$form->id}/legacy-*.{$preview['extension']}");
                } else {
                    $stats['urls_failed']++;
                    $stats['forms_failed']++;
                    $this->warn("[forms:{$form->id}] banner_url CANNOT decode (skip; mime tak diizinkan/oversize/rusak)");
                }

                continue;
            }

            $result = LegacyFormImage::convertFormBanner($form->banner_url, $form->id);
            if ($result['converted']) {
                $form->banner_url = $result['value'];
                $form->save();
                $stats['urls_converted']++;
                $stats['forms_converted']++;
                $this->line("[forms:{$form->id}] banner_url converted -> {$result['value']}");
            } else {
                $stats['urls_failed']++;
                $stats['forms_failed']++;
                $this->warn("[forms:{$form->id}] banner_url FAILED to convert (dipertahankan apa adanya)");
            }
        }

        foreach ($fields as $field) {
            $stats['fields_scanned']++;

            $rawMeta = $field->metadata;
            $meta = $rawMeta instanceof Collection ? $rawMeta->all() : (array) $rawMeta;

            $result = LegacyFormImage::convertFieldMetadata($meta, $field->form_id);
            $touched = $result['converted'] + $result['failed'];

            if ($touched === 0) {
                continue;
            }
            $stats['data_urls_found'] += $touched;

            if (! $write) {
                $stats['urls_converted'] += $result['converted'];
                $stats['urls_failed'] += $result['failed'];
                if ($result['converted'] > 0) {
                    $stats['fields_converted']++;
                }
                if ($result['failed'] > 0) {
                    $stats['fields_failed']++;
                }
                $this->line("[fields:{$field->id} form:{$field->form_id}] WOULD convert {$result['converted']}, fail {$result['failed']}");

                continue;
            }

            if ($result['converted'] > 0) {
                $field->metadata = $result['metadata'];
                $field->save();
                $stats['urls_converted'] += $result['converted'];
                $stats['fields_converted']++;
                $this->line("[fields:{$field->id} form:{$field->form_id}] converted {$result['converted']}"
                    .($result['failed'] > 0 ? ", failed {$result['failed']} (dipertahankan)" : ''));
            } else {
                $stats['urls_failed'] += $result['failed'];
                $stats['fields_failed']++;
                $this->warn("[fields:{$field->id} form:{$field->form_id}] FAILED ({$result['failed']} URL tak bisa di-decode)");
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Forms scanned', $stats['forms_scanned']],
                ['Forms converted', $stats['forms_converted']],
                ['Forms failed', $stats['forms_failed']],
                ['Fields scanned', $stats['fields_scanned']],
                ['Fields converted', $stats['fields_converted']],
                ['Fields failed', $stats['fields_failed']],
                ['Data URLs found', $stats['data_urls_found']],
                ['URLs converted', $stats['urls_converted']],
                ['URLs failed/skipped', $stats['urls_failed']],
            ]
        );

        if (! $write) {
            $this->info('Dry-run selesai. Jalankan dengan --write untuk menyimpan: php artisan forms:migrate-legacy-images --write');
        } elseif ($stats['urls_failed'] > 0) {
            $this->warn('Sebagian URL gagal dikonversi dan dipertahankan apa adanya (mime di luar png/jpg/gif/webp, oversize >5MB, atau base64 rusak).');
        } else {
            $this->info('Migrasi selesai tanpa kegagalan.');
        }

        return self::SUCCESS;
    }
}
