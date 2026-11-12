<?php

namespace App\Console\Commands;

use App\Models\PendingRegistration;
use Illuminate\Console\Command;

/**
 * Hapus registrasi pending yang ditinggalkan agar tabel OTP tidak menumpuk;
 * dijalankan scheduler tiap jam, menyisakan baris yang masih aktif dalam 24 jam terakhir.
 */
class PrunePendingRegistrationsCommand extends Command
{
    protected $signature = 'otp:prune';

    protected $description = 'Hapus registrasi pending OTP yang tidak diperbarui lebih dari 24 jam';

    public function handle(): int
    {
        $deleted = PendingRegistration::query()
            ->where('updated_at', '<', now()->subDay())
            ->delete();

        $this->info("Menghapus {$deleted} registrasi pending kedaluwarsa.");

        return self::SUCCESS;
    }
}
