<?php

namespace App\Enums;

/**
 * Hasil aturan OTP registrasi; dipetakan controller ke respons halaman.
 */
enum OtpOutcome
{
    case Issued;
    case Verified;
    case InvalidCode;
    case Expired;
    case Locked;
    case Missing;
    case PasswordMismatch;
}
