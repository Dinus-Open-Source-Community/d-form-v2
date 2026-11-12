<?php

namespace App\Services\Auth;

use App\Exceptions\RedactedMailDeliveryException;
use Illuminate\Support\Facades\Password;
use Throwable;

class PasswordResetService
{
    /**
     * Send a password reset link to the given email address.
     *
     * Always completes without revealing whether the account exists.
     */
    public function sendResetLink(string $email): void
    {
        try {
            Password::sendResetLink(['email' => $email]);
        } catch (Throwable $exception) {
            report(RedactedMailDeliveryException::fromThrowable($exception));
        }
    }
}
