<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterStoreRequest;
use App\Services\Auth\RegistrationOtpService;
use App\Support\PendingRegistrationSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    /** Pesan netral agar status pending tidak bisa ditebak dari percobaan kata sandi. */
    private const PENDING_EMAIL_MESSAGE = 'Pendaftaran dengan email ini sedang menunggu verifikasi. Selesaikan verifikasi atau gunakan email lain.';

    /** Tampilkan form pendaftaran. */
    public function index(): Response
    {
        return inertia('Auth/Register');
    }

    /**
     * Mulai pendaftaran tertunda lalu arahkan ke verifikasi OTP; user belum dibuat dan belum login.
     */
    public function store(RegisterStoreRequest $request, RegistrationOtpService $service): RedirectResponse
    {
        $data = $request->validated();
        $outcome = $this->startPending($service, $data);

        if ($outcome === OtpOutcome::PasswordMismatch) {
            return Inertia::flash('toast', [
                'message' => self::PENDING_EMAIL_MESSAGE,
                'type' => 'error',
            ])->back();
        }

        $request->session()->regenerate();
        PendingRegistrationSession::remember($request, $data['email']);

        $locked = $outcome === OtpOutcome::Locked;

        Inertia::flash('toast', [
            'message' => $locked
                ? 'Batas pengiriman kode tercapai. Tunggu sampai hitung mundur berakhir.'
                : 'Kode verifikasi sudah dikirim ke email Anda.',
            'type' => $locked ? 'error' : 'success',
        ]);

        return redirect()->route('auth.otp.show');
    }

    /**
     * Panggil start() dan ulangi sekali bila request paralel lebih dulu membuat baris pending.
     *
     * @param  array<string, mixed>  $data
     */
    private function startPending(RegistrationOtpService $service, array $data): OtpOutcome
    {
        try {
            return $service->start($data);
        } catch (UniqueConstraintViolationException) {
            return $service->start($data);
        }
    }
}
