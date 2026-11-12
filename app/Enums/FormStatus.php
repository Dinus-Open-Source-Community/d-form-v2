<?php

namespace App\Enums;

/**
 * Status form mengikuti pola {@see EventStatus}: draft adalah baris yang masih
 * disusun penyelenggara dan tidak pernah terlihat oleh peserta/publik.
 */
enum FormStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): string
    {
        return ucwords(__('enum.form.status.' . $this->value));
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }
}
