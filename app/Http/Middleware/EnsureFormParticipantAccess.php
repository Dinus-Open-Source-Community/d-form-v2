<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Event;
use App\Models\Form;
use App\Services\Form\GuestFormAccess;
use App\Services\Form\GuestFormSubmissionOwnership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Allows eligible guests through the shared participant form routes. */
final class EnsureFormParticipantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        $event = $request->route('event');
        $form = $request->route('form');

        if ($event instanceof Event && $form instanceof Form && $form->event_id !== $event->id) {
            abort(404);
        }

        if ($event instanceof Event && $form instanceof Form && GuestFormAccess::isEligible($event, $form)) {
            return $next($request);
        }

        if ($request->routeIs('dashboard.events.forms.submitted')
            && $event instanceof Event
            && $form instanceof Form
            && GuestFormSubmissionOwnership::isOwned($request, $event, $form)) {
            return $next($request);
        }

        return redirect()->guest(route('auth.login'));
    }
}
