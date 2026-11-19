<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\TwoFactorVerifyResult;
use App\Http\Controllers\Concerns\ResolvesSessionUser;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Http\Requests\ChangeTwoFactorPinRequest;
use App\Http\Requests\DisableTwoFactorRequest;
use App\Http\Requests\EnableTwoFactorRequest;
use App\Services\Auth\TwoFactorPinService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Pengaturan PIN faktor kedua dari halaman profil (spec §6.3, §6.5). Setiap aksi membuktikan
 * pemilik akun: kata sandi saat ini bila ada, dan PIN saat ini untuk ubah/nonaktifkan.
 */
class TwoFactorSettingsController extends Controller
{
    use ResolvesSessionUser;

    /** Aktifkan PIN; sesi langsung ditandai lolos agar tidak diminta PIN yang baru dibuat. */
    public function enable(EnableTwoFactorRequest $request, TwoFactorPinService $pins): RedirectResponse
    {
        $user = $this->sessionUser($request);

        if ($pins->status($user)['enabled']) {
            return redirect()->back()->withErrors([
                'pin' => 'PIN sudah aktif. Gunakan Ubah PIN untuk menggantinya.',
            ]);
        }

        $pins->enable($user, (string) $request->validated('pin'));

        $request->session()->regenerate();
        EnsureTwoFactorVerified::markVerified($request, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'PIN berhasil diaktifkan.',
        ]);

        return redirect()->back();
    }

    /** Ganti PIN setelah PIN saat ini benar; lockout lama tidak terbawa (spec §6.5). */
    public function change(ChangeTwoFactorPinRequest $request, TwoFactorPinService $pins): RedirectResponse
    {
        $user = $this->sessionUser($request);
        $result = $pins->verify($user, (string) $request->validated('current_pin'));

        if ($result !== TwoFactorVerifyResult::Verified) {
            return redirect()->back()->withErrors(['current_pin' => $result->message()]);
        }

        $pins->change($user, (string) $request->validated('pin'));

        $request->session()->regenerate();
        EnsureTwoFactorVerified::markVerified($request, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'PIN berhasil diubah.',
        ]);

        return redirect()->back();
    }

    /** Nonaktifkan PIN setelah PIN saat ini benar; penanda sesi ikut dibersihkan. */
    public function disable(DisableTwoFactorRequest $request, TwoFactorPinService $pins): RedirectResponse
    {
        $user = $this->sessionUser($request);
        $result = $pins->verify($user, (string) $request->validated('current_pin'));

        if ($result !== TwoFactorVerifyResult::Verified) {
            return redirect()->back()->withErrors(['current_pin' => $result->message()]);
        }

        $pins->disable($user);

        $request->session()->regenerate();
        EnsureTwoFactorVerified::clearVerified($request);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'PIN dinonaktifkan.',
        ]);

        return redirect()->back();
    }
}
