<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\RegistrationOtpService;
use App\Support\PendingRegistrationSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Batalkan pendaftaran tertunda milik sesi lalu kembali ke form register.
 */
class CancelPendingRegistrationController extends Controller
{
    /**
     * Hapus pending hanya bila sesi memuat email dan token pembatalan dari alur register.
     */
    public function __invoke(Request $request, RegistrationOtpService $service): RedirectResponse
    {
        $email = PendingRegistrationSession::email($request);
        $token = PendingRegistrationSession::cancelToken($request);

        if ($email !== null && $token !== null) {
            $service->cancel($email);
        }

        PendingRegistrationSession::forget($request);

        return redirect()->route('auth.register');
    }
}
