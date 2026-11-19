<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpOutcome;
use App\Enums\TwoFactorVerifyResult;
use App\Http\Controllers\Concerns\ResolvesSessionUser;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Http\Requests\RecoveryTwoFactorRequest;
use App\Http\Requests\VerifyTwoFactorRequest;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\TwoFactorPinService;
use App\Support\PostAuthDestination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman tantangan faktor kedua: verifikasi PIN dan pemulihan lewat OTP email.
 * Pemulihan tidak melewati faktor kedua: OTP hanya membuka langkah set PIN baru (spec §6.5).
 */
class TwoFactorChallengeController extends Controller
{
    use ResolvesSessionUser;

    /** Tampilkan halaman tantangan; akun tanpa PIN atau yang sudah lolos tidak perlu halaman ini. */
    public function show(Request $request, TwoFactorPinService $pins): Response|RedirectResponse
    {
        $user = $this->sessionUser($request);

        if (! (bool) $user->has_pin_2fa) {
            return redirect()->to(PostAuthDestination::urlFor($user));
        }

        if (EnsureTwoFactorVerified::isSatisfied($request, $user)) {
            return redirect()->to(PostAuthDestination::urlFor($user));
        }

        return inertia('Auth/TwoFactorChallenge', [
            'status' => $pins->status($user),
        ]);
    }

    /** Cocokkan PIN; sukses merotasi sesi, menandai faktor kedua lolos, lalu ke `intended`. */
    public function verify(VerifyTwoFactorRequest $request, TwoFactorPinService $pins): JsonResponse|RedirectResponse
    {
        $user = $this->sessionUser($request);
        $result = $pins->verify($user, (string) $request->validated('pin'));

        if ($result !== TwoFactorVerifyResult::Verified) {
            return $this->failure($request, 'pin', $result, $pins->status($user));
        }

        $request->session()->regenerate();
        EnsureTwoFactorVerified::markVerified($request, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'PIN benar. Selamat datang kembali.',
        ]);

        return redirect()->intended(PostAuthDestination::urlFor($user));
    }

    /** Kirim OTP pemulihan ke email terdaftar memakai mesin OTP verifikasi email yang sama. */
    public function sendRecovery(Request $request, EmailVerificationService $codes): JsonResponse|RedirectResponse
    {
        $user = $this->sessionUser($request);
        $outcome = $codes->issue($user, EmailVerificationService::PURPOSE_TWO_FACTOR_RECOVERY);

        if ($outcome !== OtpOutcome::Issued) {
            $message = 'Kode pemulihan tidak dapat dikirim sekarang. Tunggu sebentar lalu coba lagi.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'status' => $codes->status($user, EmailVerificationService::PURPOSE_TWO_FACTOR_RECOVERY),
                ], 429);
            }

            return redirect()->route('two-factor.challenge')->withErrors(['recovery' => $message]);
        }

        if ($request->expectsJson()) {
            return response()->json($codes->status($user, EmailVerificationService::PURPOSE_TWO_FACTOR_RECOVERY));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Kode pemulihan sudah dikirim ke email Anda.',
        ]);

        return redirect()->route('two-factor.challenge');
    }

    /**
     *
     * Konfirmasi OTP pemulihan. Sukses hanya membuka langkah set PIN baru; faktor kedua
     * belum terpenuhi sampai PIN baru tersimpan.
     *
     */
    public function confirmRecovery(RecoveryTwoFactorRequest $request, EmailVerificationService $codes): JsonResponse|RedirectResponse
    {
        $user = $this->sessionUser($request);
        $outcome = $codes->verify(
            $user,
            (string) $request->validated('code'),
            EmailVerificationService::PURPOSE_TWO_FACTOR_RECOVERY,
        );

        if ($outcome !== OtpOutcome::Verified) {
            $message = $this->recoveryErrorMessage($outcome);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('two-factor.challenge')->withErrors(['code' => $message]);
        }

        $request->session()->put(EnsureTwoFactorVerified::SESSION_RECOVERY_USER_ID, (string) $user->getKey());

        if ($request->expectsJson()) {
            return response()->json(['confirmed' => true]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Kode benar. Buat PIN baru untuk melanjutkan.',
        ]);

        return redirect()->route('two-factor.challenge');
    }

    /** Set PIN baru setelah OTP terbukti; baru pada titik ini sesi ditandai lolos faktor kedua. */
    public function setRecoveryPin(RecoveryTwoFactorRequest $request, TwoFactorPinService $pins): JsonResponse|RedirectResponse
    {
        $user = $this->sessionUser($request);
        $request->session()->forget(EnsureTwoFactorVerified::SESSION_RECOVERY_USER_ID);

        $pins->change($user, (string) $request->validated('pin'));

        $request->session()->regenerate();
        EnsureTwoFactorVerified::markVerified($request, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'PIN baru tersimpan. Anda sudah masuk.',
        ]);

        return redirect()->intended(PostAuthDestination::urlFor($user));
    }

    /**
     *
     * Respons gagal verifikasi PIN: JSON untuk klien Inertia hook, redirect ber-error untuk form.
     *
     * @param  array{enabled: bool, attemptsRemaining: int, lockedUntil: string|null}  $status
     *
     */
    private function failure(
        Request $request,
        string $field,
        TwoFactorVerifyResult $result,
        array $status,
    ): JsonResponse|RedirectResponse {
        $message = $result->message();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'status' => $status], 422);
        }

        return redirect()->route('two-factor.challenge')->withErrors([$field => $message]);
    }

    /** Pesan kegagalan konfirmasi OTP pemulihan sesuai outcome service. */
    private function recoveryErrorMessage(OtpOutcome $outcome): string
    {
        return match ($outcome) {
            OtpOutcome::InvalidCode => 'Kode pemulihan salah. Periksa kembali atau kirim ulang kode.',
            OtpOutcome::Expired => 'Kode pemulihan sudah kedaluwarsa. Kirim ulang kode baru.',
            OtpOutcome::Missing => 'Kode pemulihan sudah tidak berlaku. Kirim ulang kode baru.',
            default => 'Kode pemulihan tidak dapat diproses. Kirim ulang kode baru.',
        };
    }
}
