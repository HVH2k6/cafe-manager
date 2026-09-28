<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Hiding a sidebar link does not stop anyone from typing the URL, so every
 * page behind login must also check a permission on the route itself.
 */
test('every authenticated route checks a permission', function () {
    /** Routes any logged-in user may open, whatever their role. */
    $openToAllStaff = ['dashboard', 'logout'];

    $unprotected = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => in_array('auth', $route->gatherMiddleware(), true))
        ->reject(fn (RoutingRoute $route): bool => in_array($route->getName(), $openToAllStaff, true))
        ->reject(fn (RoutingRoute $route): bool => collect($route->gatherMiddleware())
            ->contains(fn (mixed $middleware): bool => is_string($middleware) && Str::startsWith($middleware, 'can:')))
        ->map(fn (RoutingRoute $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unprotected)->toBe([], 'These routes need ->middleware(\'can:module.action\'): '.implode(', ', $unprotected));
});
