<?php

namespace App\Support\E2e;

use Illuminate\Support\Facades\DB;

/**
 * Journal baris fixture satu run: mencatat urutan insert agar kegagalan parsial
 * bisa dibersihkan terbalik (anak dulu) dalam satu transaksi.
 *
 * Entri hanya dibuang setelah transaksi delete sukses, sehingga kegagalan rollback
 * tidak menghilangkan jejak baris yang masih harus dibersihkan.
 */
final class FixtureJournal
{
    /** @var list<array{table: string, id: string}> */
    private array $entries = [];

    /**
     * Catat satu baris yang baru dibuat; urutan panggilan menentukan urutan rollback.
     */
    public function record(string $table, string $id): void
    {
        $this->entries[] = ['table' => $table, 'id' => $id];
    }

    /**
     * Hapus seluruh baris tercatat secara terbalik, lalu kosongkan journal.
     *
     * `$cause` adalah error payload asli; bila rollback sendiri gagal, error itu
     * dirantai sebagai `previous` agar penyebab awal tidak tertelan.
     */
    public function rollback(\Throwable $cause): void
    {
        if ($this->entries === []) {
            return;
        }

        $entries = array_reverse($this->entries);

        try {
            DB::transaction(function () use ($entries): void {
                foreach ($entries as $entry) {
                    DB::table($entry['table'])->where('id', $entry['id'])->delete();
                }
            });
        } catch (\Throwable $failure) {
            throw new \RuntimeException(
                'e2e fixture rollback failed after: '.$cause->getMessage()
                .'; rollback error: '.$failure->getMessage(),
                0,
                $cause,
            );
        }

        $this->entries = [];
    }
}
