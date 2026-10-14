<?php

namespace App\Support;

use App\Models\FormField;

/**
 * API / kontrak memakai `select`; kolom DB `form_fields.input_type` memakai `selectInput`.
 * `radio` dan `checkbox` tersimpan dengan nama yang sama di API dan DB.
 */
final class FormFieldTypeMapping
{
    public const API_TYPES = ['input', 'select', 'textarea', 'datePicker', 'fileUpload', 'radio', 'checkbox', 'banner'];

    /**
     * Konten gambar builder TIDAK menambah API type / enum DB baru:
     * simpan sebagai `input` + `metadata.builderType = image`
     * (kolom `form_fields.input_type` tetap tanpa migrasi).
     */
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

    /**
     * Bentuk field untuk Inertia (sama seperti halaman isi formulir): `type` API + metadata lengkap.
     */
    public static function fieldToInertia(FormField $field): array
    {
        $meta = $field->metadata;
        if ($meta instanceof \Illuminate\Support\Collection) {
            $meta = $meta->all();
        } elseif (is_object($meta) && method_exists($meta, 'toArray')) {
            $meta = $meta->toArray();
        } else {
            $meta = (array) $meta;
        }

        return [
            'id' => $field->id,
            'type' => self::toApiType($field->input_type),
            'label' => $field->label,
            'description' => $field->description,
            'name' => $field->name,
            'order' => $field->order,
            'metadata' => $meta,
            'is_append' => (bool) $field->is_append,
        ];
    }
}
