<?php

namespace Melasistema\MelaCms\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Settings;

/**
 * /sitemap.xml: every address of the site search engines should know, in
 * each language. What goes in it is listed under `sitemap` in
 * config/mela-cms.php: model classes with a url($locale) method, and plain
 * paths for the pages a site serves with its own routes.
 */
class SitemapController
{
    public function __invoke(Languages $languages, Settings $settings): Response
    {
        $entries = config('mela-cms.sitemap') ?: [];

        // Nothing to list while search engines are asked to stay away.
        abort_if($entries === [] || $settings->get('site.seo.noindex'), 404);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        foreach ($entries as $entry) {
            if (! is_subclass_of($entry, Model::class)) {
                $xml .= $this->urls(fn (string $locale) => $languages->url($locale, (string) $entry), null, $languages);

                continue;
            }

            $query = $entry::query();

            if (method_exists($entry, 'scopePublished')) {
                $query->published();
            }

            foreach ($query->lazy() as $record) {
                if (method_exists($record, 'seoNoindex') && $record->seoNoindex()) {
                    continue;
                }

                $xml .= $this->urls($record->url(...), $record->updated_at?->toAtomString(), $languages);
            }
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * One <url> per language, each naming the others as alternates.
     *
     * @param  callable(string): string  $url
     */
    private function urls(callable $url, ?string $lastModified, Languages $languages): string
    {
        $urls = array_combine($languages->all(), array_map($url, $languages->all()));
        $xml = '';

        foreach ($urls as $address) {
            $xml .= '  <url>'."\n".'    <loc>'.e($address).'</loc>'."\n";

            if ($lastModified !== null) {
                $xml .= '    <lastmod>'.$lastModified.'</lastmod>'."\n";
            }

            if (count($urls) > 1) {
                foreach ($urls as $locale => $alternate) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="'.e(str_replace('_', '-', $locale)).'" href="'.e($alternate).'"/>'."\n";
                }
            }

            $xml .= '  </url>'."\n";
        }

        return $xml;
    }
}
