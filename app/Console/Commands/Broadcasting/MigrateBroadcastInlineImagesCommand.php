<?php

namespace App\Console\Commands\Broadcasting;

use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastInlineImageService;
use Illuminate\Console\Command;

/**
 * Migrasi sekali jalan: pindahkan inline image base64 lama
 * (src="data:image/...") di EmailBroadcast.content ke storage Laravel,
 * tulis ulang src ke URL storage. Aman diulang (idempoten):
 * konten tanpa data: URL dilewati.
 */
class MigrateBroadcastInlineImagesCommand extends Command
{
    protected $signature = 'broadcasts:migrate-inline-images
        {--dry-run : Tampilkan rencana migrasi tanpa menyimpan perubahan.}
        {--chunk=200 : Jumlah broadcast per chunk.}';

    protected $description = 'Migrasi inline image base64 di konten broadcast ke storage (DB hanya simpan URL).';

    public function handle(BroadcastInlineImageService $images): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $totalBroadcasts = 0;
        $totalMigrated = 0;
        $totalSkipped = 0;
        $touchedBroadcasts = 0;

        EmailBroadcast::query()
            ->where('content', 'like', '%data:image/%')
            ->orderBy('created_at')
            ->chunk($chunk, function ($broadcasts) use ($images, $dryRun, &$totalBroadcasts, &$totalMigrated, &$totalSkipped, &$touchedBroadcasts): void {
                foreach ($broadcasts as $broadcast) {
                    $totalBroadcasts++;

                    $result = $images->migrateHtml($broadcast, (string) $broadcast->content);

                    if ($result['migrated'] === 0 && $result['skipped'] === 0) {
                        continue;
                    }

                    $touchedBroadcasts++;
                    $totalMigrated += $result['migrated'];
                    $totalSkipped += $result['skipped'];

                    $this->line(sprintf(
                        '[%s] %s: %d dipindahkan%s%s',
                        $broadcast->id,
                        $broadcast->name,
                        $result['migrated'],
                        $result['skipped'] > 0 ? sprintf(', %d dilewati (tidak valid/melebihi 2 MB)', $result['skipped']) : '',
                        $dryRun ? ' (dry-run)' : ''
                    ));

                    if (! $dryRun && $result['migrated'] > 0) {
                        $broadcast->forceFill(['content' => $result['html']])->save();
                    }
                }
            });

        $this->info(sprintf(
            '%s: %d broadcast diperiksa, %d disentuh, %d gambar dipindahkan, %d dilewati.',
            $dryRun ? 'Dry-run selesai' : 'Migrasi selesai',
            $totalBroadcasts,
            $touchedBroadcasts,
            $totalMigrated,
            $totalSkipped
        ));

        return self::SUCCESS;
    }
}
