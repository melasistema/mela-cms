<?php

namespace Melasistema\MelaCms\Http\Controllers;

use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Request;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Models\Page;
use Melasistema\MelaCms\Seo;
use Melasistema\MelaCms\Settings;
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
    public function __invoke(Request $request, Languages $languages, Seo $seo): Response
    {
        abort_unless((bool) config('mela-cms.public_pages'), 404);

        $path = trim($request->path(), '/');

        $page = $path === ''
            ? Page::home()
            : Page::query()->whereSlug($path)->first();

        $visible = $page !== null && ($page->isPublished() || $request->user()?->can('update', $page));

        if (! $visible && $path === '' && config('mela-cms.resources.pages') !== null) {
            return $this->noHome($request);
        }

        abort_unless($visible, 404);

        // The home page has one address only: the root.
        if ($path !== '' && $page->isHome()) {
            return redirect()->to($page->url(), 301);
        }

        // The language switcher and the hreflang tags follow the slug of each language.
        $languages->localize($page->paths());

        // What @seo prints in the <head> of the view.
        $seo->for($page);

        return response()->view($page->view(), ['page' => $page]);
    }

    /**
     * The root while there is no home page to show, instead of a bare 404: on
     * a new site, the first thing anyone opens. Answered with a 200, because
     * the site is up (Laravel's stock test and uptime checks ask `/` for one);
     * the view tells search engines not to index it. The admin is linked only
     * for those who can open it.
     */
    private function noHome(Request $request): Response
    {
        $panel = Filament::getDefaultPanel();
        $user = $request->user();

        return response()->view('mela-cms::pages.no-home', [
            'siteName' => app(Settings::class)->translated('site.name', config('app.name')),
            'adminUrl' => $user instanceof FilamentUser && $user->canAccessPanel($panel) ? url($panel->getPath()) : null,
        ]);
    }
}
