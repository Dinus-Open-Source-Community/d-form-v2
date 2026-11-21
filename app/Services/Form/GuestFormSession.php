<?php

namespace App\Services\Form;

use App\Models\Form;
use Illuminate\Http\Request;

/** Establishes and verifies the session marker required by public form boundaries. */
final class GuestFormSession
{
    public static function establish(Request $request, Form $form): string
    {
        $token = $request->cookie(GuestFormToken::COOKIE_NAME);
        if (! is_string($token) || ! GuestFormToken::isValid($token)) {
            $token = GuestFormToken::issue();
        }
        $request->session()->put(GuestFormAccess::sessionKey($form), true);
        return $token;
    }

    public static function assertEstablished(Request $request, Form $form): string
    {
        $token = $request->cookie(GuestFormToken::COOKIE_NAME);
        abort_unless(is_string($token) && GuestFormToken::isValid($token), 404);
        abort_unless($request->session()->get(GuestFormAccess::sessionKey($form)) === true, 404);
        return $token;
    }
}
