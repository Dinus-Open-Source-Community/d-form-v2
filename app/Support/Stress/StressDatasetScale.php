<?php

namespace App\Support\Stress;

/**
 * Nilai default dataset stress yang dipakai command `stress:seed`.
 *
 * Class ini di-commit agar `php artisan package:discover` tidak bergantung pada
 * seeder stress yang di-gitignore (`database/seeders/Stress/*`). Signature command
 * dievaluasi saat discovery, sedangkan isi seeder hanya saat command dijalankan.
 */
final class StressDatasetScale
{
    /** Skala default: 1 juta baris per tabel domain. */
    public const DEFAULT_SCALE = 1_000_000;
}
