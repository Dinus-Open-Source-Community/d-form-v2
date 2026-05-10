<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = auth()->guard('web')->user();

        return [
            ...parent::share($request),
            'auth.user' => $user ? array_merge(
                $user->toArray(),
                [
                    'roles' => $user->getRoleNames()->toArray(),
                    'has_local_password' => filled($user->getRawOriginal('password')),
                    'can_manage_events' => $user->can('events.list'),
                ],
            ) : null,
        ];
    }
}
