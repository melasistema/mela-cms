<?php

namespace Melasistema\MelaCms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Melasistema\MelaCms\Concerns\HasSeo;
use Melasistema\MelaCms\Concerns\HasSlug;
use Melasistema\MelaCms\Concerns\Publishable;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Settings;
use Spatie\Translatable\HasTranslations;

/**
 * A page of the site: a title, an address and a text in each language, shown
 * by one of the site's views in resources/views/pages.
 *
 * @property string $title
 * @property string $slug
 * @property string|null $body
 * @property string $template
 * @property int|null $cover_id
 * @property array<string, mixed>|null $fields
 * @property array<string, mixed>|null $seo
 * @property Carbon|null $published_at
 * @property-read Media|null $cover
 */
class Page extends Model
{
    use HasSeo {
        seoTitle as protected seoTitleOrTitle;
    }
    use HasSlug;
    use HasTranslations;
    use Publishable;

    protected $table = 'mela_pages';

    protected $guarded = [];

    protected $attributes = [
        'template' => 'default',
    ];

    /**
     * @var array<string>
     */
    public array $translatable = ['title', 'slug', 'body'];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_id');
    }

    /**
     * The page an admin chose as the home page on the Settings page.
     */
    public static function home(): ?static
    {
        $id = app(Settings::class)->get('site.home_page');

        return blank($id) ? null : static::query()->find($id);
    }

    public function isHome(): bool
    {
        return $this->exists && (string) app(Settings::class)->get('site.home_page') === (string) $this->getKey();
    }

    /**
     * The home page without a title written for search engines is called
     * like the site, not "Home".
     */
    public function seoTitle(): ?string
    {
        return $this->isHome()
            ? app(Languages::class)->translation(Arr::get($this->seo ?? [], 'title'))
            : $this->seoTitleOrTitle();
    }

    /**
     * The address of the page inside the site, without the language: its
     * slug, or nothing for the home page.
     */
    public function path(?string $locale = null): string
    {
        if ($this->isHome()) {
            return '';
        }

        return (string) $this->slugIn($locale);
    }

    /**
     * The address of the page in every site language: [locale => path].
     *
     * @return array<string, string>
     */
    public function paths(): array
    {
        $languages = app(Languages::class)->all();

        return array_combine($languages, array_map($this->path(...), $languages));
    }

    /**
     * The full address of the page, in the language of the request or in the
     * given one.
     */
    public function url(?string $locale = null): string
    {
        $locale ??= app(Languages::class)->current();

        return app(Languages::class)->url($locale, $this->path($locale));
    }

    /**
     * A value of a field the site added to the page form under `fields`. A
     * dot reaches inside: `hero.button`.
     */
    public function field(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->fields ?? [], $key) ?? $default;
    }

    /**
     * A field the site added inside Translations, in the language of the
     * request. An empty translation falls back to the default language.
     */
    public function translated(string $key, mixed $default = null): mixed
    {
        return app(Languages::class)->translation($this->field($key), $default);
    }

    /**
     * The templates an editor can choose from: the views of the site in
     * resources/views/pages, [name => label]. `default` is always there.
     *
     * @return array<string, string>
     */
    public static function templates(): array
    {
        $templates = ['default' => 'Default'];

        foreach (app('view')->getFinder()->getPaths() as $path) {
            foreach (glob($path.'/pages/*.blade.php') ?: [] as $file) {
                $name = basename($file, '.blade.php');

                $templates[$name] = Str::headline($name);
            }
        }

        return $templates;
    }

    /**
     * The view that shows the page: the one of its template, or the default
     * one when the template is gone. Until the site has its own
     * pages/default view, the plain one of the core is used.
     */
    public function view(): string
    {
        return array_find(
            ["pages.{$this->template}", 'pages.default'],
            fn (string $view) => view()->exists($view),
        ) ?? 'mela-cms::pages.default';
    }
}
