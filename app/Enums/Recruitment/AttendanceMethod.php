<?php

namespace App\Enums\Recruitment;

enum AttendanceMethod: string
{
    case Qr = 'qr';
    case RegistrationNumber = 'registration_number';

    public function label(): string
    {
        return match ($this) {
            self::Qr => 'QR Code',
            self::RegistrationNumber => 'Nomor pendaftaran',
        };
    }
}
