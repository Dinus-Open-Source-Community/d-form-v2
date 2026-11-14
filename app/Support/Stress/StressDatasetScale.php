<?php

namespace App\Support\Stress;

/** Nilai default dataset stress yang dipakai command `stress:seed`. */
final class StressDatasetScale
{
    /** Skala default: 1 juta baris per tabel domain. */
    public const DEFAULT_SCALE = 1_000_000;
}
