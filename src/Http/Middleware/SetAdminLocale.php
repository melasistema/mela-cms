<?php

namespace Melasistema\MelaCms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Melasistema\MelaCms\Languages;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel has its own language, set on the Languages page. It is
 * independent of the languages of the site.
 */
class SetAdminLocale
{
    public function __construct(private Languages $languages) {}

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->languages->admin());

        return $next($request);
    }
}
