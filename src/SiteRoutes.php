<?php

namespace Melasistema\MelaCms;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The routes of the site, as opposed to the fallback route that serves pages.
 * A page is never shown at an address one of them answers.
 */
final class SiteRoutes
{
    /**
     * Whether a route of the site answers the address. A route for another
     * method counts: Laravel answers a visit with a 405.
     */
    public static function answer(string $path): bool
    {
        $request = Request::create(request()->getSchemeAndHttpHost().'/'.ltrim($path, '/'));

        foreach (Route::getRoutes() as $route) {
            if (! $route->isFallback && $route->matches($request, includingMethod: false)) {
                return true;
            }
        }

        return false;
    }
}
