<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Console\Command;

/** Cetak kode verifikasi email yang aktif; hanya untuk local/testing (E2E), tidak untuk produksi. */
class EmailVerificationCodeCommand extends Command
{
    protected $signature = 'email:verification-code {email}';

    protected $description = 'Cetak kode verifikasi email aktif untuk email (hanya local/testing)';

    public function handle(EmailVerificationService $service): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Perintah ini hanya tersedia pada lingkungan local/testing.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', (string) $this->argument('email'))->first();
        $code = $user === null ? null : $service->currentCode($user);

        if ($code === null) {
            $this->error('Tidak ada kode verifikasi aktif untuk email tersebut.');

            return self::FAILURE;
        }

        $this->line($code);

        return self::SUCCESS;
    }
}
