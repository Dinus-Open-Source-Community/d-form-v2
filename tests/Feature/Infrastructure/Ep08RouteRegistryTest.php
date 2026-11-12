<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Routing\Route;
use Tests\TestCase;

class Ep08RouteRegistryTest extends TestCase
{
    public function test_route_names_are_unique_for_BE_P2_010(): void
    {
        $routesByName = [];

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if (! is_string($name) || $name === '') {
                continue;
            }

            $routesByName[$name][] = $route;
        }

        $duplicateRoutes = array_filter(
            $routesByName,
            static fn (array $routes): bool => count($routes) > 1,
        );

        $duplicateDiagnostics = [];
        foreach ($duplicateRoutes as $name => $routes) {
            $duplicateDiagnostics[] = sprintf(
                '%s => %s',
                $name,
                implode(', ', array_map(
                    static fn (Route $route): string => $route->uri(),
                    $routes,
                )),
            );
        }

        $legacyRedirectUris = [
            'admin/recruitment/interview-sessions',
            'admin/recruitment/reports',
            'admin/recruitment/periods',
        ];
        $legacyRedirectDiagnostics = [];

        foreach ($legacyRedirectUris as $uri) {
            $matchingRoutes = array_values(array_filter(
                app('router')->getRoutes()->getRoutes(),
                static fn (Route $route): bool => $route->uri() === $uri,
            ));

            $legacyRedirectDiagnostics[] = sprintf(
                '%s => %s',
                $uri,
                $matchingRoutes === []
                    ? '<not registered>'
                    : implode(', ', array_map(
                        static fn (Route $route): string => $route->getName() ?? '<anonymous>',
                        $matchingRoutes,
                    )),
            );
        }

        self::assertCount(
            0,
            $duplicateRoutes,
            "Duplicate named routes:\n"
            .implode("\n", $duplicateDiagnostics)
            ."\nLegacy redirect routes:\n"
            .implode("\n", $legacyRedirectDiagnostics),
        );
    }
}
