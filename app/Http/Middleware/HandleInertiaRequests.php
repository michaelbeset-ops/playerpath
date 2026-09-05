<?php

namespace App\Http\Middleware;

use App\Support\Navigation\MainNavigation;
use App\Support\Tenancy\Tenancy;
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
        // De publiek gedeelde spelerskaart staat buiten de app: geen menu, geen
        // ingelogde gebruiker, geen school. Zou hij de gewone gedeelde props
        // krijgen, dan stond de naam van de school in de broncode van een
        // pagina die iedereen met de link kan openen.
        // Expliciet leegzetten in plaats van weglaten: Inertia's gedeelde props
        // zijn statisch, dus een eerdere request kan er nog iets in hebben laten
        // staan. Overschrijven is het enige wat gegarandeerd werkt.
        if ($request->routeIs('players.shared')) {
            return array_merge(parent::share($request), [
                'name' => config('app.name'),
                'auth' => ['user' => null, 'roles' => []],
                'school' => null,
                'nav' => [],
                'unreadNotifications' => 0,
                'flash' => ['status' => null],
            ]);
        }

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->getRoleNames()->all() ?? [],
            ],
            'school' => fn () => app(Tenancy::class)->school()?->only(['id', 'name']),
            'nav' => fn () => app(MainNavigation::class)->for($request->user()),
            'unreadNotifications' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ]);
    }
}
