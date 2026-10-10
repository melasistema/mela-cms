<?php

namespace Melasistema\MelaCms\Http\Controllers;

use Illuminate\Http\Request;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Models\Page;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers the addresses that no route of the site matches: the home page at
 * the root, and every other page at its slug in the language of the request.
 *
 * Visitors see published pages only. Someone logged in who may edit the page
 * also sees a draft, to check it before publishing.
 */
class PageController
{
    public function __invoke(Request $request, Languages $languages): Response
    {
        abort_unless((bool) config('mela-cms.public_pages'), 404);

        $path = trim($request->path(), '/');

        $page = $path === ''
            ? Page::home()
            : Page::query()->whereSlug($path)->first();

        abort_if($page === null, 404);
        abort_unless($page->isPublished() || $request->user()?->can('update', $page), 404);

        // The home page has one address only: the root.
        if ($path !== '' && $page->isHome()) {
            return redirect()->to($page->url(), 301);
        }

        // The language switcher and the hreflang tags follow the slug of each language.
        $languages->localize($page->paths());

        return response()->view($page->view(), ['page' => $page]);
    }
}
