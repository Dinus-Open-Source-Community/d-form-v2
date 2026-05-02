<?php

namespace App\Support;

/**
 * API / kontrak memakai `select`; kolom DB `form_fields.input_type` memakai `selectInput`.
 * `radio` dan `checkbox` tersimpan dengan nama yang sama di API dan DB.
 */
final class FormFieldTypeMapping
{
    public const API_TYPES = ['input', 'select', 'textarea', 'datePicker', 'fileUpload', 'radio', 'checkbox'];

    public static function toInputType(string $apiType): string
    {
        return match ($apiType) {
            'select' => 'selectInput',
            default => $apiType,
        };
    }

    public static function toApiType(string $inputType): string
    {
        return match ($inputType) {
            'selectInput' => 'select',
            default => $inputType,
        };
    }
}
