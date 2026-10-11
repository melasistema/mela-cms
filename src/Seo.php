<?php

namespace Melasistema\MelaCms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Melasistema\MelaCms\Models\Media;

/**
 * What the current page tells search engines and social networks. A route
 * fills it from a record, or value by value, and the layout prints it in the
 * <head> with @seo.
 *
 *     app(Seo::class)->for($post);
 *     app(Seo::class)->title('Blog')->description('Everything we wrote.');
 *
 * What is not given comes from the Settings page: the site name, its
 * description and its share image.
 */
class Seo
{
    public const string DEFAULT_TITLE_FORMAT = ':title | :site';

    private ?string $title = null;

    private ?string $description = null;

    private int|string|Media|null $image = null;

    private bool $noindex = false;

    private string $type = 'website';

    public function __construct(private Settings $settings, private Languages $languages) {}

    /**
     * Take title, description, image and indexing from a record. A model
     * with the HasSeo trait gives what an editor wrote for search engines;
     * any other gives its title, its excerpt and its cover.
     */
    public function for(Model $model): static
    {
        if (method_exists($model, 'seoTitle')) {
            $this->title = $model->seoTitle();
            $this->description = $model->seoDescription();
            $this->image = $model->seoImageId();
            $this->noindex = $model->seoNoindex();
        } else {
            $this->title = $model->getAttribute('title') ?? $model->getAttribute('name');
            $this->description = $model->getAttribute('excerpt');
            $this->image = $model->getAttribute('cover_id');
        }

        // What visitors cannot see yet is not for search engines either.
        if (method_exists($model, 'isPublished') && ! $model->isPublished()) {
            $this->noindex = true;
        }

        return $this;
    }

    public function title(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * A file of the media library, or its id.
     */
    public function image(int|string|Media|null $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function noindex(bool $condition = true): static
    {
        $this->noindex = $condition;

        return $this;
    }

    /**
     * The Open Graph type of the page: `website`, or `article` for a post.
     */
    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function siteName(): string
    {
        return (string) $this->settings->translated('site.name', config('app.name'));
    }

    /**
     * The title of the page without the name of the site.
     */
    public function getPageTitle(): string
    {
        return filled($this->title) ? $this->title : $this->siteName();
    }

    /**
     * The title for the <title> tag, in the format of the Settings page. A
     * page without a title of its own is called like the site.
     */
    public function getTitle(): string
    {
        $site = $this->siteName();

        if (blank($this->title) || $this->title === $site) {
            return $site;
        }

        $format = $this->settings->get('site.seo.title_format');

        return strtr(filled($format) ? $format : self::DEFAULT_TITLE_FORMAT, [
            ':title' => $this->title,
            ':site' => $site,
        ]);
    }

    public function getDescription(): ?string
    {
        $description = filled($this->description) ? $this->description : $this->settings->translated('site.description');

        return filled($description) ? trim(preg_replace('/\s+/', ' ', strip_tags($description))) : null;
    }

    /**
     * The image shown when the page is shared: the one of the page, or the
     * share image of the site.
     */
    public function getImage(): ?Media
    {
        foreach ([$this->image, $this->settings->get('site.share_image')] as $image) {
            $media = $image instanceof Media ? $image : (blank($image) ? null : Media::query()->find($image));

            if ($media?->isImage()) {
                return $media;
            }
        }

        return null;
    }

    /**
     * False for a page that asked for it, and for every page while the
     * Settings page asks search engines to stay away.
     */
    public function isIndexable(): bool
    {
        return ! $this->noindex && ! $this->settings->get('site.seo.noindex');
    }

    /**
     * The address search engines should keep for the page: the current one
     * without its query string.
     */
    public function getCanonical(): string
    {
        return url()->current();
    }

    /**
     * The tags for the <head>.
     */
    public function render(): HtmlString
    {
        $title = $this->getTitle();
        $description = $this->getDescription();
        $image = $this->getImage();
        $canonical = $this->getCanonical();

        $tags = ['<title>'.e($title).'</title>'];

        if ($description !== null) {
            $tags[] = $this->meta('name', 'description', $description);
        }

        if (! $this->isIndexable()) {
            $tags[] = $this->meta('name', 'robots', 'noindex, nofollow');
        }

        $tags[] = '<link rel="canonical" href="'.e($canonical).'">';
        $tags[] = $this->meta('property', 'og:type', $this->type);
        $tags[] = $this->meta('property', 'og:site_name', $this->siteName());
        $tags[] = $this->meta('property', 'og:title', $this->getPageTitle());

        if ($description !== null) {
            $tags[] = $this->meta('property', 'og:description', $description);
        }

        $tags[] = $this->meta('property', 'og:url', $canonical);
        $tags[] = $this->meta('property', 'og:locale', $this->languages->current());

        if ($image !== null) {
            // The original file: not every network reads the WebP sizes.
            $tags[] = $this->meta('property', 'og:image', $image->url());

            if ($image->width && $image->height) {
                $tags[] = $this->meta('property', 'og:image:width', (string) $image->width);
                $tags[] = $this->meta('property', 'og:image:height', (string) $image->height);
            }

            if (filled($image->alt)) {
                $tags[] = $this->meta('property', 'og:image:alt', $image->alt);
            }
        }

        $tags[] = $this->meta('name', 'twitter:card', $image === null ? 'summary' : 'summary_large_image');

        return new HtmlString(implode("\n", $tags));
    }

    private function meta(string $attribute, string $name, string $content): string
    {
        return '<meta '.$attribute.'="'.e($name).'" content="'.e($content).'">';
    }
}
