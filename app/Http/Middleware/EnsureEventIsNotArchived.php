<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak route event-scoped ketika event yang ter-bind sedang diarsipkan.
 *
 * Parameter route dipindai, bukan hanya yang bernama `event`: route baru bisa
 * memakai `{eventId}`, dan `Event::resolveRouteBinding()` membuka trashed
 * berdasarkan nama route (`dashboard.events.*`) — bukan nama parameter —
 * sehingga route seperti itu lolos bila pemeriksaan hanya membaca
 * `$request->route('event')`.
 *
 * Default-deny: model dicari lebih dulu, lalu hanya route pada
 * READ_ONLY_ALLOWED_ROUTES yang diizinkan; route tanpa nama ikut ditolak karena
 * tidak bisa masuk allowlist. Kelas ini hanya melindungi group/route yang
 * diberi alias `event.not-archived`; kelengkapan anotasi setiap route `{event}`
 * ditegakkan oleh registry test di ArchivedEventWriteBlockTest.
 */
class EnsureEventIsNotArchived
{
    public const READ_ONLY_ALLOWED_ROUTES = [
        'dashboard.events.index',
        'dashboard.events.create',
        'dashboard.events.store',
        'dashboard.events.show',
        'dashboard.events.archived',
        'dashboard.events.restore',
        'dashboard.events.force-delete',
        'dashboard.events.registration-status',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $event = $route === null ? null : $this->firstBoundEvent($route->parameters());

        if (
            $event?->trashed()
            && ! in_array($route?->getName(), self::READ_ONLY_ALLOWED_ROUTES, true)
        ) {
            abort(409, __('messages.event.archived.read_only'));
        }

        return $next($request);
    }

    /**
     * Ambil model Event pertama dari parameter route yang sudah ter-resolve.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function firstBoundEvent(array $parameters): ?Event
    {
        foreach ($parameters as $parameter) {
            if ($parameter instanceof Event) {
                return $parameter;
            }
        }

        return null;
    }
}
