<?php

namespace Melasistema\MelaCms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Melasistema\MelaCms\Languages;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finds the language of a request from its URL and serves the request in it.
 *
 * The default language is at the root of the site. With 'path' URLs the
 * other languages are under a prefix: /it/about is the /about route in
 * Italian. The prefix is moved into the base URL of the request, as if the
 * app were installed in /it, so routes are written once and every generated
 * URL keeps the language.
 */
class SetLocale
{
    public function __construct(private Languages $languages) {}

    public function handle(Request $request, Closure $next): Response
    {
        $subdomains = config('mela-cms.locale_urls') === 'subdomain';
        $slug = $subdomains ? Str::before($request->getHost(), '.') : (string) $request->segment(1);
        $locale = $this->languages->fromSlug($slug);
        $default = $this->languages->default();

        // The default language has one address only: the root.
        if ($locale === $default && $request->isMethodSafe()) {
            return redirect()->to($this->withoutSlug($request, $slug, $subdomains));
        }

        if ($locale === null || $locale === $default || $subdomains) {
            $this->languages->use($locale ?? $default);
            $this->serveAssetsFrom(null);

            return $next($request);
        }

        $prefix = $request->getBaseUrl().'/'.$slug;

        if (! str_starts_with($request->getRequestUri(), $prefix)) {
            // An encoded prefix such as /%69t: not a language URL.
            $this->languages->use($default);

            return $next($request);
        }

        $this->languages->use($locale);
        $this->serveAssetsFrom($request->getSchemeAndHttpHost().$request->getBaseUrl());

        $response = $next($this->mountAt($request, $prefix));

        // The URL generator outlives the request in tests and on long-running
        // servers: it must not keep the prefix.
        URL::setRequest($request);

        return $response;
    }

    /**
     * The same request with the prefix as part of its base URL.
     */
    private function mountAt(Request $request, string $prefix): Request
    {
        $uri = $request->getRequestUri();
        $rest = substr($uri, strlen($prefix));

        // "/it" is the home page: the base URL is only found in "/it/".
        if ($rest === '' || $rest[0] === '?') {
            $uri = $prefix.'/'.$rest;
        }

        $script = basename($request->server->get('SCRIPT_FILENAME') ?: 'index.php');

        return $request->duplicate(server: [
            ...$request->server->all(),
            'REQUEST_URI' => $uri,
            'SCRIPT_FILENAME' => $request->server->get('SCRIPT_FILENAME') ?: $script,
            'SCRIPT_NAME' => $prefix.'/'.$script,
        ]);
    }

    /**
     * Files in public/ are not under the language prefix.
     */
    private function serveAssetsFrom(?string $root): void
    {
        if (config('app.asset_url') === null) {
            URL::useAssetOrigin($root);
        }
    }

    private function withoutSlug(Request $request, string $slug, bool $subdomains): string
    {
        if ($subdomains) {
            return $request->getScheme().'://'.Str::chopStart($request->getHttpHost(), $slug.'.').$request->getRequestUri();
        }

        $prefix = $request->getBaseUrl().'/'.$slug;
        $rest = Str::chopStart($request->getRequestUri(), $prefix);

        return $request->getSchemeAndHttpHost().$request->getBaseUrl().Str::start($rest, '/');
    }
}
