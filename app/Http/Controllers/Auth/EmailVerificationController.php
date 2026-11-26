<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpOutcome;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Support\PostAuthDestination;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Halaman verifikasi email akun: tampilkan status, kirim kode, dan konfirmasi kode. */
class EmailVerificationController extends Controller
{
    /** Tampilkan halaman verifikasi; email yang sudah terverifikasi tidak perlu halaman ini. */
    public function notice(Request $request, EmailVerificationService $service): Response|RedirectResponse
    {
        $user = $this->sessionUser($request);

        if ($user->email_verified_at !== null) {
            return redirect()->to(PostAuthDestination::urlFor($user));
        }

        return inertia('Auth/VerifyEmail', $this->pageProps($user, $service));
    }

    /** Terbitkan kode baru (kirim pertama atau kirim ulang) memakai aturan service. */
    public function send(Request $request, EmailVerificationService $service): JsonResponse|RedirectResponse
    {
        $user = $this->sessionUser($request);
        $outcome = $service->issue($user);

        if ($outcome === OtpOutcome::Verified) {
            return $this->respondVerified($request, $user);
        }

        if ($outcome === OtpOutcome::Issued) {
            if ($request->expectsJson()) {
                return response()->json($this->pageProps($user, $service));
            }

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Kode verifikasi sudah dikirim ke email Anda.',
            ]);

            return redirect()->route('verification.notice');
        }

        return redirect()
            ->route('verification.notice')
            ->withErrors(['resend' => $this->sendErrorMessage($service->status($user))]);
    }

    /** Cocokkan kode milik user yang sedang login; sukses menandai email terverifikasi. */
    public function confirm(Request $request, EmailVerificationService $service): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:'.max(1, (int) config('otp.length'))],
        ], [
            'code.required' => 'Masukkan kode verifikasi.',
            'code.digits' => 'Kode verifikasi harus berupa angka sesuai panjang kode.',
        ]);

        $user = $this->sessionUser($request);
        $outcome = $service->verify($user, (string) $validated['code']);

        if ($outcome !== OtpOutcome::Verified) {
            $message = $this->confirmErrorMessage($outcome);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('verification.notice')->withErrors(['code' => $message]);
        }

        return $this->respondVerified($request, $user);
    }

    /**
     *
     * Props halaman verifikasi: identitas singkat + objek status dari service.
     *
     * @return array{email: string, verified: bool, expiresAt: string|null, serverNow: string, attemptsRemaining: int, sendsRemaining: int, lockedUntil: string|null, resendAvailableAt: string|null}
     *
     */
    private function pageProps(User $user, EmailVerificationService $service): array
    {
        return [
            'email' => (string) $user->email,
            'verified' => $user->email_verified_at !== null,
            ...$service->status($user),
        ];
    }

    /** Redirect (atau JSON) menuju tujuan pasca-verifikasi sesuai peran user. */
    private function respondVerified(Request $request, User $user): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'verified' => true,
                'redirect' => PostAuthDestination::urlFor($user),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Email berhasil diverifikasi.',
        ]);

        return redirect()->to(PostAuthDestination::urlFor($user));
    }

    /** User sesi; route terpasang middleware `auth` sehingga user selalu ada. */
    private function sessionUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    /** Pesan kegagalan konfirmasi sesuai outcome service. */
    private function confirmErrorMessage(OtpOutcome $outcome): string
    {
        return match ($outcome) {
            OtpOutcome::InvalidCode => 'Kode verifikasi salah. Periksa kembali atau kirim ulang kode.',
            OtpOutcome::Expired => 'Kode verifikasi sudah kedaluwarsa. Kirim ulang kode baru.',
            OtpOutcome::Missing => 'Kode verifikasi sudah tidak berlaku. Kirim ulang kode baru.',
            default => 'Kode verifikasi tidak dapat diproses. Kirim ulang kode baru.',
        };
    }

    /**
     *
     * Pesan penolakan kirim: lock kuota berbeda dari cooldown antar-kirim.
     * Salinan sengaja identik dengan halaman OTP registrasi agar satu situasi berbunyi sama.
     *
     * @param  array{lockedUntil: string|null, resendAvailableAt: string|null}  $status
     *
     */
    private function sendErrorMessage(array $status): string
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
