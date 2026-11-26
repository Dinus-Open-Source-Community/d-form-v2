<?php

namespace App\Services\Form;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use Illuminate\Http\Request;

/** Proves that a public success request belongs to the current guest browser. */
final class GuestFormSubmissionOwnership
{
    public static function isOwned(Request $request, Event $event, Form $form): bool
    {
        if ($form->event_id !== $event->id) {
            return false;
        }

        $token = $request->cookie(GuestFormToken::COOKIE_NAME);
        if (! is_string($token) || ! GuestFormToken::isValid($token)) {
            return false;
        }

        if ($request->session()->get(GuestFormAccess::sessionKey($form)) !== true) {
            return false;
        }

        return FormAnswer::query()
            ->where('form_id', $form->id)
            ->whereNull('user_id')
            ->where('guest_token_hash', GuestFormToken::hash($token))
            ->where('is_active', 1)
            ->exists();
    }

    public static function assertOwned(Request $request, Event $event, Form $form): string
    {
        abort_unless(self::isOwned($request, $event, $form), 404);

        $token = $request->cookie(GuestFormToken::COOKIE_NAME);
        if (! is_string($token)) {
            abort(404);
        }

        return GuestFormToken::hash($token);
    }
}
