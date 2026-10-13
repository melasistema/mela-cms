<?php

namespace Melasistema\MelaCms;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Locale;
use ResourceBundle;
use Spatie\Translatable\Translatable;

/**
 * The languages of the site and of the admin panel.
 *
 * What an admin saved on the Languages page wins. Until then the values come
 * from config/mela-cms.php.
 */
class Languages
{
    /**
     * The site language of the current request.
     */
    private ?string $current = null;

    /**
     * The path of the current page in each language, when it is not the same
     * in all of them: [locale => path].
     *
     * @var array<string, string>
     */
    private array $paths = [];

    public function __construct(private Settings $settings) {}

    /**
     * The languages the site is published in. The default language is first.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $locales = $this->settings->get('locales');

        return is_array($locales) && $locales !== []
            ? array_values($locales)
            : array_values(config('mela-cms.locales'));
    }

    /**
     * The language served at the root of the site.
     */
    public function default(): string
    {
        return $this->all()[0];
    }

    /**
     * Every site language except the default.
     *
     * @return list<string>
     */
    public function translations(): array
    {
        return array_slice($this->all(), 1);
    }

    /**
     * Where the slug of a record is looked for when the language of the
     * request, or the given one, has none: that language, then the default
     * language, then the other site languages in their order.
     *
     * @return list<string>
     */
    public function fallbacks(?string $locale = null): array
    {
        return array_values(array_unique([$locale ?? $this->current(), ...$this->all()]));
    }

    public function has(string $locale): bool
    {
        return in_array($locale, $this->all(), true);
    }

    /**
     * The site language of the current request. In the admin panel this is
     * still the language of the site, not the one of the panel.
     */
    public function current(): string
    {
        return $this->current ?? $this->default();
    }

    /**
     * Serve the current request in the given site language.
     */
    public function use(string $locale): void
    {
        $this->current = $locale;

        app()->setLocale($locale);
    }

    /**
     * How a language is written in a URL: "it", and "pt-br" for pt_BR.
     */
    public static function slug(string $locale): string
    {
        return Str::lower(str_replace('_', '-', $locale));
    }

    /**
     * The site language a URL segment or subdomain stands for, if any.
     */
    public function fromSlug(string $slug): ?string
    {
        return array_find($this->all(), fn (string $locale) => static::slug($locale) === $slug);
    }

    /**
     * The URL of a page in the given language: the current page, or the
     * given path. The default language has no prefix or subdomain.
     */
    public function url(string $locale, ?string $path = null): string
    {
        $request = request();
        $path = '/'.ltrim($path ?? $request->getPathInfo(), '/');

        $current = $this->current() === $this->default() ? null : static::slug($this->current());
        $slug = $locale === $this->default() ? null : static::slug($locale);

        if (config('mela-cms.locale_urls') === 'subdomain') {
            $host = $current === null ? $request->getHttpHost() : Str::chopStart($request->getHttpHost(), $current.'.');

            $root = $request->getScheme().'://'.($slug === null ? '' : $slug.'.').$host.$request->getBaseUrl();
        } else {
            // On a translated page the prefix is part of the base URL.
            $base = $current === null ? $request->getBaseUrl() : Str::chopEnd($request->getBaseUrl(), '/'.$current);

            $root = $request->getSchemeAndHttpHost().$base.($slug === null ? '' : '/'.$slug);
        }

        return rtrim($root.$path, '/');
    }

    /**
     * Tell where the current page lives in each language, when its address
     * is translated: [locale => path]. alternates() and hreflang() follow it.
     * A model with translated slugs gives the paths with slugs().
     *
     * @param  array<string, string|null>  $paths
     */
    public function localize(array $paths): void
    {
        $this->paths = array_filter($paths, is_string(...));
    }

    /**
     * The current page in every site language, for a language switcher:
     * [locale => url].
     *
     * @return array<string, string>
     */
    public function alternates(): array
    {
        return array_combine($this->all(), array_map(
            fn (string $locale) => $this->url($locale, $this->paths[$locale] ?? null),
            $this->all(),
        ));
    }

    /**
     * The <link rel="alternate" hreflang> tags of the current page. Empty
     * when the site has one language.
     */
    public function hreflang(): HtmlString
    {
        if ($this->translations() === []) {
            return new HtmlString('');
        }

        $tags = [];

        $alternates = $this->alternates();

        foreach ($alternates as $locale => $url) {
            $tags[] = '<link rel="alternate" hreflang="'.e(str_replace('_', '-', $locale)).'" href="'.e($url).'">';
        }

        $tags[] = '<link rel="alternate" hreflang="x-default" href="'.e($alternates[$this->default()]).'">';

        return new HtmlString(implode("\n", $tags));
    }

    /**
     * The value of the language of the request among [locale => value]. An
     * empty one falls back to the default language of the site.
     */
    public function translation(mixed $translations, mixed $default = null): mixed
    {
        $translations = is_array($translations) ? $translations : [];

        return array_find(
            [$translations[app()->getLocale()] ?? null, $translations[$this->default()] ?? null],
            fn (mixed $value) => filled($value),
        ) ?? $default;
    }

    /**
     * The language of the admin panel. It does not have to be a site language.
     */
    public function admin(): string
    {
        return $this->settings->get('admin_locale')
            ?? config('mela-cms.admin_locale')
            ?? config('app.locale');
    }

    /**
     * @param  array<int, string>  $locales
     */
    public function update(array $locales, string $default, string $admin): void
    {
        $this->settings->set('locales', array_values(array_unique([$default, ...$locales])));
        $this->settings->set('admin_locale', $admin);

        app(Translatable::class)->fallbackLocale = $default;
    }

    /**
     * The name of a language: in its own language ("Italiano") by default,
     * or in the language given as $in.
     */
    public static function name(string $locale, ?string $in = null): string
    {
        return Str::ucfirst(Locale::getDisplayName($locale, $in ?? $locale));
    }

    /**
     * The languages a site can be published in, named in the current
     * language: [code => name].
     *
     * @return array<string, string>
     */
    public function available(): array
    {
        $codes = array_filter(ResourceBundle::getLocales(''), fn (string $code) => ! str_contains($code, '_'));

        // A regional locale such as pt_BR can be set in the config.
        return $this->options([...$codes, ...$this->all()]);
    }

    /**
     * The languages the admin panel is translated into: [code => name].
     *
     * @return array<string, string>
     */
    public function availableForAdmin(): array
    {
        $path = app('translator')->getLoader()->namespaces()['filament-panels'] ?? null;

        $codes = $path === null ? [] : array_map(basename(...), glob($path.'/*', GLOB_ONLYDIR) ?: []);

        return $this->options([...$codes, $this->admin()]);
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, string>
     */
    private function options(array $codes): array
    {
        $options = [];

        foreach (array_unique($codes) as $code) {
            $options[$code] = static::name($code, app()->getLocale())." ({$code})";
        }

        asort($options);

        return $options;
    }
}
