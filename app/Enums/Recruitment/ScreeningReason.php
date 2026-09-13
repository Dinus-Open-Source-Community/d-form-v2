<?php

namespace App\Enums\Recruitment;

enum ScreeningReason: string
{
    case IncompleteData = 'incomplete_data';
    case InvalidData = 'invalid_data';
    case DocumentMismatch = 'document_mismatch';
    case DocumentUnreadable = 'document_unreadable';
    case InfoMismatch = 'info_mismatch';
    case RequirementsNotMet = 'requirements_not_met';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::IncompleteData => 'Data tidak lengkap',
            self::InvalidData => 'Data tidak valid',
            self::DocumentMismatch => 'Dokumen tidak sesuai',
            self::DocumentUnreadable => 'Dokumen tidak dapat dibaca',
            self::InfoMismatch => 'Informasi tidak sesuai',
            self::RequirementsNotMet => 'Persyaratan tidak terpenuhi',
            self::Other => 'Lainnya',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $reason): array => ['value' => $reason->value, 'label' => $reason->label()],
            self::cases(),
        );
    }
}
