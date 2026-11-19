<?php

namespace App\Enums;

/** Hasil percobaan melepas provider dari akun yang sedang login. */
enum ProviderDisconnectionResult: string
{
    case Disconnected = 'disconnected';
    case NotConnected = 'not_connected';
    case LastCredential = 'last_credential';
}
