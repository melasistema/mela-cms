<?php

namespace Melasistema\MelaCms;

use Illuminate\Support\Str;
use Locale;
use ResourceBundle;

/**
 * The languages of the site and of the admin panel.
 *
 * What an admin saved on the Languages page wins. Until then the values come
 * from config/mela-cms.php.
 */
class Languages
{
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

    public function has(string $locale): bool
    {
        return in_array($locale, $this->all(), true);
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
