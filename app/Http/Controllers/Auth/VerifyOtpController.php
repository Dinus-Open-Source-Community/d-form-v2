<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Auth\RegistrationOtpService;
use App\Support\PendingRegistrationSession;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman verifikasi OTP registrasi: tampilkan status, verifikasi kode, kirim ulang.
 *
 * Email selalu diambil dari sesi (bukan body) agar aksi tidak bisa dipicu pihak ketiga.
 */
class VerifyOtpController extends Controller
{
    /** Tampilkan halaman OTP dengan props status dari server; tanpa sesi pending → register. */
    public function index(Request $request, RegistrationOtpService $service): Response|RedirectResponse
    {
        $pending = $this->pendingFromSession($request);

        if ($pending === null) {
            return $this->redirectToRegister($request);
        }

        return inertia('Auth/VerifyOtp', $service->status($pending));
    }

    /** Verifikasi kode; sukses langsung login dan menghapus penanda sesi pending. */
    public function verify(VerifyOtpRequest $request, RegistrationOtpService $service): RedirectResponse
    {
        $email = PendingRegistrationSession::email($request);

        if ($email === null) {
            return $this->redirectToRegister($request);
        }

        $outcome = $service->verify($email, (string) $request->validated('code'));

        if ($outcome !== OtpOutcome::Verified) {
            return $this->backToOtpWithError('code', $this->verifyErrorMessage($outcome));
        }

        $user = User::query()->where('email', $email)->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();
        PendingRegistrationSession::forget($request);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Email berhasil diverifikasi.',
        ]);

        return redirect()->route('dashboard');
    }

    /** Kirim ulang kode memakai email sesi; outcome selain sukses dipetakan ke pesan. */
    public function resend(Request $request, RegistrationOtpService $service): RedirectResponse
    {
        $pending = $this->pendingFromSession($request);

        if ($pending === null) {
            return $this->redirectToRegister($request);
        }

        $outcome = $service->resend($pending->email);

        if ($outcome === OtpOutcome::Issued) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Kode verifikasi baru sudah dikirim ke email Anda.',
            ]);

            return redirect()->route('auth.otp.show');
        }

        if ($outcome === OtpOutcome::Missing) {
            return $this->redirectToRegister($request);
        }

        return $this->backToOtpWithError('resend', $this->resendErrorMessage($service->status($pending)));
    }

    /** Pending yang ditunjuk sesi; null bila sesi kosong atau barisnya sudah tidak ada. */
    private function pendingFromSession(Request $request): ?PendingRegistration
    {
        $email = PendingRegistrationSession::email($request);

        if ($email === null) {
            return null;
        }

        return PendingRegistration::query()->where('email', $email)->first();
    }

    /** Kembali ke halaman OTP dengan error pada key yang dipakai halaman. */
    private function backToOtpWithError(string $key, string $message): RedirectResponse
    {
        return redirect()->route('auth.otp.show')->withErrors([$key => $message]);
    }

    /** Bersihkan sesi OTP lalu kembali ke form register. */
    private function redirectToRegister(Request $request): RedirectResponse
    {
        PendingRegistrationSession::forget($request);

        return redirect()->route('auth.register');
    }

    /** Pesan error verifikasi sesuai outcome service. */
    private function verifyErrorMessage(OtpOutcome $outcome): string
    {
        return match ($outcome) {
            OtpOutcome::InvalidCode => 'Kode verifikasi salah. Periksa kembali atau kirim ulang kode.',
            OtpOutcome::Expired => 'Kode verifikasi sudah kedaluwarsa. Kirim ulang kode baru.',
            OtpOutcome::Locked => 'Batas pengiriman kode tercapai. Tunggu sampai hitung mundur berakhir.',
            default => 'Sesi verifikasi tidak ditemukan. Silakan daftar ulang.',
        };
    }

    /**
     * Pesan penolakan kirim ulang: lock kuota (15 menit) berbeda dari cooldown antar-kirim.
     *
     * @param  array{lockedUntil: string|null, resendAvailableAt: string|null}  $status
     */
    private function resendErrorMessage(array $status): string
    {
        if ($status['lockedUntil'] !== null) {
            return 'Batas pengiriman kode tercapai. Tunggu sampai hitung mundur berakhir.';
        }

        $availableAt = $status['resendAvailableAt'];

        $seconds = $availableAt === null
            ? 0
            : max(0, (int) now()->diffInSeconds(CarbonImmutable::parse($availableAt)));

        return 'Tunggu '.$seconds.' detik sebelum mengirim ulang kode.';
    }
}
