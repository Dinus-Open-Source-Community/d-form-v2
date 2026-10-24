<?php

namespace App\Console\Commands;

use App\Services\Auth\RegistrationOtpService;
use Illuminate\Console\Command;

/**
 * Cetak kode OTP registrasi yang aktif; hanya untuk local/testing (E2E), tidak untuk produksi.
 */
class CurrentOtpCommand extends Command
{
    protected $signature = 'otp:current {email}';

    protected $description = 'Cetak kode OTP registrasi aktif untuk email (hanya local/testing)';

    public function handle(RegistrationOtpService $otpService): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Perintah ini hanya tersedia pada lingkungan local/testing.');

            return self::FAILURE;
        }

        $code = $otpService->currentCode((string) $this->argument('email'));

        if ($code === null) {
            $this->error('Tidak ada OTP aktif untuk email tersebut.');

            return self::FAILURE;
        }

        $this->line($code);

        return self::SUCCESS;
    }
}
